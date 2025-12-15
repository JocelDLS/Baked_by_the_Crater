<?php
// order_details.php
// Manages the detailed view and status updates for a single order.

require('db.php'); 
require('xml_utils.php'); // Assumed functions: get_order_by_id(), update_order_status(), format_currency()
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

// --- STATE MANAGEMENT ---
$message = '';
$message_type = 'msg-ok';
$order = false;
$order_id = (string) ($_GET['id'] ?? 0); // Order IDs are usually strings or integers, using string for consistency with XML attributes

// 1. HANDLE STATUS UPDATE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = trim($_POST['new_status'] ?? '');
    $post_order_id = trim($_POST['order_id'] ?? '');

    if (!empty($post_order_id) && !empty($new_status)) {
        if (update_order_status($post_order_id, $new_status)) {
            $message = "Order #{$post_order_id} status updated to " . strtoupper($new_status) . " successfully!";
            // Redirect to GET to clear POST data and reload the fresh order
            header("Location: order_details.php?id={$post_order_id}&msg=" . urlencode($message) . "&type=msg-ok");
            exit();
        } else {
            $message = "Error updating order status. Order not found or file error.";
            $message_type = 'msg-err';
        }
    }
}

// 2. LOAD ORDER DETAILS
if (!empty($order_id) && $order_id !== '0') {
    // get_order_by_id() is now defined in xml_utils.php
    $order = get_order_by_id($order_id);
    if (!$order) {
        $message = "Error: Order ID #{$order_id} not found.";
        $message_type = 'msg-err';
    }
} else {
    $message = "Error: Invalid Order ID provided.";
    $message_type = 'msg-err';
}

// Check for messages passed via URL after status update
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars(urldecode($_GET['msg']));
    $message_type = htmlspecialchars($_GET['type']);
}

// Helper function from orders.php (duplicate is fine since it's a utility)
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
$status_options = ['pending', 'shipped', 'completed', 'cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Order #<?= $order_id; ?> | Baked by the Crater</title>
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
                <a href="settings.php"><i class='bx bxs-cog'></i> Settings</a>
            </nav>
            <a href="logout.php" class="logout-btn"><i class='bx bx-log-out'></i> Logout</a>
        </aside>

        <main class="main-content">
            <header class="header">
                <h2>Manage Order #<?= htmlspecialchars($order_id); ?></h2>
                <div class="profile">
                    <span>Welcome, <?= $admin_name; ?></span>
                    <i class='bx bxs-user-circle'></i>
                </div>
            </header>
            
            <?php if (!empty($message)): ?>
                <div class="msg <?= $message_type; ?>">
                    <?= $message; ?>
                </div>
            <?php endif; ?>

            <a href="orders.php" class="btn btn-primary btn-small" style="width: auto; margin-bottom: 20px;">
                <i class='bx bx-arrow-back'></i> Back to Orders List
            </a>

            <?php if ($order): 
                $status_details = get_order_status_details($order['status']);
            ?>
                <div class="content-grid order-detail-grid"> 
                    
                    <div class="card status-card">
                        <h4>Order Summary</h4>
                        <div class="order-summary-details">
                            <p><strong>Order Date:</strong> <?= htmlspecialchars($order['date']); ?></p>
                            <p><strong>Customer Name:</strong> <?= htmlspecialchars($order['user_name']); ?></p>
                            <p><strong>Customer ID:</strong> <?= htmlspecialchars($order['user_id']); ?></p>
                            <p><strong>Total Amount:</strong> <?= format_currency($order['total_amount']); ?></p>
                        </div>
                        
                        <div class="current-status">
                            <label>Current Status:</label>
                            <span class="order-status <?= $status_details['class']; ?>">
                                <?= $status_details['text']; ?>
                            </span>
                        </div>

                        <form method="POST" action="order_details.php?id=<?= htmlspecialchars($order_id); ?>" class="status-form">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id); ?>">
                            
                            <label for="new_status">Update Status:</label>
                            <select id="new_status" name="new_status" required>
                                <?php foreach ($status_options as $status_val): ?>
                                    <option value="<?= $status_val; ?>" 
                                        <?= strtolower($order['status']) === $status_val ? 'selected' : ''; ?>>
                                        <?= strtoupper($status_val); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-small" style="width: 100%; margin-top: 10px;">Update Status</button>
                        </form>
                    </div> 

                    <div class="card shipping-card">
                        <h4>Customer & Shipping Details (Mocked)</h4>
                        <p><strong>Name:</strong> <?= htmlspecialchars($order['user_name']); ?></p>
                        <p><strong>Email:</strong> user<?= htmlspecialchars($order['user_id']); ?>@example.com</p>
                        <p><strong>Phone:</strong> +63 9xx xxx xxxx</p>
                        <p style="margin-top: 15px;"><strong>Shipping Address:</strong></p>
                        <p>123 Crater Lane, Malvar, PH 4217</p>
                        <p>Payment Method: Online Transfer</p>
                    </div>

                    <div class="card order-items-card">
                        <h4>Items in Order (<?= count($order['items'] ?? []); ?>)</h4>
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Name</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($order['items'])): ?>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_id']); ?></td> 
                                            <td><?= htmlspecialchars($item['name']); ?></td>
                                            <td><?= htmlspecialchars($item['quantity']); ?></td>
                                            <td><?= format_currency($item['price']); ?></td>
                                            <td><?= format_currency($item['subtotal']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5">No items found for this order.</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="text-align: right; font-weight: bold;">TOTAL:</td>
                                    <td><?= format_currency($order['total_amount']); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div> 

            <?php endif; ?>

        </main>

    </div> 
</body>
</html>