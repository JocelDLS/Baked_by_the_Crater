<?php
session_start();

// --- START: Composer Autoloading and Dotenv Setup ---
// This single line loads all necessary classes (Dotenv and PHPMailer)
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
// --- END: Composer Autoloading and Dotenv Setup ---

// Database Connection (Loads $con variable)
require_once 'db.php';
global $con; // Make sure $con is available

// Configuration
define('SITE_URL', 'http://localhost');
define('GOOGLE_CLIENT_ID', '127969994010-0celbu5s9hk69daminm6ob43otafc0mv.apps.googleusercontent.com'); // Client ID updated
define('GOOGLE_REDIRECT_URI', SITE_URL . '/google_callback.php');

// --- Initialize variables to prevent "Undefined variable" warnings on initial load ---
$error = '';
$success = '';
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$address = '';
// --- END: Initialization ---


// Helper function to generate verification token
function generateToken($length = 6) {
    // Generates a 6-digit numeric code
    $min = 10**($length - 1);
    $max = 10**$length - 1;
    return str_pad(random_int($min, $max), $length, '0', STR_PAD_LEFT);
}

// Helper function to check if email exists (Uses mysqli)
function emailExists($con, string $email) {
    $stmt = mysqli_prepare($con, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $count = mysqli_stmt_num_rows($stmt);
    mysqli_stmt_close($stmt);
    return $count > 0;
}

// ----------------------------------------------------------------------
// --- START: Send Verification Email Function (NAMESPACE FIXED) ---
// ----------------------------------------------------------------------
function sendVerificationEmail($email, $firstName, $verification_token, &$mail_error_info) {
    global $con; 
    $mail = new PHPMailer(true);

    // --- UPDATED CREDENTIALS/SETTINGS (Use App Password for actual value) ---
    $mailHost = 'smtp.gmail.com';
    $mailUsername = 'bakedbythecrater@gmail.com';
    $mailPassword = 'prdg wdwa ejbe muan'; // !!! REPLACE with your actual App Password for testing !!!
    // GMAIL FIX: Set FROM address to be the same as the authenticated username
    $mailFromAddress = 'no-reply@craterbakery.com';
    // ------------------------------------------------------------------

    try {
        // *** FIX: Disable Debugging to prevent 'headers already sent' error ***
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // <<< BINAGO
        // ***

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
        $mail->addAddress($email, htmlspecialchars($firstName));

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Action Required: Verify Your Email for Baked by the Crater';
        $mail->Body    = "
            <h2>Email Verification Required</h2>
            <p>Hello " . htmlspecialchars($firstName) . ",</p>
            <p>Thank you for registering with Baked by the Crater. Your verification code is:</p>
            <p style='font-size: 20px; font-weight: bold;'>{$verification_token}</p>
            <p>Please navigate to the verification page to enter this code.</p>
            <p>If you did not request this, please ignore this email.</p>";
        
        $mail->AltBody = "Hello {$firstName},\n\nThank you for registering with Baked by the Crater. Your verification code is: {$verification_token}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        $mail_error_info = $mail->ErrorInfo;
        error_log("PHPMailer failed for {$email}: {$mail_error_info}");
        return false;
    }

}
// ----------------------------------------------------------------------
// --- END: Send Verification Email Function (NAMESPACE FIXED) ---
// ----------------------------------------------------------------------


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['register'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All required fields must be filled out.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (emailExists($con, $email)) {
        $error = 'An account with this email already exists. Please log in.';
    } elseif (!isset($_POST['agreeTerms'])) {
        // NEW VALIDATION — checkbox not checked
        $error = 'You must agree to the Terms of Service and Privacy Policy.';
    } else {
        // 2. Hash Password and Generate Token
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $verification_token = generateToken();
        $created_at = date('Y-m-d H:i:s');
        $is_verified = 0;
        $token_expires_at = date('Y-m-d H:i:s', time() + 3600);

        // 3. Insert User into Database
        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, phone, address, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssis", $email, $password_hash, $firstName, $lastName, $phone, $address, $is_verified, $created_at);
        $insert_user_success = mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);

        if ($insert_user_success) {
            // 4. Insert Verification Token
            $sql_token = "INSERT INTO user_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'VERIFICATION', ?)";
            $stmt_token = mysqli_prepare($con, $sql_token);
            mysqli_stmt_bind_param($stmt_token, "iss", $user_id, $verification_token, $token_expires_at);
            $insert_token_success = mysqli_stmt_execute($stmt_token);
            mysqli_stmt_close($stmt_token);

            if ($insert_token_success) {
                $mail_error_info = ''; 
                if (sendVerificationEmail($email, $firstName, $verification_token, $mail_error_info)) {
                    header('Location: verify.php?email=' . urlencode($email));
                    exit;
                } else {
                    $error = 'Account created, but failed to send verification email. PHPMailer Error: ' . htmlspecialchars($mail_error_info);
                }
            } else {
                $error = 'Failed to generate verification token. Please try again.';
            }
        } else {
            $error = 'Registration failed. Please try again or contact support.';
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Baked by the Crater</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <div class="login-box">
        <div class="header">
            <h1>Baked by the Crater</h1> <p>Create Account</p>
        </div>

        <?php if ($error): ?>
            <p class="alert alert-error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="alert alert-success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($firstName); ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($lastName); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address"><?php echo htmlspecialchars($address); ?></textarea>
            </div>

            <div class="form-group-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="agreeTerms" id="agreeTerms" required>
                <label for="agreeTerms">I agree to the Terms of Service and Privacy Policy *</label>
            </div>

            <button type="submit" name="register" class="btn-primary">Create Account</button>
        </form>

        <div class="divider"><span>or continue with</span></div>

        <div class="sso-buttons">
            <div id="googleButton"></div>
        </div>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>

    <script>
        function handleCredentialResponse(response) {
            // Send the credential to google_callback.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'google_callback.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'credential';
            input.value = response.credential;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        window.onload = function () {
            google.accounts.id.initialize({
                client_id: "<?php echo GOOGLE_CLIENT_ID; ?>",
                callback: handleCredentialResponse
            });
            
            google.accounts.id.renderButton(
                document.getElementById("googleButton"),
                { 
                    theme: "filled_black",
                    size: "large",
                    width: 400,
                    text: "continue_with"
                }
            );
        }
    </script>
</body>
</html>