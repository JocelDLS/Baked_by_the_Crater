<?php 
// 1. FIX: session_start() MUST be the very first executable line.
session_start();

// === CRITICAL: INCLUDE DB CONNECTION FIRST ===
// NOTE: Ensure 'db.php' correctly establishes the $con MySQLi object 
// and that your MySQL server (e.g., in XAMPP) is running.
include 'db.php'; 
// ===============================================

// Initialize user_id
$user_id = '';

// Check for existing messages
$error = '';
$success = '';

// Check for redirects from login.php or google_callback.php
if (isset($_GET['logout'])) {
    $success = 'You have been successfully logged out.';
}

// --- START: Newsletter Subscription Message Handling ---
if (isset($_GET['subscription_success'])) {
    $success = 'Thank you for subscribing! Check your inbox for your 10% off coupon.';
}
if (isset($_GET['subscription_duplicate'])) {
    $success = 'You are already subscribed to our newsletter. Thank you!';
}
if (isset($_GET['subscription_error'])) {
    $error = 'Subscription failed. Please check your email and try again.';
}
// --- END: Newsletter Subscription Message Handling ---


if (isset($_SESSION['user_id'])) {
    // 1. Get user ID from active session
    $user_id = $_SESSION['user_id'];

} elseif (isset($_COOKIE['user_id'])) {
    // 2. Fallback: Get user ID from cookie
    $user_id = $_COOKIE['user_id'];

    // Validate user from database
    // Check if $con is a valid object before calling prepare()
    if (!empty($user_id) && isset($con) && $con && $stmt = $con->prepare("SELECT user_id FROM users WHERE user_id = ?")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $user_id = $user['user_id'];
        } else {
            $user_id = ''; // Invalid cookie
        }
    }
}

// --- UTILITY FUNCTION (Required for the product loop to work) ---
if (!function_exists('format_currency')) {
    /**
     * Formats a number/string as currency (₱XX.XX).
     */
    function format_currency($amount) {
        return '₱' . number_format((float)$amount, 2); 
    }
}

// --- CONFIGURATION CONSTANT ---
const DEFAULT_IMAGE = 'assets/default_product.jpg';
// ----------------------------------------------------------------


// ---------------- Page Setup Before Including Header ----------------
$page_title = 'Baked by the Crater | Home';
$active_page = 'homepage';

// NOTE: header.php must NOT contain session_start().
include 'header.php';

// ---------------- CHAT HISTORY LOAD ----------------

$chat_history_json = '[]';

// Load chat history from `full_texts` (preferred). Fallback to legacy `chats` table if necessary.
if (!empty($user_id) && isset($con) && $con) {
    $chat_array = [];

    // Preferred: full_texts table used by admin and save_chat.php
    if ($stmt = $con->prepare("SELECT message_text, sender_type, message_type FROM full_texts WHERE user_id = ? ORDER BY timestamp ASC")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Map sender_type to frontend message type used by JS ('sent' for customer, 'received' for admin)
            $type = (isset($row['sender_type']) && $row['sender_type'] === 'admin') ? 'received' : 'sent';
            $chat_array[] = ['type' => $type, 'text' => $row['message_text']];
        }
        $stmt->close();
    } else {
        // Legacy fallback: read from `chats` if it exists
        if ($stmt = $con->prepare("SELECT message_text, message_type FROM chats WHERE user_id = ? ORDER BY timestamp ASC")) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $chat_array[] = ['type' => $row['message_type'], 'text' => $row['message_text']];
            }
            $stmt->close();
        }
    }

    $chat_history_json = json_encode($chat_array);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="script.js"></script>
    <link rel="stylesheet" href="style.css">

    <script>
        // Pass the database content and user ID to JavaScript
        const CHAT_HISTORY = <?php echo $chat_history_json; ?>;
        const CURRENT_USER_ID = <?php echo json_encode($user_id); ?>;
    </script>
</head>

