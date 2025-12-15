<?php
// Best Sellers Page (bestsellers.php)

session_start();

// === CRITICAL: INCLUDE DB CONNECTION FIRST ===
include 'db.php'; // Includes the $con MySQLi object
// ===============================================

// ---------------- Session and User ID Logic ----------------
$user_id = '';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_COOKIE['user_id'])) {
    // Basic cookie logic
    $user_id = $_COOKIE['user_id'];
    if (!empty($user_id) && isset($con) && $stmt = $con->prepare("SELECT user_id FROM users WHERE user_id = ?")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()) {
            $_SESSION['user_id'] = $user_id;
        } else {
            $user_id = ''; // Invalid cookie
        }
        $stmt->close();
    }
}
// -----------------------------------------------------------

// Initialize error and success messages
$error = '';
$success = ''; 

// Check for cart actions
if (isset($_GET['success']) && $_GET['success'] == 'added_to_cart') {
    $success = 'Best Seller added to your cart successfully!';
}
// Handle error messages passed by add_to_cart.php
if (isset($_GET['error']) && $_GET['error'] == 'cart_error' && isset($_GET['message'])) {
    $error = htmlspecialchars($_GET['message']);
} elseif (isset($_GET['error']) && $_GET['error'] == 'cart_error') {
    $error = 'Error adding item to cart. Please try again.';
}

// --- Configuration ---
const PRODUCT_CATALOG_FILE = 'data/products.xml';
const ORDER_STORAGE_FILE = 'data/orders.xml'; 
const DEFAULT_IMAGE = 'assets/default_product.jpg'; 

$page_title = "Our Best Sellers";
$active_page = 'bestsellers'; 


// --- XML Utility Functions ---

/**
 * Loads product details from the catalog XML file, indexed by their ID attribute.
 * FIX: Reads product ID attribute, stock, and safely retrieves image path.
 * Returns: array [product_id => product_data]
 */
function load_product_catalog_xml() {
    $products = [];
    if (!file_exists(PRODUCT_CATALOG_FILE)) { return $products; }

    $xml = @simplexml_load_file(PRODUCT_CATALOG_FILE);
    if ($xml === false) { return $products; }
    
    foreach ($xml->product as $p) {
        $id = (string)$p['id']; 
        
        // ** FIX FOR PARSE ERROR (Line 79/80 area): 
        // Calculate image_src safely and OUTSIDE the array definition.
        // This checks if the node exists AND if the string value is not empty.
        $image_src = (isset($p->image_path) && (string)$p->image_path !== '') 
                     ? (string)$p->image_path 
                     : DEFAULT_IMAGE;
                     
        $products[$id] = [
            'id' => $id,
            'name' => (string)$p->name,
            'price' => (float)$p->price,
            'stock' => (int)$p->stock, 
            'category' => (string)$p->category,
            'description' => (string)$p->description ?? 'No description available.',
            'image_src' => $image_src, // Use the safely determined image source variable
            'total_sales' => 0 
        ];
    }
    return $products;
}

/**
 * Calculates total sales quantity for each product from orders.xml.
 * @param array $products_data The array of products to update.
 * @return array The updated products array with 'total_sales' populated.
 */
function calculate_product_sales(array $products_data) {
    if (!file_exists(ORDER_STORAGE_FILE) || filesize(ORDER_STORAGE_FILE) == 0) {
        return $products_data;
    }
    
    $xml = @simplexml_load_file(ORDER_STORAGE_FILE);
    if ($xml === false) { return $products_data; }

    foreach ($xml->order as $order) {
        foreach ($order->items->item as $item) {
            // Use the product_id attribute from the order item (e.g., '101')
            $product_id = (string)$item['product_id']; 
            $quantity = (int)$item->quantity;
            
            if (isset($products_data[$product_id])) {
                // Tally sales to the corresponding product
                $products_data[$product_id]['total_sales'] += $quantity;
            } 
        }
    }
    return $products_data;
}


/**
 * Simple currency formatter function. (Defined here for self-containment)
 */
function format_currency($amount) {
    return '₱' . number_format((float)$amount, 2); 
}


// --- Main Logic ---

// 1. Load products
$products_data = load_product_catalog_xml();

// 2. Calculate sales (NEW STEP - Automatic Best Sellers)
$products_data = calculate_product_sales($products_data);

// 3. Sort products by total_sales descending (highest sales first)
if (!empty($products_data)) {
    uasort($products_data, function($a, $b) {
        // Compare total_sales first, then fallback to product ID if sales are equal
        if ($b['total_sales'] === $a['total_sales']) {
            return $a['id'] <=> $b['id']; // Smallest ID wins tie-breaker
        }
        return $b['total_sales'] <=> $a['total_sales']; 
    });
}

// 4. Get the Top 3 Best Sellers (Must have at least 1 sale)
$best_sellers = array_filter(array_slice($products_data, 0, 3, true), function($product) {
    return $product['total_sales'] > 0;
});

$products_found = !empty($best_sellers);


// --- HTML Output ---
include 'header.php'; 
?>

<main class="bestsellers-content">
    
    <div class="bestsellers-header">
        <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
        <p class="page-subtitle">Our top-selling products, automatically calculated from all confirmed orders!</p>
        <hr>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($products_found): ?>

        <div class="product-grid bestsellers-page-grid">
            <?php 
            // Track the actual sales rank
            $rank_counter = 1;
            foreach ($best_sellers as $product): 
                $link = 'add_to_cart.php?id=' . $product['id']; 
                $is_in_stock = $product['stock'] > 0;
            ?>
                <div class="product-card <?php echo $is_in_stock ? '' : 'out-of-stock'; ?>">
                    <div class="product-image" style="background-image: url('<?php echo htmlspecialchars($product['image_src']); ?>');">
                        <img src="<?php echo htmlspecialchars($product['image_src']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <span class="price-badge" aria-hidden="true"><?php echo format_currency($product['price']); ?></span>
                    </div>
                    <div class="product-details">
                        <span class="sales-rank">#<?php echo $rank_counter++; ?> Best Seller! (Sold: <?php echo $product['total_sales']; ?>)</span>
                        
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="price-action">
                            <p class="stock-info">
                                <?php if ($is_in_stock): ?>
                                    Stock: <?php echo $product['stock']; ?> 
                                <?php else: ?>
                                    <span style="color: red; font-weight: bold;">Out of Stock</span>
                                <?php endif; ?>
                            </p>

                            <?php if ($is_in_stock): ?>
                                <a href="<?php echo $link; ?>" class="btn-small">Add to Cart</a>
                            <?php else: ?>
                                <button class="btn-small btn-disabled" disabled>Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
         <p class="text-center" style="padding: 50px; color: var(--text-muted);">
            No Best Sellers found yet. Mag-place muna ng order sa system para ma-calculate ang sales!
        </p>
    <?php endif; ?>
    
</main>

<?php include 'footer.php'; ?>