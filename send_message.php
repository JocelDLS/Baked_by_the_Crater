<?php
// send_message.php - Handles the contact form submission using PHPMailer (SMTP)

session_start();

// ====================================================================
// *** STEP 1: COMPOSER AUTOLOADING (GAYAHIN SA register.php) ***
// ====================================================================
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; 

// === CRITICAL: INCLUDE DB CONNECTION FIRST ===
// include 'db.php'; 
// ===============================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: contact.php");
    exit();
}

$error = '';
$success = '';

// 1. Basic Validation and Sanitization
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($name) || empty($email) || empty($message)) {
    $error = "Error: Please fill in your Name, Email, and Message.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Error: The email address provided is invalid.";
}

// 2. Process Submission using PHPMailer
if (empty($error)) {
    
    // --- EMAIL SETUP ---
    $business_email = "bakedbythecrater@gmail.com"; 
    $mail = new PHPMailer(true);

    try {
        // --- STEP 2: SMTP SERVER INITIAL CONFIGURATION ---
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bakedbythecrater@gmail.com'; 
        $mail->Password   = 'prdg wdwa ejbe muan'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465; 
        $mail->isHTML(true); // Default to HTML for both emails
        $mail->setFrom($business_email, 'Baked By The Crater'); // Set the FROM address once

        // ----------------------------------------------------
        // A. CONSTRUCT EMAIL BODIES (HTML and PLAIN TEXT)
        // ----------------------------------------------------
        $message_content = htmlspecialchars($message);
        $sender_name = htmlspecialchars($name);
        $sender_email = htmlspecialchars($email);
        $message_subject = htmlspecialchars($subject);

        // Reusable HTML Structure for Email Content
        $email_details_table = "
            <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                <tr><td style='padding: 8px 0; font-weight: bold; width: 30%;'>Name:</td><td style='padding: 8px 0;'>{$sender_name}</td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Email:</td><td style='padding: 8px 0;'><a href='mailto:{$sender_email}'>{$sender_email}</a></td></tr>
                <tr><td style='padding: 8px 0; font-weight: bold;'>Subject:</td><td style='padding: 8px 0;'>{$message_subject}</td></tr>
            </table>
            <h3 style='color: #2C1A0D; margin-top: 20px;'>Message:</h3>
            <div style='border: 1px solid #ccc; padding: 15px; background-color: #f9f9f9; white-space: pre-wrap;'>{$message_content}</div>
        ";

        // Reusable Plain Text Content
        $email_plain_content = "Name: {$sender_name}\nEmail: {$sender_email}\nSubject: {$message_subject}\nMessage:\n{$message_content}";


        // ----------------------------------------------------
        // B. SEND EMAIL TO BUSINESS (RECEIVING INQUIRY)
        // ----------------------------------------------------
        $mail->Subject = "New Inquiry: {$message_subject}";
        $mail->Body    = "<div style='font-family: Arial, sans-serif; border: 1px solid #2C1A0D; padding: 20px; max-width: 600px; margin: auto;'>
                            <h2 style='color: #2C1A0D;'>New Customer Inquiry Received!</h2>
                            <p>You have received a new message from the contact form on your website.</p>
                            {$email_details_table}
                            <p style='margin-top: 20px; font-size: 0.9em; color: #666;'>Please reply to the sender ({$sender_email}) directly.</p>
                          </div>";
        $mail->AltBody = "New Inquiry from Contact Form.\n\n{$email_plain_content}";
        
        $mail->addAddress($business_email);
        $mail->addReplyTo($sender_email, $sender_name);
        $mail->send();

        // ----------------------------------------------------
        // C. SEND CONFIRMATION EMAIL TO CUSTOMER (SENDER)
        // ----------------------------------------------------
        $mail->clearAllRecipients(); // I-clear ang recipients mula sa unang email
        $mail->clearReplyTos(); // I-clear ang reply-to
        
        $mail->Subject = "Confirmation: Your Message to Baked By The Crater";
        $mail->Body    = "<div style='font-family: Arial, sans-serif; border: 1px solid #A0522D; padding: 20px; max-width: 600px; margin: auto;'>
                            <h2 style='color: #A0522D;'>Message Confirmation</h2>
                            <p>Dear {$sender_name},</p>
                            <p>Thank you for contacting Baked by the Crater! This is an automated confirmation that we have successfully received your message. We will get back to you as soon as possible.</p>
                            <h3 style='color: #2C1A0D; margin-top: 20px;'>Your Sent Message Details:</h3>
                            {$email_details_table}
                            <p style='margin-top: 25px; font-size: 0.9em; color: #666;'>Do not reply to this automated email. We will contact you at {$sender_email}.</p>
                          </div>";
        $mail->AltBody = "Thank you for contacting us. We have received your message:\n\n{$email_plain_content}";

        $mail->addAddress($sender_email, $sender_name); // Target: Customer/Sender
        $mail->send();
        
        // Success Message
        $success = "Thank you, **" . $sender_name . "**! Your message has been received and a confirmation email has been sent to your inbox.";
        
    } catch (Exception $e) {
        // Failure Message 
        $error = "Sorry, there was an issue sending your message. Mailer Error: " . $mail->ErrorInfo . ". Please check your SMTP settings or call us.";
    }
}

// 3. Redirect back to contact.php with messages
if (!empty($error)) {
    $_SESSION['form_error'] = $error;
    // Store submitted data to pre-fill the form on redirect
    $_SESSION['form_data'] = [
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message
    ];  
} elseif (!empty($success)) {
    $_SESSION['form_success'] = $success;
}

header("Location: contact.php");
exit();
?>