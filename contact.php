<?php
// Contact Us Page (contact.php)

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
$form_data = []; // Initialize array for pre-filling form

// Check for redirects from send_message.php for success or error messages
if (isset($_SESSION['form_success'])) {
    $success = $_SESSION['form_success'];
    unset($_SESSION['form_success']);
}

if (isset($_SESSION['form_error'])) {
    $error = $_SESSION['form_error'];
    unset($_SESSION['form_error']);
    // If there's an error, retrieve and unset form data to pre-fill the form
    if (isset($_SESSION['form_data'])) {
        $form_data = $_SESSION['form_data'];
        unset($_SESSION['form_data']);
    }
}

// ---------------- Page Setup Before Including Header ----------------
$page_title = 'Baked by the Crater | Contact';
$active_page = 'contact'; // Used in header.php for navigation styling


// NOTE: You must have 'db.php', 'header.php', and 'footer.php' files available for this to work.
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
            <button class="close-btn" onclick="this.parentElement.style.display='none';">×</button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success container">
            <?php echo $success; // $success is already sanitized in send_message.php, but if it contains formatted HTML (like bold tags) we skip htmlspecialchars here ?>
            <button class="close-btn" onclick="this.parentElement.style.display='none';">×</button>
        </div>
    <?php endif; ?>

    <main class="container page-content">
        <h1 class="page-title">Contact & Location 📍</h1>
        <p class="page-subtitle">We'd love to hear from you! Visit our location or send us a message.</p>
        
        <hr>

        <section class="contact-grid">
            
            <div class="contact-info">
                <h2>Our Details</h2>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h4>Address</h4>
                        <p>Poblacion Barangay 6, Talisay, Batangas</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <h4>Phone</h4>
                        <p>0917 638 2945</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>Email</h4>
                        <p>bakedbythecrater@gmail.com</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h4>Hours</h4>
                        <p>Mon - Fri: 7:00 AM - 4:00 PM</p>
                        <p>Sat - Sun: 8:00 AM - 2:00 PM</p>
                    </div>
                </div>
            </div>

            <div class="contact-form-container">
                <h2>Send Us a Message</h2>
                <form class="contact-form" action="send_message.php" method="POST">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Your Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="5" required><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Send Message</button>
                </form>
            </div>
        </section>

        <section class="map-section">
            <h2>Find Us Here</h2>
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d5589.125741095147!2d121.0044352068058!3d14.02409278186431!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd73e9a460019b%3A0x4e5ce14a2caa0756!2sBaked%20By%20The%20Crater!5e1!3m2!1sen!2sph!4v1765454212961!5m2!1sen!2sph" 
                    width="100%" 
                    height="450" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
                
            </div>  
        </section>

<?php 
include 'footer.php'; // Assumes this closes the HTML document
?>
</html>