<body>
    <?php if ($error): ?>
        <div class="alert alert-error container">
            <?php echo htmlspecialchars($error); ?>
            <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success container">
            <?php echo htmlspecialchars($success); ?>
            <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
        </div>
    <?php endif; ?>

    <section class="hero-section">
        <div class="container hero-content">
            <div class="hero-text">
                <h1>Artisan Breads & Pastries</h1>
                <p>Experience the warmth and flavor of freshly baked goods, crafted with passion right here in the Crater.</p>
                <div class="hero-ctas">
                    <a href="shop.php" class="btn btn-primary">Shop Now</a>
                    <a href="bestsellers.php" class="btn-tertiary">View Best Sellers</a> 
                </div>
            </div>
        </div>
    </section>

    <section class="featured-products container">
        <h2>Our Signature Bakes</h2>
        <div class="product-grid">

            <?php
            // NOTE: This section uses the product XML. Ensure 'data/products.xml' exists.
            $xml_file = 'data/products.xml';

            if (file_exists($xml_file)) {
                $xml = simplexml_load_file($xml_file);

                // Use a counter to limit the displayed products (MAX 6)
                $display_count = 0; 
                foreach ($xml->product as $product) {
                    // FIX: Only show a maximum of 6 featured items
                    if ($display_count >= 6) break; 
                    
                    // FIX: Kunin ang totoong product ID mula sa XML attribute
                    $id = (string)$product['id']; 
                    
                    $name = htmlspecialchars((string)$product->name);
                    $price = htmlspecialchars((string)$product->price);
                    
                    // FIX: Safe check for <image_path> (consistent with other files)
                    $image_src = (isset($product->image_path) && (string)$product->image_path !== '') 
                                 ? htmlspecialchars((string)$product->image_path) 
                                 : DEFAULT_IMAGE;
                    
                    // FIX: Link points to the add_to_cart script with the actual product ID
                    $link = 'add_to_cart.php?id=' . $id; 

                    echo '<div class="product-card">';
                    echo '<div class="product-image" style="background-image: url(\''.$image_src.'\');">';
                    echo '<img src="'.$image_src.'" alt="'.htmlspecialchars($name).'">';
                    echo '<span class="price-badge">' . format_currency($price) . '</span>';
                    echo '</div>';
                    echo '<div class="product-details">';
                    echo '<h3>' . $name . '</h3>';
                    echo '<div class="price-action">';
                    echo '<a href="' . $link . '" class="btn-small">Add to Cart</a>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    
                    $display_count++; // Increment the display counter
                }

            } else {
                echo '<p style="text-align: center; color: var(--accent-soft);">Product data file ('.$xml_file.') not found. Please create it.</p>';
            }
            ?>

        </div>
    </section>

    <section class="usp-section container">
        <div class="usp-grid">
            <div class="usp-item">
                <i class="fas fa-seedling"></i>
                <h3>Natural Ingredients</h3>
                <p>We use only locally sourced, natural ingredients with no artificial preservatives.</p>
            </div>

            <div class="usp-item">
                <i class="fas fa-hand-holding-box"></i>
                <h3>Hand-Crafted Daily</h3>
                <p>Every loaf and pastry is mixed, shaped, and baked by hand with passion.</p>
            </div>

            <div class="usp-item">
                <i class="fas fa-truck-fast"></i>
                <h3>Fresh Delivery</h3>
                <p>Delivered fresh to your door within hours of coming out of the oven.</p>
            </div>
        </div>
    </section>

    <section class="testimonials container">
        <h2>What Our Customers Say</h2>
        <div class="testimonial-box">
            <p>"The sourdough here is the best I've ever had. Perfectly tangy crust and airy interior. It feels like a real treat every time!"</p>
            <footer>— Jenna P., Local Food Critic</footer>
        </div>
    </section>

    <section class="newsletter-signup">
        <div class="container">
            <h3>Get Exclusive Deals!</h3>
            <p>Join our newsletter for 10% off your first order and updates on our seasonal bakes.</p>
            <form class="newsletter-form" method="POST" action="subscribe.php">
                <input type="email" name="email" placeholder="Enter your email address" required>
                <button type="submit" class="btn-primary">Subscribe</button>
            </form>
        </div>
    </section>

    <div id="chat-plugin-container">

        <button id="chat-launcher" onclick="toggleChatWindow()">
            <i class="far fa-comment-dots"></i>
        </button>

        <div id="chat-window" class="hidden">
            <div class="chat-header">
                <h3>Baked by the Crater Support</h3>
                <button onclick="toggleChatWindow()">&times;</button>
            </div>
            
            <div class="chat-body" id="chat-messages"></div>

            <div class="chat-input">
                <input type="text" id="chat-input-field" placeholder="Type your message..." onkeydown="handleChatInput(event)">
                <button onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>