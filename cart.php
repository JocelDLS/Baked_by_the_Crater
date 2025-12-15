<?php
session_start();
// Include db.php for file structure, although the connection is unused
include 'db.php'; 

$page_title = "Your Shopping Cart";
$active_page = 'cart'; 

// --- Configuration ---
const CART_STORAGE_FILE = 'data/cart.xml';
const PRODUCT_CATALOG_FILE = 'data/products.xml'; 

// --- Cart Data Initialization ---
$user_id = $_SESSION['user_id'] ?? ''; 
$cart_items_data = [];
$total_cost = 0.00;
$error = '';
$message = '';

// FIX: Add format_currency function definition for local use and consistency
/**
 * Formats a number/string as currency (₱XX.XX).
 */
function format_currency($amount) {
    if (is_string($amount)) {
        $amount = str_replace(['$', '₱', ','], '', $amount); 
    }
    return '₱' . number_format((float)$amount, 2); 
}

// --- XML Utility Functions (Restored/Cleaned) ---
// ... (load_all_carts_xml, save_all_carts_xml, load_product_catalog_xml are unchanged)

/**
 * Loads all user carts from the XML file.
 * Returns: array [user_id => [product_id => quantity]]
 */
function load_all_carts_xml() {
    $all_carts = [];
    // Ensure the data directory exists and the file is present
    if (!file_exists(CART_STORAGE_FILE) || filesize(CART_STORAGE_FILE) == 0) {
        return $all_carts;
    }
    // Suppress errors with @ in case of corrupt XML
    $xml = @simplexml_load_file(CART_STORAGE_FILE);
    if ($xml === false) { return $all_carts; }

    foreach ($xml->user_cart as $user_cart) {
        $uid = (string)$user_cart['user_id'];
        $items = [];
        foreach ($user_cart->item as $item) {
            $items[(int)$item['id']] = (int)$item['qty'];
        }
        $all_carts[$uid] = $items;
    }
    return $all_carts;
}

/**
 * Saves all carts back to the XML file.
 * Returns: bool Success/Failure
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
                    $item->addAttribute('id', $pid);
                    $item->addAttribute('qty', $qty);
                }
            }
        }
    }
    return $xml->asXML(CART_STORAGE_FILE);
}

/**
 * Loads product details from the catalog XML file.
 * Returns: array [product_id => product_data]
 */
function load_product_catalog_xml() {
    $products = [];
    if (!file_exists(PRODUCT_CATALOG_FILE)) { return $products; }

    $xml = @simplexml_load_file(PRODUCT_CATALOG_FILE);
    if ($xml === false) { return $products; }
    
    foreach ($xml->product as $p) {
        $product_id = (string)$p['id']; 
        $products[$product_id] = [
            'name' => (string)$p->name,
            'price' => (float)$p->price,
            'image_url' => (string)$p->image_path, 
            'stock' => (int)$p->stock, // For potential stock check in cart
        ];
    }
    return $products;
}
// -----------------------------------------------------------------------------


// --- Cart Actions (Update and Remove) ---
$all_carts = load_all_carts_xml();
$user_cart = $all_carts[$user_id] ?? [];

// 1. Handle Item Removal (via GET request)
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['product_id'])) {
    $product_id = (int)$_GET['product_id'];
    if (isset($user_cart[$product_id])) {
        unset($user_cart[$product_id]);
        $all_carts[$user_id] = $user_cart;
        save_all_carts_xml($all_carts);
        $message = "Item removed from cart.";
        header('Location: cart.php?message=' . urlencode($message));
        exit;
    }
}

// 1b. Handle Clear Cart (via GET request)
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    $user_cart = [];
    $all_carts[$user_id] = $user_cart;
    save_all_carts_xml($all_carts);
    $message = "Cart cleared.";
    header('Location: cart.php?message=' . urlencode($message));
    exit;
}

// 2. Handle Quantity Update (via POST request)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $product_id => $quantity) {
        $product_id = (int)$product_id;
        $quantity = (int)$quantity;

        if ($quantity <= 0) {
            unset($user_cart[$product_id]);
        } elseif ($quantity > 0) {
            $user_cart[$product_id] = $quantity;
        }
    }
    $all_carts[$user_id] = $user_cart;
    save_all_carts_xml($all_carts);
    $message = "Cart quantities updated.";
    header('Location: cart.php?message=' . urlencode($message));
    exit;
}

