<?php
// settings.php
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

// --------------------------------------------------------------------------
// --- MAIN LOGIC ---
// --------------------------------------------------------------------------

$admin_data = null;
$message = '';
$error = '';

if ($con !== false) {
    
    // --- 1. Handle Account Details Submission (Name Update) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details_submit'])) {
        $new_name = trim($_POST['new_name']);

        if (empty($new_name)) {
            $error = "Name cannot be empty.";
        } else {
            $stmt = $con->prepare("UPDATE admins SET name=? WHERE admin_id=?");
            $stmt->bind_param("si", $new_name, $admin_id);
            
            if ($stmt->execute()) {
                $message = "Admin name updated successfully!";
            } else {
                $error = "Error updating name: " . $stmt->error;
            }
            if (isset($stmt)) $stmt->close();
        }
    }

    // --- 2. Handle Password Change Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password_submit'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // First, fetch the current hashed password for verification
        $stmt = $con->prepare("SELECT password_hash FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_hash = $row['password_hash'];
        if (isset($stmt)) $stmt->close();

        // Validation checks
        if (!password_verify($current_password, $current_hash)) {
            $error = "The current password entered is incorrect.";
        } elseif (empty($new_password) || empty($confirm_password)) {
            $error = "New password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "The new password and confirmation password do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long.";
        } else {
            // Hash and update the new password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $con->prepare("UPDATE admins SET password_hash=? WHERE admin_id=?");
            $stmt->bind_param("si", $password_hash, $admin_id);
            
            if ($stmt->execute()) {
                $message = "Admin password updated successfully!";
            } else {
                $error = "Error updating password: " . $stmt->error;
            }
            if (isset($stmt)) $stmt->close();
        }
    }

    // --- 3. Fetch Admin Data (for display and name pre-filling) ---
    $stmt = $con->prepare("SELECT admin_id, name, email FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
        $admin_name = htmlspecialchars($admin_data['name']); // Update session-level name variable
    } else {
        // Should not happen if security check passed, but handle it anyway
        $error = "Admin account not found.";
    }
    if (isset($stmt)) $stmt->close();

} else {
    $error = "Database connection failed.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | The Malvar BatCave</title>
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
                <a href="chats.php"><i class='bx bxs-chat'></i> Chats</a>
                <a href="settings.php" class="active"><i class='bx bxs-cog'></i> Settings</a>
            </nav>
            <a href="logout.php" class="logout-btn"><i class='bx bx-log-out'></i> Logout</a>
        </aside>

        <main class="main-content">
            
            <header class="header">
                <h2>Admin Settings</h2>
                <div class="profile">
                    <i class='bx bxs-user-circle'></i>
                </div>
            </header>

            <?php if (!empty($message)): ?>
                <div class="msg msg-ok"><?= $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="msg msg-err"><?= $error; ?></div>
            <?php endif; ?>

            <div class="content-grid" style="grid-template-columns: 1fr 1fr;">

                <div class="card product-form-card">
                    <h4>Admin Account Details</h4>
                    <p style="margin-bottom: 20px; color: var(--text-muted); font-size: 0.9em;">
                        Update your display name and email address. (Admin ID: <?= $admin_id; ?>)
                    </p>
                    
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="update_details_submit" value="1">

                        <label for="admin_name">Name</label>
                        <input type="text" id="admin_name" name="new_name" value="<?= htmlspecialchars($admin_data['name'] ?? ''); ?>" required>
                        
                        <label for="admin_email">Email (Read-only)</label>
                        <input type="text" id="admin_email" value="<?= htmlspecialchars($admin_data['email'] ?? ''); ?>" disabled>

                        <button type="submit" class="btn" style="margin-top: 25px;">Update Account Details</button>
                    </form>
                </div>

                <div class="card product-form-card">
                    <h4>Change Admin Password</h4>
                    <p style="margin-bottom: 20px; color: var(--status-cancelled); font-size: 0.9em;">
                        You must know your current password to set a new one.
                    </p>
                    <form method="POST" action="settings.php">
                        <input type="hidden" name="change_password_submit" value="1">

                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>

                        <label for="new_password">New Password (Min 8 characters)</label>
                        <input type="password" id="new_password" name="new_password" required>

                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>

                        <button type="submit" class="btn action-delete" style="margin-top: 25px; background: var(--status-pending); color: var(--bg-main);">Change Password</button>
                    </form>
                </div>

                <div class="card product-form-card" style="grid-column: 1 / 3;">
                    <h4>System Preferences / XML Configuration</h4>
                    <p style="color: var(--text-muted);">
                        This section can be used later to manage non-database settings, such as system titles, XML data file locations, or logging preferences.
                    </p>
                    <div style="margin-top: 15px; padding: 10px; background: var(--input-bg); border-radius: 5px;">
                        <p style="font-size: 0.8em; color: var(--accent-soft);">Current XML Path: <code>/data/dashboard_charts.xml</code></p>
                        <p style="font-size: 0.8em; color: var(--accent-soft);">Timezone: Asia/Manila (PST)</p>
                    </div>
                </div>

            </div>

        </main>

    </div> 
</body>
</html>