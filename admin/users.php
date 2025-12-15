<?php
// users.php
require('db.php'); 
require('xml_utils.php'); // Include if user data might rely on XML or common utilities
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
// --- FETCH USER DATA (USING CORRECTED SCHEMA) ---
// --------------------------------------------------------------------------
$users = [];
$message = '';

if (isset($con) && $con !== false) {
    // Corrected SQL query based on your table schema: id, email, password_hash, first_name, last_name, phone, address
    $sql = "SELECT id, email, first_name, last_name, phone, address FROM users ORDER BY id DESC";
    $result = $con->query($sql);

    if ($result) {
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // Combine first and last name for display
                $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
                $users[] = $row;
            }
        } else {
            $message = 'No users found in the database.';
        }
    } else {
        $message = "Error fetching users: " . $con->error;
    }
} else {
    $message = "Database connection failed.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | The Malvar BatCave</title>
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
                <h2>Customer Users</h2>
                <div class="profile">
                    <i class='bx bxs-user-circle'></i>
                </div>
            </header>

            <?php if (!empty($message)): ?>
                <div class="msg msg-err"><?= $message; ?></div>
            <?php endif; ?>

            <div class="activity-box">
                <?php if (!empty($users)): ?>
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Account Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']); ?></td>
                                    <td><?= htmlspecialchars($user['full_name']); ?></td>
                                    <td><?= htmlspecialchars($user['email']); ?></td>
                                    <td><?= htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($user['address'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="account-type customer">
                                            Customer
                                        </span>
                                    </td>
                                    <td class="action-column-inline">
                                        <a href="user_edit.php?id=<?= $user['id']; ?>" class="btn btn-small action-edit">Edit</a>
                                        <form method="POST" action="user_delete.php" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                            <button type="submit" class="btn btn-small action-delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No customer accounts are registered.</p>
                <?php endif; ?>
            </div>

        </main>

    </div> 
</body>
</html>