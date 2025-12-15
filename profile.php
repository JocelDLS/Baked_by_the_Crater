<?php
// 1. Start the session to access $_SESSION variables
session_start();

// 2. Include the database connection (It should define $con as MySQLi object)
include 'db.php'; 

// 3. Check if the user is logged in. If not, redirect them.
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

// Get the logged-in user's ID (Assuming $_SESSION['user_id'] holds the table's 'id')
$user_id = $_SESSION['user_id'];
$user_data = null; 
$db_query_successful = false; 
$debug_message = ''; // New variable to store error messages

// 4. CHECK 1: Database Connection Status
if (!isset($con) || $con->connect_error) {
    // FIX: Capture the exact database connection error
    $debug_message = "🔴 CRITICAL DB ERROR: The database connection (\$con) is not defined or failed. ";
    if (isset($con)) {
        $debug_message .= "MySQLi Error: " . $con->connect_error;
    } else {
        $debug_message .= "Check your 'db.php' file to ensure \$con is defined and the connection is successful.";
    }

} else {
    // 5. CHECK 2: Execute the profile data query
    // *** FIX APPLIED HERE: Changed 'username' to 'email AS username' (to match table and keep display code), ***
    // *** Changed 'user_id' to 'id' (to match primary key), and 'registration_date' to 'created_at'. ***
    if ($stmt = $con->prepare("SELECT email AS username, email, first_name, last_name, created_at FROM users WHERE id = ?")) {
        
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $stmt->close();
            $db_query_successful = true;
        } else {
            // FIX: Capture the query execution error
            $debug_message = "🔴 QUERY EXECUTION FAILED. MySQLi Error: " . $con->error;
        }
    } else {
        // FIX: Capture the prepare statement error
        $debug_message = "🔴 QUERY PREPARE FAILED. MySQLi Error: " . $con->error;
    }
}


// --- Logout/Error Display Logic ---

// If user data could not be fetched AND the query ran successfully (meaning the user doesn't exist)
if ($db_query_successful && !$user_data) {
    session_destroy();
    header("Location: index.php?logout=1&reason=invalid_profile");
    exit;
}

// Set variables for the header SAFELY
$page_title = ($user_data ? $user_data['username'] : 'User') . ' | Profile';
$active_page = 'profile'; 
include 'header.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="style.css">
    <title><?php echo $page_title; ?></title>
    <style>
        .debug-box {
            background-color: #fdd;
            border: 1px solid #f00;
            padding: 15px;
            margin-bottom: 20px;
            color: #333;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>

<section class="profile-section container">
    
    <?php if ($debug_message): // Display the debug message if an error occurred ?>
        <div class="debug-box">
            <h3>Debug Information (for Developer)</h3>
            <?php echo $debug_message; ?>
            <p>If you see a DB error, please check your `db.php` file.</p>
        </div>
    <?php endif; ?>

    <?php if ($user_data): // ONLY proceed if $user_data is valid ?>
    
        <h2>👤 User Profile: <?php echo htmlspecialchars($user_data['username']); ?></h2>
        
        <div class="profile-card">
            
            <div class="profile-detail">
                <label>Name:</label>
                <span><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></span>
            </div>
            
            <div class="profile-detail">
                <label>Email:</label>
                <span><?php echo htmlspecialchars($user_data['email']); ?></span>
            </div>
            
            <div class="profile-detail">
                <label>User ID:</label>
                <span><?php echo htmlspecialchars($user_id); ?></span>
            </div>
            
            <div class="profile-detail">
                <label>Member Since:</label>
                <span><?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></span>
            </div>
            
            <div class="profile-actions">
                <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                <a href="logout.php" class="btn-tertiary">Logout</a>
            </div>
            
        </div>
        
        <div class="profile-history">
            <h3>Recent Activity</h3>
            <p>Your recent orders and activity history will be displayed here.</p>
            <a href="chat_history.php" class="btn-small">View Chat History</a>
        </div>
    
    <?php else: // Display the original user-friendly error ?>
    
        <h2 class="error-message">Profile Load Error</h2>
        <p class="text-center">
            Sorry, we could not retrieve your profile details at this time. 
            This might be a temporary issue, or your user ID may be corrupted.
        </p>
        <div class="profile-actions" style="text-align: center; margin-top: 30px;">
            <a href="logout.php" class="btn-primary">Logout and Try Again</a>
            <a href="index.php" class="btn-tertiary">Go Home</a>
        </div>

    <?php endif; ?>

</section>

</body>
</html>
<?php include 'footer.php'; ?>