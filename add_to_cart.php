<?php
session_start();
// Include db.php to maintain session/user_id consistency with other files.
include 'db.php'; 

// --- Configuration ---
// File where ALL users' cart data is persisted (READ/WRITE)
const CART_STORAGE_FILE = 'data/cart.xml';
// File where product details (name, price, stock) are stored (READ-ONLY)
const PRODUCT_CATALOG_FILE = 'data/products.xml';

// Get the page the user came from for redirection
$referrer = $_SERVER['HTTP_REFERER'] ?? 'shop.php';
$error = '';

// --- 1. User Authentication Check ---
$user_id = $_SESSION['user_id'] ?? '';
if (empty($user_id)) {
    // Redirect unauthenticated users to login
    header("Location: login.php?redirect=" . urlencode('shop.php'));
    exit;
}

// --- 2. Get and Validate Product ID ---
// FIX: Use the ID as a string, as it is stored as an attribute (e.g., '101') in XML
$product_id = trim($_GET['id'] ?? '');

if (empty($product_id) || !is_numeric($product_id)) {
    $error = "Invalid product ID.";
    header("Location: " . $referrer . "?error=cart_error&message=" . urlencode($error));
    exit;
}


// --- 3. XML Utility Functions for Cart Persistence and Product Validation ---

/**
 * Loads all users' carts from the XML storage file into a PHP array structure.
 * FIX: Ensures product IDs are read as strings (matching XML attributes).
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
            // FIX: Load product ID as string
            $items[(string)$item['id']] = (int)$item['qty'];
        }
        $all_carts[$uid] = $items;
    }
    return $all_carts;
}

/**
 * Saves the master cart array back to the XML file.
 * FIX: Ensures product IDs are saved as strings (matching XML attributes).
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
                    // FIX: Save product ID as string
                    $item->addAttribute('id', (string)$pid); 
                    $item->addAttribute('qty', $qty);
                }
            }
        }
    }
    return $xml->asXML(CART_STORAGE_FILE);
}

/**
 * Validates if the product exists in the catalog, fetches its data, and includes stock level.
 * @param string $product_id The ID of the product (e.g., '101').
 * @return array|false The product details (including 'stock') if valid, false otherwise.
 */
function validate_product_availability(string $product_id) {
    if (!file_exists(PRODUCT_CATALOG_FILE)) {
        return false;
    }
    $xml = @simplexml_load_file(PRODUCT_CATALOG_FILE);
    if ($xml === false) {
        return false;
    }
    
    // FIX: Use XPath to search for the product by its ID attribute
    $result = $xml->xpath("//product[@id='{$product_id}']");

    if (empty($result)) {
        return false; // Product ID not found
    }
    
    $product_node = $result[0];
    
    // Return key details including the stock level
    return [
        'id' => $product_id,
        'name' => (string)$product_node->name,
        'price' => (float)$product_node->price,
        // Assuming <stock> tag exists in products.xml
        'stock' => (int)$product_node->stock, 
    ];
}


// --- 4. Load Product Data and Check Stock ---

$product_data = validate_product_availability($product_id);

if ($product_data === false) {
    $error = "Product not found in catalog.";
    header("Location: " . $referrer . "?error=cart_error&message=" . urlencode($error));
    exit;
}

// Load existing carts from cart.xml
$all_carts = load_all_carts_xml();

// Get the current user's cart items, initialize if empty
$user_cart = $all_carts[$user_id] ?? [];

// Determine current quantity in cart
$user_cart_current_qty = $user_cart[$product_id] ?? 0;
$new_quantity = $user_cart_current_qty + 1;
$available_stock = $product_data['stock'];

// NEW: Stock Check Logic
if ($new_quantity > $available_stock) {
    $error = htmlspecialchars($product_data['name']) . " is currently out of stock or you have reached the maximum available quantity (" . $available_stock . ").";
    header("Location: " . $referrer . "?error=cart_error&message=" . urlencode($error));
    exit;
}

// --- 5. Modify and Save Cart ---

// Add the item or increment quantity (we know it's safe now)
$user_cart[$product_id] = $new_quantity;

// Update the master cart array with the modified user cart
$all_carts[$user_id] = $user_cart;

// Save the updated cart data back to XML
if (save_all_carts_xml($all_carts)) {
    // Success, redirect back to the page the user came from
    header("Location: " . $referrer . "?success=added_to_cart");
    exit;
} else {
    $error = "Failed to save cart data. Please try again later.";
    header("Location: " . $referrer . "?error=cart_error&message=" . urlencode($error));
    exit;
}