<?php
// chats.php
require('db.php'); 
require('xml_utils.php'); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SECURITY CHECK ---
if (!isset($_SESSION['admin_id'])) {
    if (isset($_COOKIE['admin_id'])) {
        $_SESSION['admin_id'] = $_COOKIE['admin_id'];
    } else {
        header('Location: login.php');
        exit();
    }
}
$admin_id = $_SESSION['admin_id'];

// --- FETCH ADMIN INFO ---
$admin_name = 'Admin'; 
if (isset($con) && $con !== false) {
    $stmt = $con->prepare("SELECT name FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $admin_name = htmlspecialchars($row['name']);
    }
    if (isset($stmt)) $stmt->close();
}

// --------------------------------------------------------------------------
// --- MAIN CHAT LOGIC ---
// --------------------------------------------------------------------------

$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$threads = [];
$messages = [];
$selected_user_name = '';
$message = '';
$error = '';

if ($con !== false) {
    
    // --- 1. Handle New Message Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_user_id > 0) {
        $new_message = trim($_POST['message_text']);
        
        if (!empty($new_message)) {
            // Admin is sending a message to the selected user.
            $sender_type = 'admin';
            $message_type = 'received'; 
            $timestamp = date('Y-m-d H:i:s');
            
            $stmt = $con->prepare("INSERT INTO full_texts (user_id, message_text, sender_type, message_type, timestamp) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $selected_user_id, $new_message, $sender_type, $message_type, $timestamp);
            
            if ($stmt->execute()) {
                // Redirect to prevent form resubmission on refresh
                header("Location: chats.php?user_id={$selected_user_id}&status=sent");
                exit();
            } else {
                $error = "Error sending message: " . $stmt->error;
            }
            if (isset($stmt)) $stmt->close();
        }
    }
    
    // Check if a message was just sent successfully
    if (isset($_GET['status']) && $_GET['status'] === 'sent') {
        $message = "Message sent successfully.";
    }


    // --- 2. Fetch Chat Threads (Unique users with latest message info) ---
    // Select the latest message for each user and join with the users table for names
    $threads_sql = "
        SELECT 
            t1.user_id,
            t3.first_name,
            t3.last_name,
            t1.message_text,
            t1.timestamp
        FROM full_texts t1
        INNER JOIN (
            SELECT 
                user_id,
                MAX(timestamp) AS max_timestamp
            FROM full_texts
            GROUP BY user_id
        ) AS t2 
        ON t1.user_id = t2.user_id AND t1.timestamp = t2.max_timestamp
        INNER JOIN users t3 ON t1.user_id = t3.id
        ORDER BY t1.timestamp DESC
    ";

    $threads_result = $con->query($threads_sql);
    if ($threads_result) {
        while ($row = $threads_result->fetch_assoc()) {
            $threads[] = $row;
        }
    } else {
        $error .= " Error fetching chat threads: " . $con->error;
    }


    // --- 3. Fetch Messages for Selected User ---
    if ($selected_user_id > 0) {
        
        // Determine selected user's name
        foreach($threads as $thread) {
            if ($thread['user_id'] == $selected_user_id) {
                $selected_user_name = htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']);
                break;
            }
        }
        
        if (empty($selected_user_name)) {
             $stmt = $con->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
             $stmt->bind_param("i", $selected_user_id);
             $stmt->execute();
             $name_result = $stmt->get_result();
             if ($name_result->num_rows > 0) {
                 $name_row = $name_result->fetch_assoc();
                 $selected_user_name = htmlspecialchars($name_row['first_name'] . ' ' . $name_row['last_name']);
             }
             if (isset($stmt)) $stmt->close();
        }

        // Fetch all messages for the selected user
        $messages_sql = "SELECT message_text, sender_type, timestamp FROM full_texts WHERE user_id = ? ORDER BY timestamp ASC";
        $stmt = $con->prepare($messages_sql);
        $stmt->bind_param("i", $selected_user_id);
        $stmt->execute();
        $messages_result = $stmt->get_result();
        
        if ($messages_result) {
            while ($row = $messages_result->fetch_assoc()) {
                $messages[] = $row;
            }
        }
        if (isset($stmt)) $stmt->close();
    }
} else {
    $error = "Database connection failed.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chats | The Malvar BatCave</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="dashboard-container">

        <aside class="sidebar">
            <div class="logo">
                <h3>Baked by the Crater</h3>
            </div>
            <nav class="nav-links">
                <a href="dashboard.php"><i class='bx bxs-dashboard'></i> Dashboard</a>
                <a href="products.php"><i class='bx bxs-box'></i> Products</a>
                <a href="orders.php"><i class='bx bxs-cart-alt'></i> Orders</a>
                <a href="users.php"><i class='bx bxs-group'></i> Users</a>
                <a href="chats.php" class="active"><i class='bx bxs-chat'></i> Chats</a>
                <a href="settings.php"><i class='bx bxs-cog'></i> Settings</a>
            </nav>
            <a href="logout.php" class="logout-btn"><i class='bx bx-log-out'></i> Logout</a>
        </aside>

        <main class="main-content">
            
            <header class="header">
                <h2>Customer Support Chats</h2>
                <div class="profile">
                    <i class='bx bxs-user-circle'></i>
                </div>
            </header>

            <?php if (!empty($message) && isset($_GET['status']) && $_GET['status'] === 'sent'): ?>
                <div class="msg msg-ok"><?= $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="msg msg-err"><?= $error; ?></div>
            <?php endif; ?>

            <div class="chat-container-layout">
                <div class="chat-thread-list card">
                    <h4>Active Threads</h4>
                    <?php if (empty($threads)): ?>
                        <p class="no-threads-msg">No active chat threads found.</p>
                    <?php else: ?>
                        <div class="thread-list-content">
                            <?php foreach ($threads as $thread): 
                                $is_active = $thread['user_id'] == $selected_user_id ? 'active' : '';
                                $full_name = htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']);
                                $last_message = htmlspecialchars($thread['message_text']);
                                $time = date('h:i A', strtotime($thread['timestamp']));
                            ?>
                                <a href="chats.php?user_id=<?= $thread['user_id']; ?>" class="thread-item <?= $is_active; ?>">
                                    <div class="thread-name"><?= $full_name; ?></div>
                                    <div class="thread-snippet"><?= substr($last_message, 0, 30) . (strlen($last_message) > 30 ? '...' : ''); ?></div>
                                    <div class="thread-time"><?= $time; ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="chat-window card">
                    <?php if ($selected_user_id > 0): ?>
                        <h4 class="chat-header">Chat with: <?= $selected_user_name; ?></h4>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                                <p class="no-messages-msg">Start a conversation with <?= $selected_user_name; ?>.</p>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): 
                                    $is_admin = $msg['sender_type'] === 'admin';
                                    $sender_class = $is_admin ? 'admin' : 'customer';
                                    $time = date('h:i A', strtotime($msg['timestamp']));
                                ?>
                                    <div class="message-bubble-row <?= $sender_class; ?>">
                                        <div class="message-bubble">
                                            <p class="message-text"><?= htmlspecialchars($msg['message_text']); ?></p>
                                            <span class="message-time"><?= $time; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="chats.php?user_id=<?= $selected_user_id; ?>" class="chat-input-form">
                            <input type="text" name="message_text" placeholder="Type a message..." required>
                            <button type="submit" class="btn btn-small"><i class='bx bxs-send'></i> Send</button>
                        </form>

                    <?php else: ?>
                        <div class="no-chat-selected">
                            <i class='bx bxs-chat'></i>
                            <p>Select a thread from the left to view the conversation.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>

    </div> 
    
    <script>
        // Scroll to the bottom of the chat messages automatically
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    </script>
</body>
</html>