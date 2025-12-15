<?php
session_start();
// Include db.php for file structure, although the connection is unused
include 'db.php'; 

// --- Configuration (XML file constants) ---
const CART_STORAGE_FILE = 'data/cart.xml';
const PRODUCT_CATALOG_FILE = 'data/products.xml';
const ORDER_STORAGE_FILE = 'data/orders.xml'; 

$error = '';
$success = '';
// Using a fixed shipping cost example (₱50.00)
$shipping_cost = 50.00; 

// --- 1. User Authentication Check ---
$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id)) {
    // Redirect unauthenticated users to login
    header("Location: login.php?redirect=" . urlencode('checkout.php'));
    exit;
}

// FIX 1: Add format_currency function for local use and consistency (from $ to ₱)
/**
 * Formats a number/string as currency (₱XX.XX).
 */
function format_currency($amount) {
    if (is_string($amount)) {
        // Remove existing currency symbols or commas for clean conversion
        $amount = str_replace(['$', '₱', ','], '', $amount); 
    }
    // Change the currency symbol to Philippine Peso (₱)
    return '₱' . number_format((float)$amount, 2); 
}


// --- 2. XML Utility Functions (Consistent with cart.php, adapted for orders) ---

/**
 * Loads all user carts from the XML file.
 * Returns: array [user_id => [product_id => quantity]]
 */
function load_all_carts_xml() {
    $all_carts = [];
    if (!file_exists(CART_STORAGE_FILE) || filesize(CART_STORAGE_FILE) == 0) {
        return $all_carts;
    }
    $xml = @simplexml_load_file(CART_STORAGE_FILE);
    if ($xml === false) { return $all_carts; }

    foreach ($xml->user_cart as $user_cart) {
        $uid = (string)$user_cart['user_id'];
        $items = [];
        foreach ($user_cart->item as $item) {
            // FIX: Load product ID as string/integer attribute
            $items[(string)$item['id']] = (int)$item['qty'];
        }
        $all_carts[$uid] = $items;
    }
    return $all_carts;
}

/**
 * Saves all carts back to the XML file.
 */
function save_all_carts_xml(array $all_carts) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><carts></carts>');

    foreach ($all_carts as $uid => $cart_items) {
        if (!empty($cart_items)) {
            $user_cart = $xml->addChild('user_cart');
            $user_cart->addAttribute('user_id', $uid);

            foreach ($cart_items as $pid => $qty) {
                if ($qty > 0) {
                    $item = $user_cart->addChild('item');
                    // FIX: Ensure PID is saved as the correct string/integer attribute
                    $item->addAttribute('id', $pid); 
                    $item->addAttribute('qty', $qty);
                }
            }
        }
    }
    return $xml->asXML(CART_STORAGE_FILE);
}

/**
 * Loads product details from the catalog XML file, indexed by their ID attribute.
 * FIX: Use the 'id' attribute from XML instead of a counter.
 * Returns: array [product_id => product_data]
 */
function load_product_catalog_xml() {
    $products = [];
    if (!file_exists(PRODUCT_CATALOG_FILE)) { return $products; }

    $xml = @simplexml_load_file(PRODUCT_CATALOG_FILE);
    if ($xml === false) { return $products; }
    
    foreach ($xml->product as $p) {
        // FIX: Use the 'id' attribute from XML instead of a counter.
        $product_id = (string)$p['id']; 
        $products[$product_id] = [
            'name' => (string)$p->name,
            'price' => (float)$p->price,
            'stock' => (int)$p->stock, // Read stock for checking availability
        ];
    }
    return $products;
}

/**
 * Loads the SimpleXMLElement object for the product catalog (Used for READ/WRITE stock).
 * Returns: SimpleXMLElement|false
 */
function load_product_catalog_xml_object() {
    if (!file_exists(PRODUCT_CATALOG_FILE)) { return false; }
    $xml = @simplexml_load_file(PRODUCT_CATALOG_FILE);
    return $xml;
}

/**
 * NEW FUNCTION: Updates stock levels in the product catalog XML.
 * @param array $cart_details Array of items in the current order with 'id' and 'qty'.
 * Returns: bool Success/Failure
 */
