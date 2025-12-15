<?php
session_start();
// Include db.php for file structure (it contains connection details but is unused for confirmation)
include 'db.php'; 

// --- Configuration (XML file constants) ---
// Define the location of the stored orders (assuming it's in a 'data' folder)
const ORDER_STORAGE_FILE = 'data/orders.xml'; 

$page_title = "Order Confirmation";
$active_page = ''; 

// --- XML Utility Functions ---

/**
 * Loads a specific order's details from the 'orders.xml' file by ID.
 * This is the secure way to get the total after an order is placed.
 */
function load_order_xml(int $order_id) {
    if (!file_exists(ORDER_STORAGE_FILE)) { return null; }

    $xml = @simplexml_load_file(ORDER_STORAGE_FILE);
    if ($xml === false) { return null; }
    
    // Use XPath to find the specific order by its 'id' attribute
    $result = $xml->xpath("//order[@id={$order_id}]");
    
    if (empty($result)) { return null; }
    
    $order_data = $result[0];
    
    return [
        'id' => (int)$order_data['id'],
        'user_id' => (string)$order_data->user_id,
        'order_date' => (string)$order_data->order_date,
        // total_amount is stored as a formatted string in the XML
        'total_amount' => (string)$order_data->total_amount, 
    ];
}

/**
 * Formats a number/string as currency (₱XX.XX).
 * (Necessary because the user's HTML code uses this function)
 */
function format_currency($amount) {
    // Clean up possible currency symbols or commas before formatting
    if (is_string($amount)) {
        // FIX: Updated to clean up existing $ or ₱ symbols and replace with ₱
        $amount = str_replace(['$', '₱', ','], '', $amount); 
    }
    return '₱' . number_format((float)$amount, 2); // FIX: Changed '$' to '₱'
}


// --- Data Retrieval (Security Fix implemented here) ---
// Initialize variables
$order_id = 0;
$order_total = '0.00';
$order_details = null;

// FIX: Get the secure Order ID passed from checkout.php via URL
if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    
    // Load the official, saved order data from the XML file
    $order_details = load_order_xml($order_id); 
    
    if ($order_details) {
        // Use the total from the saved order, NOT the insecure URL parameter
        $order_total = $order_details['total_amount'];
    }
}

// Get the User ID for personalization
$user_id = $_SESSION['user_id'] ?? 'Guest';


// --- HTML Output ---
include 'header.php'; // Assumes this provides the start of HTML, navigation, etc.
?>

<main>
    <section class="page-content confirmation-section">
        <div class="container confirmation-container">
            
            <i class="fas fa-check-circle confirmation-icon"></i>
            <h2 class="page-title">Order Confirmed!</h2>
            
            <p class="confirmation-message">
                Thank you, <?php echo htmlspecialchars($user_id); ?>! Your order has been successfully placed.
            </p>

            <div class="order-summary-box">
                <h3>Order Details</h3>
                
                <div class="summary-line">
                    <span class="label">Order Number:</span>
                    <span class="value">#<?php echo htmlspecialchars($order_id > 0 ? $order_id : 'N/A'); ?></span>
                </div>

                <div class="summary-line total-line">
                    <span class="label">Total Charged:</span>
                    <span class="value grand-total-value"><?php echo format_currency($order_total); ?></span> 
                </div>
                
                <p class="delivery-note">
                    You will receive a confirmation email shortly with the full details and tracking information.
                </p>
            </div>

            <div class="next-steps-actions">
                <a href="shop.php" class="btn-primary">Continue Shopping</a>
                <a href="account.php" class="btn-secondary">View Order History</a>
            </div>

        </div>
    </section>
</main>

<?php 
include 'footer.php'; // Assumes this closes the HTML document
?>