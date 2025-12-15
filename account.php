<?php
session_start();
include 'db.php'; 

$page_title = "Account Dashboard - Order History";
$active_page = 'account'; 
const ORDER_STORAGE_FILE = 'data/orders.xml'; 

$user_id = $_SESSION['user_id'] ?? '';
$error = '';
$user_orders = [];

// FIX: Add format_currency function definition for local use and consistency
/**
 * Formats a number/string as currency (₱XX.XX).
 */
function format_currency($amount) {
    // If it's a string (e.g., from XML where it might still have a comma), clean it first.
    if (is_string($amount)) {
        $amount = str_replace(['$', '₱', ','], '', $amount); 
    }
    return '₱' . number_format((float)$amount, 2); 
}

// --- 1. User Authentication Check ---
if (empty($user_id)) {
    // Redirect unauthenticated users to login
    header("Location: login.php?redirect=" . urlencode('account.php'));
    exit;
}

// --- 2. XML Utility Function ---

/**
 * Loads all orders from the XML file.
 * Returns: array of order objects/arrays.
 */
function load_all_orders_xml() {
    $all_orders = [];
    if (!file_exists(ORDER_STORAGE_FILE) || filesize(ORDER_STORAGE_FILE) == 0) {
        return $all_orders;
    }
    
    $xml = @simplexml_load_file(ORDER_STORAGE_FILE);
    if ($xml === false) { return $all_orders; }

    foreach ($xml->order as $order) {
        $order_items = [];
        foreach ($order->items->item as $item) {
            $order_items[] = [
                'product_id' => (string)$item['product_id'],
                'name' => (string)$item->product_name,
                // Ensure unit price is read correctly, though typically it doesn't have a comma
                'unit_price' => (float)str_replace(',', '', (string)$item->unit_price), 
                'quantity' => (int)$item->quantity
            ];
        }

        // FIX: The total amount string is cleaned of commas before casting to float.
        $total_amount_string = (string)$order->total_amount;
        $clean_total = str_replace(',', '', $total_amount_string);
        
        $all_orders[] = [
            'id' => (string)$order['id'],
            'user_id' => (string)$order->user_id,
            'date' => (string)$order->order_date,
            'total' => (float)$clean_total, // Now correctly loads as 2225.00
            'items' => $order_items
        ];
    }
    return $all_orders;
}

// --- 3. Load and Filter Orders ---

$all_orders = load_all_orders_xml();

if (empty($all_orders)) {
    $message = "You have no previous orders.";
} else {
    // Filter orders to show only the current user's orders
    $user_orders = array_filter($all_orders, function($order) use ($user_id) {
        // Ensure user_id types match for comparison (XML stores as string)
        return (string)$order['user_id'] === (string)$user_id; 
    });

    if (empty($user_orders)) {
        $message = "You have no previous orders.";
    }
}


// --- 4. HTML Output ---
include 'header.php'; // Assumes header includes basic layout setup
?>

<main>
    <section class="page-content account-history-section">
        <div class="container">
            <h2 class="page-title">Order History for User: <?php echo htmlspecialchars($user_id); ?></h2>
            <p class="page-subtitle">A list of all orders placed with Baked by the Crater.</p>

            <?php if (isset($message)): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($user_orders)): ?>
                
                <div class="order-list">
                    <?php 
                    // Sort orders by date descending (most recent first)
                    usort($user_orders, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });
                    
                    foreach ($user_orders as $order): 
                    ?>
                        <div class="order-card">
                            <div class="order-header">
                                <span class="order-id">Order #<?php echo htmlspecialchars($order['id']); ?></span>
                                <span class="order-date"><?php echo date('M j, Y', strtotime($order['date'])); ?></span>
                            </div>
                            
                            <div class="order-details">
                                <div class="detail-line">
                                    <span class="label">Total Amount:</span>
                                    <span class="value total-value"><?php echo format_currency($order['total']); ?></span> 
                                </div>
                                <div class="detail-line item-count">
                                    <span class="label">Items Purchased:</span>
                                    <span class="value"><?php echo count($order['items']); ?></span>
                                </div>
                            </div>

                            <div class="order-items-summary">
                                <h4>Items:</h4>
                                <ul>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <li>
                                            <?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <button class="btn-secondary view-details-btn">View Full Receipt (Simulated)</button>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>
    </section>
</main>

<?php 
include 'footer.php'; 
?>