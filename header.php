<?php

// --- Configuration for Dynamic Page Title and Active Link ---
// Ensure db.php is included so format_currency and symbol are available across pages
require_once __DIR__ . '/db.php';

// 1. IMPROVEMENT: Use a default page title if not set, making sure the variable exists.
if (!isset($page_title) || empty($page_title)) {
    $page_title = 'Baked by the Crater | Fresh Bread & Pastries';
}

// 2. FIX: Ensure $active_page is set. Get the filename without .php or .html extension.
if (!isset($active_page) || empty($active_page)) {
    // Get the base filename (e.g., 'index.php' or 'shop.php')
    $current_file = basename($_SERVER['PHP_SELF']);
    
    // Remove both '.php' and '.html' extensions for robust matching against nav links
    $active_page = str_replace(array('.php', '.html'), '', $current_file);
}

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <img src="images/BakedByTheCrater.png" alt="Baked by the Crater Logo" class="site-logo">
                <a href="index.php">Baked by the Crater</a>
            </div>

            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="<?php echo ($active_page == 'index') ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="shop.php" class="<?php echo ($active_page == 'shop') ? 'active' : ''; ?>">Shop/Menu</a></li>
                    <li><a href="bestsellers.php" class="<?php echo ($active_page == 'bestsellers') ? 'active' : ''; ?>">Best Sellers</a></li>
                    <li><a href="about.php" class="<?php echo ($active_page == 'about') ? 'active' : ''; ?>">About Us</a></li>
                    <li><a href="contact.php" class="<?php echo ($active_page == 'contact') ? 'active' : ''; ?>">Contact</a></li>
                </ul>
            </nav>

            <div class="user-actions">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <a href="profile.php" class="btn-secondary">Profile</a>
                    <a href="logout.php" class="btn-secondary">Logout</a>
                    
                    <a href="cart.php" class="nav-link cart-icon">
                        <i class="fas fa-shopping-cart"></i> Cart
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-secondary">Login</a>
                    <a href="register.php" class="btn-secondary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>