// Retrieve message from URL after redirect
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}


// --- 3. Load All Products and Calculate Cart ---
$all_products = load_product_catalog_xml();

if (empty($user_id)) {
    $error = "You must be logged in to view your cart.";
} elseif (!file_exists(PRODUCT_CATALOG_FILE) || empty($all_products)) {
    $error = "Sorry, we're unable to load the product information at this time. Please try again later.";
}

if (empty($error) && !empty($user_cart)) {
    
    foreach ($user_cart as $product_id => $quantity) {
        
        if (isset($all_products[$product_id])) {
            $product = $all_products[$product_id];
            
            $subtotal = $product['price'] * $quantity;
            $total_cost += $subtotal;

            $cart_items_data[] = [
                'id' => $product_id,
                'name' => htmlspecialchars($product['name']),
                'price' => (float)$product['price'],
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'image_url' => htmlspecialchars($product['image_url'] ?? 'images/product_placeholder.png')
            ];
        } else {
            // Product in cart no longer exists in catalog: remove it from persistent cart
            // This is handled by the post/get handlers, no need to save again here.
        }
    }

    $shipping_cost = 10.00;
    $grand_total = $total_cost + $shipping_cost;

} else {
    $shipping_cost = 0.00;
    $grand_total = 0.00;
}


// --- 4. HTML Layout (Unchanged) ---
include 'header.php'; 
?>
<main>
    <section class="page-content">
        <div class="container cart-container">
            <h2 class="page-title"><?php echo $page_title; ?></h2>
            <p class="page-subtitle">Review your selections before checkout.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($cart_items_data)): ?>
                <div class="empty-cart-message">
                    <p>Your shopping cart is currently empty.</p>
                    <a href="shop.php" class="btn-primary">Continue Shopping</a>
                </div>
            <?php else: ?>
                
                <form action="cart.php" method="POST" class="cart-form">
                    <div class="cart-details">
                        <div class="cart-items-list">
                            <?php foreach ($cart_items_data as $item): ?>
                                <div class="cart-item">
                                    <div class="item-image" style="background-image: url('<?php echo $item['image_url']; ?>');"></div>
                                    <div class="item-info">
                                        <h4><?php echo $item['name']; ?></h4>
                                        <p class="item-price">Price: <?php echo format_currency($item['price']); ?></p> 
                                        
                                        <div class="item-quantity">
                                            <label for="qty_<?php echo $item['id']; ?>">Quantity:</label>
                                            <input type="number" 
                                                   name="qty[<?php echo $item['id']; ?>]" 
                                                   id="qty_<?php echo $item['id']; ?>" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="99" 
                                                   class="qty-input">
                                        </div>
                                    </div>
                                    
                                    <div class="item-subtotal">
                                        <p>Subtotal</p>
                                        <p class="subtotal-amount"><?php echo format_currency($item['subtotal']); ?></p> 
                                        <a href="cart.php?action=remove&product_id=<?php echo $item['id']; ?>" class="remove-link" title="Remove Item">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div> <div class="cart-actions-bottom">
                            <button type="submit" name="update_cart" class="btn-tertiary" style="width: auto;">Update Cart</button>
                            <a href="shop.php" class="btn-secondary">Continue Shopping</a>
                            <a href="cart.php?action=clear" class="btn-danger">Clear Cart</a>
                        </div>
                    </div> </form>

                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-line">
                        <span>Subtotal:</span>
                        <span class="value"><?php echo format_currency($total_cost); ?></span> 
                    </div>
                    
                    <div class="summary-line">
                        <span>Shipping:</span>
                        <span class="value"><?php echo format_currency($shipping_cost); ?></span> 
                    </div>
                    
                    <div class="summary-line total-line">
                        <span>Grand Total:</span>
                        <span class="value grand-total-value"><?php echo format_currency($grand_total); ?></span> 
                    </div>
                    
                    <a href="checkout.php" class="btn-primary">Proceed to Checkout</a>
                    
                    <p class="security-note">Secure checkout powered by SSL.</p>
                </div> <?php endif; ?>

        </div> </section>
</main>
<script src="script.js"></script>
<?php 
include 'footer.php'; 
?>