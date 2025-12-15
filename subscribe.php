<?php
// This script handles the newsletter subscription form submission

session_start();

// Include the database connection
require_once 'db.php';
global $con;

// Define the redirect destination and messages
$redirect_to = 'index.php';
$success_message = 'subscription_success=1';
$error_message = 'subscription_error=1';
$duplicate_message = 'subscription_duplicate=1';

// Process the form submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Basic email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ' . $redirect_to . '?' . $error_message);
        exit;
    }

    // Use prepared statements to prevent SQL injection

    try {
        // Check if email already exists
        $sql_check = "SELECT email FROM subscribers WHERE email = ?";
        $stmt_check = mysqli_prepare($con, $sql_check);
        
        // Check for prepare error
        if (!$stmt_check) {
            error_log("MySQLi Prepare Error (Check): " . mysqli_error($con));
            header('Location: ' . $redirect_to . '?' . $error_message);
            exit;
        }

        mysqli_stmt_bind_param($stmt_check, "s", $email);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            // Email is already subscribed
            mysqli_stmt_close($stmt_check);
            header('Location: ' . $redirect_to . '?' . $duplicate_message);
            exit;
        }
        mysqli_stmt_close($stmt_check);
        
        // Insert new email into the subscribers table
        $sql_insert = "INSERT INTO subscribers (email, subscribed_at) VALUES (?, NOW())";
        $stmt_insert = mysqli_prepare($con, $sql_insert);

        // Check for prepare error
        if (!$stmt_insert) {
            error_log("MySQLi Prepare Error (Insert): " . mysqli_error($con));
            header('Location: ' . $redirect_to . '?' . $error_message);
            exit;
        }

        mysqli_stmt_bind_param($stmt_insert, "s", $email);
        $insert_success = mysqli_stmt_execute($stmt_insert);
        mysqli_stmt_close($stmt_insert);

        if ($insert_success) {
            // Success redirect
            header('Location: ' . $redirect_to . '?' . $success_message);
            exit;
        } else {
            // Database insert failed
            error_log("Subscription failed for email: {$email} due to insert failure.");
            header('Location: ' . $redirect_to . '?' . $error_message);
            exit;
        }

    } catch (Exception $e) {
        error_log("General PHP/Database error during subscription: " . $e->getMessage());
        header('Location: ' . $redirect_to . '?' . $error_message);
        exit;
    }

} else {
    // Direct access without POST data
    header('Location: ' . $redirect_to);
    exit;
}
?>