<?php
// Shop/Menu Page (shop.php)

session_start();

// === CRITICAL: INCLUDE DB CONNECTION FIRST ===
include 'db.php'; // Includes the $con MySQLi object
// ===============================================

// ---------------- Session and User ID Logic ----------------
$user_id = '';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_COOKIE['user_id'])) {
    // Basic cookie logic (as seen in index.php)
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
    $success = 'Item added to your cart successfully!';
}
// Handle error messages passed by add_to_cart.php
if (isset($_GET['error']) && $_GET['error'] == 'cart_error' && isset($_GET['message'])) {
    $error = htmlspecialchars($_GET['message']);
} elseif (isset($_GET['error']) && $_GET['error'] == 'cart_error') {
    $error = 'Error adding item to cart. Please try again.';
}


// --- Configuration ---
const PRODUCT_CATALOG_FILE = 'data/products.xml';
const DEFAULT_IMAGE = 'assets/default_product.jpg'; // Placeholder for missing images

$page_title = "Our Baked Goods";
$active_page = 'shop'; 

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
        
        // ** (Line 79) FIX FOR PARSE ERROR: Calculate image_src outside the array definition
        // This checks if the node exists AND if the string value is not empty.
        $image_src = (isset($p->image_path) && (string)$p->image_path !== '') 
                     ? (string)$p->image_path 
                     : DEFAULT_IMAGE;
                     
        $products[$id] = [ // (Line 84) Start of array definition
            'id' => $id,
            'name' => (string)$p->name,
            'price' => (float)$p->price,
            'stock' => (int)$p->stock, 
            'category' => (string)$p->category,
            'description' => (string)$p->description ?? 'No description available.',
            'image_src' => $image_src, // Use the safely determined image source
        ];
    }
    return $products;
}

/**
 * Simple currency formatter function. (Defined here for self-containment)
 */
function format_currency($amount) {
    return '₱' . number_format((float)$amount, 2); 
}


// --- Main Logic ---

$products_data = load_product_catalog_xml();
$all_categories = [];
$filtered_products = $products_data;
$search_query = '';

// Group products by category and filter by search query
if (!empty($products_data)) {
    foreach ($products_data as $product) {
        $category = trim($product['category']);
        if (!empty($category)) {
            $all_categories[$category] = $category;
        }
    }
    ksort($all_categories); // Sort categories alphabetically

    // Apply Search Filter if query is present
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_query = strtolower(trim($_GET['search']));
        $filtered_products = array_filter($products_data, function($product) use ($search_query) {
            $name = strtolower($product['name']);
            $description = strtolower($product['description']);
            // Check if search query is found in the product name OR description
            return (strpos($name, $search_query) !== false) || (strpos( (string) $description, $search_query) !== false);
        });
    }

    // Group filtered products by category for display
    $categorized_products = [];
    $products_found = false;
    foreach ($filtered_products as $product) {
        $category = trim($product['category']);
        $products_found = true;
        if (!empty($category)) {
            $categorized_products[$category][] = $product;
        } else {
            $categorized_products['Uncategorized'][] = $product;
        }
    }
}


// --- HTML Output ---
include 'header.php'; // Assumes header includes basic layout setup
?>

<main class="shop-content">
    
    <div class="shop-header">
        <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
        <p class="page-subtitle">Browse our selection of artisanal breads, pastries, and seasonal specials.</p>
        <hr>

        <form method="GET" action="shop.php" class="search-form">
            <input type="text" name="search" placeholder="Search for a pastry..." value="<?php echo htmlspecialchars($search_query); ?>">
            
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php 
    if (!empty($categorized_products)) {
        foreach ($categorized_products as $category => $category_products) {
            if (!empty($category_products)) {
            ?>
                <h2><?php echo htmlspecialchars($category); ?></h2>
                <div class="product-grid shop-page-grid">
                    <?php 
                    foreach ($category_products as $product) {
                        $link = 'add_to_cart.php?id=' . $product['id']; 
                        $is_in_stock = $product['stock'] > 0; // Check stock
                    ?>
                        <div class="product-card <?php echo $is_in_stock ? '' : 'out-of-stock'; ?>">
                            <div class="product-image" style="background-image: url('<?php echo htmlspecialchars($product['image_src']); ?>');">
                                <img src="<?php echo htmlspecialchars($product['image_src']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <span class="price-badge" aria-hidden="true"><?php echo format_currency($product['price']); ?></span>
                            </div>
                            <div class="product-details">
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
                    <?php 
                    } 
                    ?>
                </div>
            <?php
            }
        } 
        if (!$products_found && !empty($search_query)) {
            echo '<div class="alert alert-info">No products match your search query. Try a different keyword.</div>';
        }
    } else {
        echo '<div class="alert alert-warning">No products available in the catalog.</div>';
    }
    ?>

</main>

<?php include 'footer.php'; ?>