function deduct_product_stocks_xml(array $cart_details) {
    $xml = load_product_catalog_xml_object();
    if ($xml === false) { return false; }

    foreach ($cart_details as $item) {
        $product_id_to_find = (string)$item['id'];
        $quantity_ordered = (int)$item['qty'];
        
        // Use XPath to find the specific product by its 'id' attribute
        $result = $xml->xpath("//product[@id='{$product_id_to_find}']");
        
        if (!empty($result)) {
            $product_node = $result[0]; // The first result is the product node
            
            // ASSUMPTION: The product XML must contain a <stock> element.
            if (isset($product_node->stock)) { 
                $current_stock = (int)$product_node->stock;
                $new_stock = $current_stock - $quantity_ordered;
                
                if ($new_stock < 0) {
                     $new_stock = 0; // Cap at 0 to prevent negative stock
                }
                
                // Update the stock value in the SimpleXMLElement object
                $product_node->stock = (string)$new_stock;
            } 
        }
    }

    // Save the modified XML back to the file
    return $xml->asXML(PRODUCT_CATALOG_FILE);
}


/**
 * Loads the current user's cart items from the XML file. (Wraps load_all_carts_xml)
 * Returns: array [product_id => quantity]
 */
function load_user_cart_xml(string $user_id) {
    $all_carts = load_all_carts_xml();
    return $all_carts[$user_id] ?? [];
}

/**
 * Clears the user's cart in the XML file.
 * @param string $user_id The current user's ID.
 * Returns: bool Success/Failure
 */
function clear_user_cart_xml(string $user_id) {
    $all_carts = load_all_carts_xml();
    if (isset($all_carts[$user_id])) {
        unset($all_carts[$user_id]);
    }
    return save_all_carts_xml($all_carts);
}


/**
 * Adds a successful order to the 'orders.xml' file.
 * @param string $user_id The current user's ID.
 * @param array $items Array of cart item details.
 * @param float $total The grand total of the order.
 * Returns: int|false The new Order ID on success, or false on failure.
 */
function save_order_xml($user_id, array $items, float $total) {
    // 1. Load existing orders or create the structure
    if (!file_exists(ORDER_STORAGE_FILE) || filesize(ORDER_STORAGE_FILE) == 0) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><orders></orders>');
    } else {
        $xml = @simplexml_load_file(ORDER_STORAGE_FILE);
        if ($xml === false) { return false; }
    }
    
    // 2. Determine new Order ID (Fixed: Count all existing 'order' elements + 1)
    $order_id = $xml->order->count() + 1;
    if ($order_id === 0) { 
        $order_id = 1; // Start at 1 if no elements are found (e.g., brand new file)
    }

    // 3. Create the new order element
    $order = $xml->addChild('order');
    $order->addAttribute('id', $order_id);
    $order->addChild('user_id', $user_id);
    $order->addChild('order_date', date('Y-m-d H:i:s'));
    $order->addChild('total_amount', number_format($total, 2));

    $order_items = $order->addChild('items');
    
    // 4. Add items to the order
    foreach ($items as $item) {
        $item_xml = $order_items->addChild('item');
        // IMPORTANT: Use the actual product ID (e.g., '101')
        $item_xml->addAttribute('product_id', $item['id']); 
        $item_xml->addChild('product_name', $item['name']);
        $item_xml->addChild('unit_price', number_format($item['price'], 2));
        $item_xml->addChild('quantity', $item['qty']);
    }
    
    // 5. Save the XML file
    if ($xml->asXML(ORDER_STORAGE_FILE)) {
        return $order_id; // Return the ID on success
    }
    return false; // Return false on failure
}


// --- 3. Load Data & Calculate Totals ---

// Load ONLY the current user's cart (XML method)
$user_cart_items = load_user_cart_xml($user_id);
$products = load_product_catalog_xml();

if (empty($user_cart_items)) {
    header("Location: shop.php?error=empty_cart");
    exit;
}

$cart_details = [];
$subtotal = 0.00;
$stock_issue = false;

