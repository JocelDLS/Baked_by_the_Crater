<?php
// About Us Page (about.php)

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

// Check for cart actions (for consistency, though less likely on this page)
if (isset($_GET['success']) && $_GET['success'] == 'added_to_cart') {
    $success = 'Item added to your cart successfully!';
}
if (isset($_GET['error']) && $_GET['error'] == 'cart_error') {
    $error = 'Error adding item to cart. Please try again.';
}

// ---------------- Social Share Variables ----------------
// NOTE: You must replace 'YOUR_SITE_URL' with your actual website address
$page_url = urlencode("http://localhost/sia/about.php"); 
$share_text = urlencode("Discover the incredible story and delicious artisan breads from Baked by the Crater! #ArtisanBreads #CraterBakery");
$share_title = urlencode("Baked by the Crater: Our Story");

// ---------------- Page Setup Before Including Header ----------------
$page_title = 'Baked by the Crater | About Us';
$active_page = 'about'; // Used in header.php for navigation styling



include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
    <script src="script.js"></script>
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

    <main class="container page-content">
        <h1 class="page-title">Our Story: Baked by Passion</h1>
        <p class="page-subtitle">From a humble start in a small mountain town, our passion for natural ingredients grew into the bakery you love today.</p>
        
        <hr>

        <section class="about-section story-mission-grid">
            <div class="about-image">
                <img src="images/about-bakers.jpg" alt="A baker kneading dough" class="responsive-img">
            </div>
            
            <div class="about-text">
                <h2>The Crater's Warmth</h2>
                <p><strong>Baked by the Crater</strong> started with a simple belief: the best bread comes from the simplest, most honest ingredients. Our founder, Marialyn, moved to the remote town of Vista del Fuego (The Crater) to escape the city rush and focus on the ancient art of sourdough. Using locally milled flours and natural volcanic spring water, she perfected our signature 24-hour fermentation process.</p>
                <p>Every loaf we bake is a testament to patience and craft, delivering a taste that is both rustic and refined. We are proud to share the warmth of The Crater with your table.</p>
                
                <h3>Our Mission</h3>
                <ul>
                    <li>**Quality:** Use only natural, locally-sourced ingredients.</li>
                    <li>**Craft:** Honor traditional baking techniques over mass production.</li>
                    <li>**Community:** Create a welcoming space that supports local farmers.</li>
                </ul>
            </div>
        </section>

        <section class="team-section">
            <h2>Meet the Bakers</h2>
            <p>Our small team is dedicated to bringing you the perfect bite every single day.</p>
            <div class="team-grid">
                <div class="team-member">
                    <img src="images/team-marialyn.jpg" alt="Marialyn, Founder" class="team-photo">
                    <h4>Marialyn (Founder)</h4>
                    <p>The visionary behind the sourdough culture and the heart of the operation.</p>
                </div>
                <div class="team-member">
                    <img src="images/team-kristian.jpg" alt="Kristian, Pastry Chef" class="team-photo">
                    <h4>Kristian (Head Baker)</h4>
                    <p>Our resident expert in delicate French pastries, croissants, and babka.</p>
                </div>
            </div>
        </section>

        <section class="social-share-section">
            <h3>Share Our Story!</h3>
            <p>Love our bread? Spread the word about Baked by the Crater with your friends.</p>
            <div class="social-share-buttons">
                
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $page_url; ?>" 
                   target="_blank" class="share-btn facebook-btn">
                    <i class="fab fa-facebook-f"></i> Share on Facebook
                </a>
                
                <a href="https://twitter.com/intent/tweet?text=<?php echo $share_text; ?>&url=<?php echo $page_url; ?>" 
                   target="_blank" class="share-btn twitter-btn">
                    <i class="fab fa-twitter"></i> Tweet
                </a>
                
                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $page_url; ?>&title=<?php echo $share_title; ?>&summary=<?php echo $share_text; ?>&source=Baked%20by%20the%20Crater" 
                   target="_blank" class="share-btn linkedin-btn">
                    <i class="fab fa-linkedin-in"></i> Share on LinkedIn
                </a>
                
                <a href="mailto:?subject=<?php echo $share_title; ?>&body=Check out the amazing local bakery, Baked by the Crater: <?php echo $page_url; ?>" 
                   class="share-btn email-btn">
                    <i class="fas fa-envelope"></i> Email
                </a>

            </div>
        </section>

    </main>

    <?php include 'footer.php'; ?>
</body>
</html>