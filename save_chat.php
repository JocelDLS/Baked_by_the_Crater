<?php
// Set the content type to JSON for the response
header('Content-Type: application/json');

// Include the database connection file
include 'db.php'; // Includes the $con MySQLi object

// 1. Get the JSON payload from the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 2. CRITICAL CHECK: Ensure all keys, including 'user_id', are present and valid
if (
    !is_array($data) || 
    !isset($data['message']) || 
    empty(trim($data['message'])) || 
    !isset($data['type']) ||
    !in_array($data['type'], ['sent', 'received']) ||
    !isset($data['user_id']) || // This check prevents the "missing user_id" error
    empty($data['user_id'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or incomplete message payload (missing message, type, or user_id).']);
    exit;
}

$message_text = trim($data['message']);
$message_type = $data['type'];
$user_id = $data['user_id']; 

// Determine the sender type for the database record
$sender_type = ($message_type === 'sent') ? 'customer' : 'admin';

// --- DB LOGIC using MySQLi ---
if (isset($con)) {
    try {
        // Prepare the SQL INSERT statement; include timestamp for ordering
        $timestamp = date('Y-m-d H:i:s');
        $stmt = $con->prepare("INSERT INTO full_texts (user_id, message_text, sender_type, message_type, timestamp) VALUES (?, ?, ?, ?, ?)");
        
        // Bind parameters: 'issss' = integer, string, string, string, string
        $stmt->bind_param("issss", $user_id, $message_text, $sender_type, $message_type, $timestamp);
        
        // Execute the statement
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Chat history updated in database.']);
        } else {
            throw new Exception("MySQLi execution failed: " . $stmt->error);
        }
        
        $stmt->close();

    } catch (Exception $e) {
        error_log("Database Save Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database Save Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database connection ($con) failed or not available.']);
}
// --- END DB LOGIC ---
?>