foreach ($user_cart_items as $product_id => $qty) {
    if (isset($products[$product_id])) {
        $product = $products[$product_id];
        $price = $product['price'];
        $item_subtotal = $price * $qty;
        
        // Stock check before adding to cart details
        if ($qty > $product['stock']) {
            $error = "Stock issue: Ordered quantity for " . $product['name'] . " exceeds available stock (" . $product['stock'] . "). Please update your cart.";
            $stock_issue = true;
            break; 
        }

        $cart_details[] = [
            'id' => $product_id,
            'name' => $product['name'],
            'price' => $price,
            'qty' => $qty,
            'subtotal' => $item_subtotal
        ];
        $subtotal += $item_subtotal;
    }
}

if ($stock_issue) {
    // Redirect back to cart if there's a stock issue
    header("Location: cart.php?error=" . urlencode($error));
    exit;
}


$grand_total = $subtotal + $shipping_cost;


// --- 4. Handle Order Submission (POST Request) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    
    // 1. Save the order history (with order items) - Capture the Order ID
    $new_order_id = save_order_xml($user_id, $cart_details, $grand_total);
    
    if ($new_order_id) { 
        
        // NEW STEP 1.5: DEDUCT STOCKS
        if (deduct_product_stocks_xml($cart_details)) {
            
            // Stock deduction succeeded. Proceed to clear cart.
            
            // 2. Clear the user's cart in the XML file
            if (clear_user_cart_xml($user_id)) {
                // Success! Redirect to confirmation page, passing the secure Order ID
                header("Location: confirmation.php?order_id=" . $new_order_id);
                exit;
            } else {
                // Order saved and stock deducted, but cart clear failed. 
                $error = "Order saved and stock deducted, but failed to clear cart data. Please clear your cart manually.";
            }
        } else {
            // Order saved, but stock deduction failed.
            $error = "Order saved, but failed to deduct product stocks. Inventory levels may be incorrect. Please check products.xml.";
        }

    } else {
        $error = "Failed to process and save your order history.";
    }
}


// --- 5. HTML Output (Updated) ---

// Assuming header.php and footer.php exist for the main site
include 'header.php'; 
?>

<main class="checkout-page">
    <h2>Checkout & Place Order</h2>

    <?php if ($error): ?>
        <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success-message"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <section class="checkout-summary">
        <h3>Order Summary</h3>
        <div class="cart-items-list">
            <?php foreach ($cart_details as $item): ?>
            <div class="summary-item">
                <span class="item-name"><?= htmlspecialchars($item['name']) ?> (x<?= $item['qty'] ?>)</span>
                <span class="item-price"><?= format_currency($item['subtotal']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <hr>

        <div class="summary-line">
            <span class="label">Subtotal:</span>
            <span class="value"><?= format_currency($subtotal) ?></span>
        </div>
        <div class="summary-line">
            <span class="label">Shipping:</span>
            <span class="value"><?= format_currency($shipping_cost) ?></span>
        </div>
        <div class="summary-line total-line">
            <span class="label">Grand Total:</span>
            <span class="value"><?= format_currency($grand_total) ?></span>
        </div>
    </section>

    <section class="checkout-form">
        <h3>Shipping & Payment Details</h3>
        <form method="POST" action="checkout.php" class="billing-form">
            <input type="hidden" name="action" value="place_order">

            <p>
                <label for="address">Shipping Address:</label>
                <input type="text" id="address" name="address" required placeholder="123 Main St">
            </p>
            <p>
                <label for="payment">Payment Method:</label>
                <select id="payment" name="payment" required>
                    <option value="">Select Method</option>
                    <option value="cod">Cash on Delivery (COD)</option>
                    <option value="visa">Visa (Dummy)</option>
                    <option value="mastercard">MasterCard (Dummy)</option>
                </select>
            </p>
            
            <p class="checkout-note">
                (Payment processing is simulated. Clicking "Place Order" finalizes the transaction and deducts stock.)
            </p>

            <button type="submit" class="place-order-button">Place Order - <?= format_currency($grand_total) ?></button>
        </form>
    </section>
    
</main>

<?php 
include 'footer.php'; 
?>