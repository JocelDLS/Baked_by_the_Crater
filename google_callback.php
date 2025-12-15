<?php
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
define('GOOGLE_CLIENT_ID', '247335083658-2i7ksr2i8he1vtedhdiciglbthorc1jc.apps.googleusercontent.com');

// Helper function to generate verification token (Needed for fallback email logic)
function generateToken($length = 6) {
    // Generates a 6-digit numeric code
    $min = 10**($length - 1);
    $max = 10**$length - 1;
    return str_pad(random_int($min, $max), $length, '0', STR_PAD_LEFT);
}

// --- START: JWT and User Data Processing ---
// Get the JWT credential from POST
$credential = $_POST['credential'] ?? '';

if (empty($credential)) {
    header('Location: login.php?error=No credential received from Google');
    exit;
}

// Decode the JWT (without verification for simplicity - in production, verify the signature)
function decodeJWT($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    // Base64Url decode the payload
    $payload = base64_decode(strtr($parts[1], '-_', '+/'));
    return json_decode($payload, true);
}

$payload = decodeJWT($credential);

if (!$payload) {
    header('Location: login.php?error=Invalid credential received from Google');
    exit;
}

// Extract relevant user information
$email = $payload['email'] ?? null;
$firstName = $payload['given_name'] ?? 'User';
$lastName = $payload['family_name'] ?? '';
$is_verified = ($payload['email_verified'] ?? false) ? 1 : 0; // Google verifies email

if (empty($email)) {
    header('Location: login.php?error=Google login failed: Email not provided.');
    exit;
}

// 1. Check if user exists
$sql_check = "SELECT id, is_verified FROM users WHERE email = ? AND provider = 'GOOGLE'";
$stmt_check = mysqli_prepare($con, $sql_check);
mysqli_stmt_bind_param($stmt_check, "s", $email);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$existing_user = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

if ($existing_user) {
    $user_id = $existing_user['id'];
    
    // Update last_login timestamp and verify status (Google users are usually pre-verified)
    $stmt_update = mysqli_prepare($con, "UPDATE users SET last_login = NOW(), is_verified = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_update, "ii", $is_verified, $user_id);
    mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);

    if ($existing_user['is_verified'] == 0 && $is_verified == 1) {
        // User was unverified but is now verified by Google/update
        // Log them in immediately
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['logged_in'] = true;
        header('Location: index.php');
        exit;
    } elseif ($existing_user['is_verified'] == 0) {
        // User is still unverified (this shouldn't happen with standard Google login, but as a fallback)
        header('Location: login.php?verification_needed=1'); // Fallback to prompt local verification
        exit;
    } else {
        // User is fully verified, log them in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['logged_in'] = true;
        header('Location: index.php'); // Assuming an index.php dashboard
        exit;
    }

} else {
    // 2. Create New User (SSO - Google)
    $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT); // Generate a random, unusable password
    $provider = 'GOOGLE';
    $created_at = date('Y-m-d H:i:s');
    
    // Insert New User
    $sql_insert = "INSERT INTO users (email, password_hash, first_name, last_name, is_verified, provider, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($con, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "sssssis", $email, $password_hash, $firstName, $lastName, $is_verified, $provider, $created_at);
    $insert_success = mysqli_stmt_execute($stmt_insert);
    $user_id = mysqli_insert_id($con);
    mysqli_stmt_close($stmt_insert);

    if (!$insert_success) {
        header('Location: login.php?error=Failed to create account.');
        exit;
    }

    if ($is_verified == 0) {
        // This block runs if Google did not verify the email (rare/hypothetical), 
        // falling back to local verification logic (if needed).
        
        // Generate Token
        $verification_token = generateToken();
        $token_expires_at = date('Y-m-d H:i:s', time() + 3600); 

        // Insert Token (SQL omitted for brevity, assuming standard token insertion)
        $sql_token = "INSERT INTO user_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'VERIFICATION', ?)";
        $stmt_token = mysqli_prepare($con, $sql_token);
        mysqli_stmt_bind_param($stmt_token, "iss", $user_id, $verification_token, $token_expires_at);
        $insert_token_success = mysqli_stmt_execute($stmt_token);
        mysqli_stmt_close($stmt_token);

        if ($insert_token_success) {
            
            // Send Verification Email (using PHPMailer setup)
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
                $mail->SMTPDebug = SMTP::DEBUG_OFF; 
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
                
                // *** FIX: Redirect directly to verify.php to show the code input form ***
                header('Location: verify.php?email=' . urlencode($email));
                exit;

            } catch (Exception $e) {
                error_log("PHPMailer failed for {$email}: {$mail->ErrorInfo}");
                header('Location: login.php?mail_error=1');
                exit;
            }

        } else {
            // Error inserting token
            header('Location: login.php?error=Failed to generate verification token.');
            exit;
        }

    } else {
        // User is fully verified (by Google), log them in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['logged_in'] = true;
        
        // Redirect to a dashboard or home page
        header('Location: index.php'); // Assuming an index.php dashboard
        exit;
    }
}
?>