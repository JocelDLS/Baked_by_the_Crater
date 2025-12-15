<?php
session_start();

require_once 'db.php';
global $con;

// --- START: PHPMailer Inclusion ---
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
// --- END: PHPMailer Inclusion ---

$provider = $_GET['provider'] ?? '';
$email = $_GET['email'] ?? '';
$firstName = $_GET['first_name'] ?? 'User';
$lastName = $_GET['last_name'] ?? '';

if ($provider === '') {
    die('Missing provider.');
}

// For demo purposes, if no email is supplied, fall back to a fake one
if ($email === '') {
    $email = $provider . '_guest@example.com';
}

// Check if user exists
$stmt_check = mysqli_prepare($con, "SELECT id, is_verified FROM users WHERE email = ? AND provider = ?");
mysqli_stmt_bind_param($stmt_check, "ss", $email, $provider);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$existing_user = mysqli_fetch_assoc($result_check);
mysqli_stmt_close($stmt_check);

if (!$existing_user) {
    // Create new SSO user (automatically verified if not using email verification flow)
    $created_at = date('Y-m-d H:i:s');
    $password_hash = ''; // No password for SSO users
    $is_verified = 1; // SSO users are typically auto-verified if coming from a trusted OAuth provider
    
    $sql_insert = "INSERT INTO users (email, password_hash, first_name, last_name, is_verified, provider, created_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($con, $sql_insert);
    // Note: The is_verified parameter should be an integer (i)
    mysqli_stmt_bind_param($stmt_insert, "sssssis", $email, $password_hash, $firstName, $lastName, $is_verified, $provider, $created_at);
    $insert_success = mysqli_stmt_execute($stmt_insert);
    mysqli_stmt_close($stmt_insert);
    
    if (!$insert_success) {
        die('Failed to create SSO user account.');
    }
    $user_id = mysqli_insert_id($con);
} else {
    $user_id = $existing_user['id'];
    
    // If not verified, mark as verified (this path is mostly for legacy users 
    // or if the standard SSO flow used email verification, but Google's flow is auto-verifying)
    if (!$existing_user['is_verified']) {
        $stmt_update = mysqli_prepare($con, "UPDATE users SET is_verified = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update, "i", $user_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }
}

// Update last_login timestamp
$stmt_update_login = mysqli_prepare($con, "UPDATE users SET last_login = NOW() WHERE id = ?");
mysqli_stmt_bind_param($stmt_update_login, "i", $user_id);
mysqli_stmt_execute($stmt_update_login);
mysqli_stmt_close($stmt_update_login);


// Log the user in
$_SESSION['user_id'] = $user_id;
$_SESSION['email'] = $email;
$_SESSION['first_name'] = $firstName;
$_SESSION['logged_in'] = true;

// Redirect to dashboard or home page
header('Location: index.php'); // Assuming an index.php exists
exit;