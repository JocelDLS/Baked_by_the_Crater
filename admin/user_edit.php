<?php
// user_edit.php
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
// --- MAIN LOGIC ---
// --------------------------------------------------------------------------

$user_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$user_data = null;
$message = '';
$error = '';

if ($user_id > 0 && $con !== false) {
    
    // 1. Handle User Details Submission (Update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['change_password_submit'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);

        // Basic validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error = "First Name, Last Name, and Email are required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Prepare update statement for user details
            $stmt = $con->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address=? WHERE id=?");
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
            
            if ($stmt->execute()) {
                $message = "User details for ID #{$user_id} updated successfully!";
            } else {
                $error = "Error updating user details: " . $stmt->error;
            }
            if (isset($stmt)) $stmt->close();
        }
    }

    // 2. Handle Password Change Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password_submit'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_password) || empty($confirm_password)) {
            $error = "Both new password fields are required to change the password.";
        } elseif ($new_password !== $confirm_password) {
            $error = "The new password and confirmation password do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Hash the password securely
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Prepare update statement for password only
            $stmt = $con->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->bind_param("si", $password_hash, $user_id);
            
            if ($stmt->execute()) {
                $message = "Password for User ID #{$user_id} updated successfully!";
            } else {
                $error = "Error updating password: " . $stmt->error;
            }
            if (isset($stmt)) $stmt->close();
        }
    }

    // 3. Fetch/Re-fetch User Data (After any update attempt)
    $stmt = $con->prepare("SELECT id, email, first_name, last_name, phone, address FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $error = "User not found with ID: {$user_id}.";
    }
    if (isset($stmt)) $stmt->close();

} else {
    $error = "Invalid user ID provided.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Baked by the Crater</title>
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
                <a href="users.php" class="active"><i class='bx bxs-group'></i> Users</a>
                <a href="chats.php"><i class='bx bxs-chat'></i> Chats</a>
                <a href="settings.php"><i class='bx bxs-cog'></i> Settings</a>
            </nav>
            <a href="logout.php" class="logout-btn"><i class='bx bx-log-out'></i> Logout</a>
        </aside>

        <main class="main-content">
            
            <header class="header">
                <h2><?= $user_data ? "Edit User: " . htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) : "Edit User"; ?></h2>
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

            <?php if ($user_data): ?>
                
                <div class="content-grid product-forms-grid">
                    <div class="card product-form-card">
                        <h4>Update User Details (ID: <?= $user_data['id']; ?>)</h4>
                        <form method="POST" action="user_edit.php?id=<?= $user_data['id']; ?>">
                            <input type="hidden" name="id" value="<?= $user_data['id']; ?>">

                            <div class="form-group-flex">
                                <div class="form-field-half">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user_data['first_name']); ?>" required>
                                </div>
                                <div class="form-field-half">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user_data['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <label for="email">Email</label>
                            <input type="text" id="email" name="email" value="<?= htmlspecialchars($user_data['email']); ?>" required>

                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user_data['phone']); ?>">

                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?= htmlspecialchars($user_data['address']); ?></textarea>

                            <button type="submit" class="btn" style="margin-top: 20px;">Update Details</button>
                        </form>
                    </div>
                </div>

                <div class="content-grid product-forms-grid" style="margin-top: 20px; grid-template-columns: 1fr;"> 
                    <div class="card product-form-card">
                        <h4>Change Password</h4>
                        <form method="POST" action="user_edit.php?id=<?= $user_data['id']; ?>">
                            <input type="hidden" name="change_password_submit" value="1">
                            <input type="hidden" name="id" value="<?= $user_data['id']; ?>">

                            <div class="form-group-flex">
                                <div class="form-field-half">
                                    <label for="new_password">New Password (Min 8 chars)</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>
                                <div class="form-field-half">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn action-delete" style="margin-top: 20px; background: var(--status-pending); color: var(--bg-main);">Change Password</button>
                        </form>
                    </div>
                </div>
                
                <a href="users.php" class="btn btn-primary" style="margin-top: 20px; background: var(--chart-color-3); color: var(--text-main); line-height: 45px; width: 100%;">Back to Users List</a>

            <?php endif; ?>

        </main>

    </div> 
</body>
</html>