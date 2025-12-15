<?php
// orders.php
// Manages the display and actions for customer orders.

require('db.php'); // Mock DB connection for user name or dashboard context
require('xml_utils.php'); // Contains get_all_orders() and update_order_status()
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
$admin_name = 'Admin'; 

// --- HELPER FUNCTIONS ---

/**
 * Reads the orders.xml file and returns an array of all orders.
 * NOTE: This relies on the get_all_orders() function in xml_utils.php
 */
$orders = get_all_orders(); 

/**
 * Determines the display text and CSS class for an order status
 * to match the look in admin_style.css and the visual sketch.
 * @param string $status The raw status from orders.xml
 * @return array 
 */
function get_order_status_details($status) {
    $status_lower = strtolower($status);
    switch ($status_lower) {
        case 'pending':
            return ['text' => 'PREPARING', 'class' => 'pending'];
        case 'shipped':
            return ['text' => 'SHIPPED', 'class' => 'shipped'];
        case 'completed':
            return ['text' => 'COMPLETE', 'class' => 'completed'];
        case 'cancelled':
            return ['text' => 'CANCELLED', 'class' => 'cancelled'];
        default:
            return ['text' => ucfirst($status_lower), 'class' => $status_lower];
    }
}

/**
 * Determines if a navigation link should have the 'active' CSS class.
 * @param string $page_name The file name (e.g., 'orders.php')
 * @return string 'active' or ''
 */
function is_active($page_name) {
    return strpos($_SERVER['SCRIPT_NAME'], $page_name) !== false ? 'active' : '';
}

// --------------------------------------------------------------------------
// --- HTML STRUCTURE ---
// --------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Dashboard - Baked by the Crater</title>
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
                <a href="orders.php" class="active"><i class='bx bxs-cart-alt'></i> Orders</a>
                <a href="users.php"><i class='bx bxs-group'></i> Users</a>
                <a href="chats.php"><i class='bx bxs-chat'></i> Chats</a> 
                <a href=" nsettings.php"><i class='bx bxs-cog'></i> Settings</a>
            </nav>
            <a href="logout.php" class="logout-btn"><i class='bx bx-log-out'></i> Logout</a>
        </aside>
        <main class="main-content">
            <header class="header">
                <h2>Orders Management</h2>
                <div class="profile">
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>
            
            <section class="recent-activity">
                <h3>Latest Orders</h3>
                <div class="activity-box">
                    <?php if (empty($orders)): ?>
                        <p class="msg msg-ok">No orders found.</p>
                    <?php else: ?>
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer Name</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): 
                                    $status_details = get_order_status_details($order['status']);
                                    $status_text = $status_details['text'];
                                    $status_class = $status_details['class'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['id']); ?></td>
                                        <td><?= htmlspecialchars($order['user_name'] ?? ('User ID: ' . ($order['user_id'] ?? 'N/A'))); ?></td>
                                        <td><?= htmlspecialchars($order['date']); ?></td>
                                        <td>$<?= number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="order-status <?= $status_class; ?>">
                                                <?= $status_text; ?>
                                            </span>
                                        </td>
                                        <td class="action-column text-center">
                                            <a href="order_details.php?id=<?= htmlspecialchars($order['id']); ?>" class="btn btn-small action-edit">Manage</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

        </main>

    </div> 
</body>
</html>