<?php
// forgot_password.php
session_start();

// --- START: Composer Autoloading and Dotenv Setup (FIX) ---
// This single line loads all necessary classes (Dotenv and PHPMailer)
require 'vendor/autoload.php'; // *** FIX: Corrected path for consistency ***

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
// --- END: Composer Autoloading and Dotenv Setup (FIX) ---

// Database Connection (Loads $con variable)
require_once 'db.php';
global $con;

// Configuration
define('SITE_URL', 'http://localhost/sia');
define('RESET_PASSWORD_PAGE', SITE_URL . '/reset_password.php');

$error = '';
$success = '';

// Helper function to generate a 6-digit numeric code
function generateCode($length = 6) {
    $min = 10**($length - 1);
    $max = 10**$length - 1;
    return str_pad(random_int($min, $max), $length, '0', STR_PAD_LEFT);
}

// Helper function to send email
function sendResetCodeEmail($email, $code) {
    $mail = new PHPMailer(true);

    // --- UPDATED CREDENTIALS/SETTINGS (Use App Password for actual value) ---
    $mailHost = 'smtp.gmail.com'; // Mula sa iyong .env file
    $mailUsername = 'bakedbythecrater@gmail.com'; // Mula sa iyong .env file
    $mailPassword = 'prdg wdwa ejbe muan'; // !!! REPLACE with your actual App Password for testing !!!
    // GMAIL FIX: Dapat ang MAIL_FROM_ADDRESS ay kapareho ng MAIL_USERNAME
    $mailFromAddress = 'no-reply@craterbakery.com'; 
    // ------------------------------------------------------------------

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $mailHost;
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $mail->Password = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Recipients
        $mail->setFrom($mailFromAddress, 'Baked by the Crater');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Code for Baked by the Crater';
        $mail->Body    = "
            <h2>Password Reset Code</h2>
            <p>You requested a password reset. Your 6-digit verification code is:</p>
            <p style='font-size: 20px; font-weight: bold;'>{$code}</p>
            <p>This code will expire in 1 hour.</p>
            <p>If you did not request this, please ignore this email.</p>";
        
        $mail->AltBody = "Your password reset code is: {$code}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer failed for {$email}: {$mail->ErrorInfo}");
        return false;
    }
}


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // 1. Find the user
        $stmt_user = mysqli_prepare($con, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt_user, "s", $email);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        $user_data = mysqli_fetch_assoc($result_user);
        mysqli_stmt_close($stmt_user);

        if (!$user_data) {
            $error = 'No account found with that email address.';
        } else {
            $user_id = $user_data['id'];
            $code = generateCode();
            $token_expires_at = date('Y-m-d H:i:s', time() + 3600); // Expires in 1 hour

            // 2. Delete any old, unused reset tokens for this user
            $stmt_delete = mysqli_prepare($con, "DELETE FROM user_tokens WHERE user_id = ? AND type = 'PASSWORD_RESET'");
            mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);

            // 3. Insert new reset token
            $sql_token = "INSERT INTO user_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'PASSWORD_RESET', ?)";
            $stmt_token = mysqli_prepare($con, $sql_token);
            mysqli_stmt_bind_param($stmt_token, "iss", $user_id, $code, $token_expires_at);
            $insert_token_success = mysqli_stmt_execute($stmt_token);
            mysqli_stmt_close($stmt_token);

            if ($insert_token_success) {
                // 4. Send Email
                if (sendResetCodeEmail($email, $code)) {
                    // *** FIX: Redirect directly to reset_password.php to prompt for code ***
                    header('Location: reset_password.php?email=' . urlencode($email));
                    exit;
                } else {
                    $error = 'Failed to send the reset code email. Please try again or contact support.';
                }
            } else {
                $error = 'Failed to generate reset token. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Baked by the Crater</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="login-box">
        <div class="header">
            <h1>Baked by the Crater</h1> <p>Password Reset</p>
        </div>

        <?php if ($error): ?>
            <p class="alert alert-error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="alert alert-success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <p class="subtitle" style="margin-bottom: 25px; margin-top: -15px;">Enter your account email address to receive a 6-digit password reset code.</p>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <button type="submit" name="forgot_password" class="btn-primary">Send Reset Code</button>
        </form>

        <div class="login-link">
            Wait, I remember my password! <a href="login.php">Sign in</a>
        </div>
    </div>
</body>
</html>