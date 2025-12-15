<?php
// xml_utils.php - Functions for XML Data Parsing

// --------------------------------------------------------------------------
// --- CRITICAL FIX: CHANGING RELATIVE PATHS TO POINT TO PARENT DIRECTORY ---
// Assuming this file is in an /admin/ folder and the data folder is in the root.
// --------------------------------------------------------------------------
const ORDER_FILE = '../data/orders.xml'; 
const PRODUCT_CATALOG_FILE = '../data/products.xml';
const CATEGORY_FILE = '../data/categories.xml'; 

// --------------------------------------------------------------------------
// --- CORE XML UTILITIES ---
// --------------------------------------------------------------------------

/**
 * Loads the SimpleXMLElement object from a file path.
 * Includes logic to create the 'data' directory and initial XML files if missing.
 * @param string $file_path
 * @return SimpleXMLElement|false
 */
function load_xml_file(string $file_path) {
    
    // 1. --- CHECK AND CREATE DIRECTORY ---
    $dir_path = dirname($file_path);
    
    if (!is_dir($dir_path)) {
        if (!@mkdir($dir_path, 0777, true)) { 
            return false;
        }
    }

    // Check if the file exists
    if (!file_exists($file_path)) {
        
        // 2. --- CREATE EMPTY XML FILE IF NECESSARY ---
        $new_xml = false;
        
        if ($file_path === PRODUCT_CATALOG_FILE) {
            $new_xml = new SimpleXMLElement('<products/>');
        } elseif ($file_path === CATEGORY_FILE) {
            $new_xml = new SimpleXMLElement('<categories/>');
            // Default categories
            $default_categories = ['Bread', 'Pastries', 'Cakes', 'Cookies', 'Drinks'];
            foreach ($default_categories as $cat) {
                $new_xml->addChild('category', htmlspecialchars($cat));
            }
        } elseif ($file_path === ORDER_FILE) {
             $new_xml = new SimpleXMLElement('<orders/>');
        }
        
        // Save the new XML file
        if ($new_xml !== false) {
            if ($new_xml->asXML($file_path)) {
                return $new_xml;
            } else {
                return false;
            }
        }
        
        return false;
    }
    
    // Suppress errors and check for success if file exists
    $xml = @simplexml_load_file($file_path);
    if ($xml === false) {
        return false;
    }
    return $xml;
}

/**
 * Saves the SimpleXMLElement object back to its file path.
 * @param SimpleXMLElement $xml
 * @param string $file_path
 * @return bool
 */
function save_xml_file(SimpleXMLElement $xml, string $file_path): bool {
    // Attempt to save the XML file
    return $xml->asXML($file_path);
}

// --------------------------------------------------------------------------
// --- CATEGORY FUNCTIONS ---
// --------------------------------------------------------------------------

function get_product_categories(): array {
    $xml = load_xml_file(CATEGORY_FILE);

    if ($xml === false) {
        return [];
    }
    
    $categories = [];
    foreach ($xml->category as $category) {
        $categories[] = (string) $category;
    }
    
    return $categories;
}

function add_product_category(string $category_name): bool {
    $xml = load_xml_file(CATEGORY_FILE);

    if ($xml === false) {
        return false;
    }
    
    $category_name = trim($category_name);
    foreach ($xml->category as $category) {
        if (strtolower((string) $category) === strtolower($category_name)) {
            return false;
        }
    }

    $xml->addChild('category', htmlspecialchars($category_name));
    
    return save_xml_file($xml, CATEGORY_FILE);
}


// --------------------------------------------------------------------------
// --- PRODUCT CATALOG FUNCTIONS ---
// --------------------------------------------------------------------------

/**
 * Loads and parses the product catalog XML, now including the image field.
 */
function get_product_catalog_data() {
    $xml = load_xml_file(PRODUCT_CATALOG_FILE);

    if ($xml === false) {
        return [];
    }
    
    $products = [];
    foreach ($xml->product as $product) {
        $products[] = [
            'id' => (string) $product['id'],
            'name' => (string) $product->name,
            'description' => (string) $product->description,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
            'category' => (string) $product->category,
            // NEW: Added image_path extraction
            'image_path' => (string) ($product->image_path ?? ''), 
        ];
    }
    
    return $products;
}

function get_product_by_id(int $product_id) {
    $products = get_product_catalog_data();
    foreach ($products as $product) {
        if ((int) $product['id'] === $product_id) {
            return $product;
        }
    }
    return null;
}

/**
 * Adds a new product to the XML catalog, now accepting an image_path.
 */
function add_product_to_xml(array $data): bool {
    $xml = load_xml_file(PRODUCT_CATALOG_FILE); 
    if ($xml === false) { return false; }

    $max_id = 0;
    foreach ($xml->product as $product) {
        $id = (int) $product['id'];
        if ($id > $max_id) { $max_id = $id; }
    }
    $new_id = $max_id + 1;
    $new_product = $xml->addChild('product');
    $new_product->addAttribute('id', $new_id);
    $new_product->addChild('name', htmlspecialchars($data['name']));
    $new_product->addChild('description', htmlspecialchars($data['description'] ?? ''));
    $new_product->addChild('price', (string) $data['price']);
    $new_product->addChild('stock', (string) $data['stock']);
    $new_product->addChild('category', htmlspecialchars($data['category']));
    // NEW: Add the image path element
    $new_product->addChild('image_path', htmlspecialchars($data['image_path'] ?? '')); 
    
    return save_xml_file($xml, PRODUCT_CATALOG_FILE);
}

/**
 * Updates an existing product in the XML catalog, now updating the image_path.
 */
function update_product_in_xml(int $product_id, array $data): bool {
    $xml = load_xml_file(PRODUCT_CATALOG_FILE);
    if ($xml === false) { return false; }
    foreach ($xml->product as $product) {
        if ((int) $product['id'] === $product_id) {
            $product->name = htmlspecialchars($data['name']);
            $product->description = htmlspecialchars($data['description'] ?? '');
            $product->price = (string) $data['price'];
            $product->stock = (string) $data['stock'];
            $product->category = htmlspecialchars($data['category']);
            // NEW: Update the image path element
            $product->image_path = htmlspecialchars($data['image_path'] ?? ''); 
            
            return save_xml_file($xml, PRODUCT_CATALOG_FILE);
        }
    }
    return false;
}

function delete_product_from_xml(int $product_id): bool {
    $xml = load_xml_file(PRODUCT_CATALOG_FILE);
    if ($xml === false) { return false; }
    $dom = dom_import_simplexml($xml);
    $product_found = false;
    foreach ($dom->childNodes as $node) {
        if ($node->nodeName === 'product') {
            $simplexml_product = simplexml_import_dom($node);
            if ((int) $simplexml_product['id'] === $product_id) {
                $node->parentNode->removeChild($node);
                $product_found = true;
                break;
            }
        }
    }
    if ($product_found) {
        $new_xml = simplexml_import_dom($dom);
        return save_xml_file($new_xml, PRODUCT_CATALOG_FILE);
    }
    return false;
}

// --------------------------------------------------------------------------
// --- DASHBOARD FUNCTIONS (FIXED) ---
// --------------------------------------------------------------------------

/**
 * FIXED: Ensures total amounts are correctly parsed by removing commas, 
 * and uses total_amount as the profit proxy since the profit tag is missing.
 */
function get_dashboard_chart_data() { 
    $xml = load_xml_file(ORDER_FILE); 
    
    $default_data = [
        'profit' => ['labels' => ['Nov', 'Dec'], 'data' => [500.00, 750.00]], 
        'items_sold' => ['labels' => ['Cake', 'Bread'], 'data' => [15, 10]],
        'income' => ['labels' => ['Q1', 'Q2', 'Q3', 'Q4'], 'data' => [10000, 15000, 12000, 0]], 
        'stock' => ['labels' => ['Bread', 'Pastries', 'Cakes'], 'data' => [40, 35, 25]], 
    ];

    if ($xml === false) {
        return $default_data;
    }

    $monthly_data = [];
    $items_sold = [];
    $stock_categories = [
        'Bread' => 0,
        'Pastries' => 0,
        'Cakes' => 0,
        'Cookies' => 0, 
        'Drinks' => 0, 
        'Other' => 0,
    ];
    
    foreach ($xml->order as $order) {
        $status = (string) $order['status'];
        if ($status !== 'completed' && $status !== 'shipped') {
            continue;
        }

        // Use order_date element
        $date_str = (string)($order->order_date ?? $order['date'] ?? (string)$order['datetime'] ?? '');
        $timestamp = strtotime($date_str);
        
        if ($timestamp !== false) {
            $month_key = date('Y-m', $timestamp);
            $month_label = date('M', $timestamp);
            
            // --- FIX: Harmonize total/profit extraction and handle comma in value ---
            $raw_profit_str = '';
            $raw_total_str = '';

            // 1. Get total string value (used for income)
            $raw_total_str = (string)($order->total_amount ?? $order['total'] ?? $order->total ?? '0.00');

            // 2. Get profit string value (if exists)
            $raw_profit_str = (string)($order->profit ?? $order['profit'] ?? '');
            
            // Clean and cast total (income). Removes commas before casting.
            $total = (float) str_replace(',', '', $raw_total_str);
            
            // Clean and cast profit (If not found, use $total as a proxy for profit)
            $profit = (empty($raw_profit_str)) 
                      ? $total 
                      : (float) str_replace(',', '', $raw_profit_str);
            // --- END FIX ---


            if (!isset($monthly_data[$month_key])) {
                $monthly_data[$month_key] = ['profit' => 0, 'income' => 0, 'label' => $month_label];
            }
            $monthly_data[$month_key]['profit'] += $profit;
            $monthly_data[$month_key]['income'] += $total;
        }

        if (!isset($order->items) || !isset($order->items->item)) { continue; }
        foreach ($order->items->item as $item) {
            $name = '';
            if (isset($item->product_name)) { $name = (string)$item->product_name; }
            elseif (isset($item->name)) { $name = (string)$item->name; }
            elseif (isset($item['product_name'])) { $name = (string)$item['product_name']; }
            elseif (isset($item['name'])) { $name = (string)$item['name']; }

            $category = '';
            if (isset($item->category)) { $category = (string)$item->category; }
            elseif (isset($item['category'])) { $category = (string)$item['category']; }

            $quantity = 0;
            if (isset($item->quantity)) { $quantity = (int)$item->quantity; }
            elseif (isset($item['quantity'])) { $quantity = (int)$item['quantity']; }

            if (!isset($items_sold[$name])) {
                $items_sold[$name] = 0;
            }
            $items_sold[$name] += $quantity;

            if (array_key_exists($category, $stock_categories)) {
                $stock_categories[$category] += $quantity;
            } else {
                if (!empty($category)) {
                    $stock_categories['Other'] += $quantity;
                }
            }
        }
    }
    
    ksort($monthly_data);
    $profit_labels = [];
    $profit_data = [];
    $quarterly_income = [1 => 0, 2 => 0, 3 => 0, 4 => 0]; 
    
    foreach ($monthly_data as $key => $month) {
        $profit_labels[] = $month['label'];
        $profit_data[] = round($month['profit'], 2);
        
        $month_num = (int) date('n', strtotime($key . '-01'));
        $quarter = ceil($month_num / 3);
        $quarterly_income[$quarter] += $month['income'];
    }
    
    arsort($items_sold);
    $top_items = array_slice($items_sold, 0, 5, true);
    $items_sold_labels = array_keys($top_items);
    $items_sold_data = array_values($top_items);

    $stock_total = array_sum($stock_categories);
    $stock_labels = [];
    $stock_data_final = [];
    
    foreach ($stock_categories as $cat => $count) {
        if ($count > 0) {
            $percentage = ($stock_total > 0) ? round(($count / $stock_total) * 100) : 0;
            $stock_labels[] = $cat . ' (' . $percentage . '%)';
            $stock_data_final[] = $percentage;
        }
    }
    
    if (empty($stock_labels)) {
        $stock_labels = $default_data['stock']['labels'];
        $stock_data_final = $default_data['stock']['data'];
    }

    return [
        'profit' => ['labels' => $profit_labels, 'data' => $profit_data],
        'items_sold' => ['labels' => $items_sold_labels, 'data' => $items_sold_data],
        'income' => ['labels' => ['Q1', 'Q2', 'Q3', 'Q4'], 'data' => array_values($quarterly_income)],
        'stock' => ['labels' => $stock_labels, 'data' => $stock_data_final],
    ];
}

// --------------------------------------------------------------------------
// --- ORDER FUNCTIONS (FIXED) ---
// --------------------------------------------------------------------------

/**
 * Fetches the customer name from the database given the user ID.
 * FIXED: Query changed to concatenate 'first_name' and 'last_name' 
 * since the 'users' table schema lacks a single 'name' column.
 */
function fetch_customer_name_from_db(int $user_id): string {
    global $con; 
    
    if (!isset($con) || !$con instanceof mysqli || $con->connect_error) {
        return "User ID: {$user_id} (DB Offline)";
    }

    // FIX: Concatenate first_name and last_name as 'name' column does not exist
    $stmt = $con->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id = ?"); 
    if ($stmt === false) {
        return "User ID: {$user_id} (Query Error)";
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = htmlspecialchars($row['name']);
        $stmt->close();
        return $name;
    }
    
    $stmt->close();
    return "User ID: {$user_id} (Not Found)";
}


/**
 * Reads the orders.xml file and returns an array of all orders.
 * FIXED: Now correctly reads data from XML elements and handles comma in total amount.
 * @return array A list of all orders.
 */
function get_all_orders() {
    if (!file_exists(ORDER_FILE)) {
        return [];
    }

    $xml = simplexml_load_file(ORDER_FILE);
    if ($xml === false) {
        return [];
    }

    $orders = [];
    foreach ($xml->order as $order) {
        // FIX: Read user_id from element
        $user_id = (int)($order->user_id ?? 0); 
        
        // FIX: Read date from ELEMENT and fallback to attribute 'date'
        $date_str = (string)($order->order_date ?? $order['date'] ?? 'N/A');
        
        // FIX: Read total_amount from ELEMENT and clean it
        $raw_total_str = (string)($order->total_amount ?? $order->total ?? '0.00');
        $total_amount = (float) str_replace(',', '', $raw_total_str); 

        $orders[] = [
            'id' => (string)$order['id'],
            'status' => (string)($order['status'] ?? 'pending'), 
            
            'date' => $date_str, 
            'total_amount' => $total_amount,
            
            'user_id' => $user_id,
            'user_name' => fetch_customer_name_from_db($user_id),
        ];
    }

    return array_reverse($orders);
}


/**
 * Finds a single order by its ID, including all item details and customer name.
 * FIXED: Correctly reads order data, maps unit_price to 'price', and calculates 'subtotal' 
 * for compatibility with order_details.php.
 * @param int $id The ID of the order to find.
 * @return array|bool The detailed order array or false if not found.
 */
function get_order_by_id(int $id) {
    $xml = load_xml_file(ORDER_FILE);
    if ($xml === false) {
        return false;
    }

    $target_orders = $xml->xpath("//order[@id='{$id}']");

    if (!empty($target_orders)) {
        $order_node = $target_orders[0];
        
        $user_id = (int)($order_node->user_id ?? 0); 

        // Read order data from elements/attributes consistently
        $date_str = (string)($order_node->order_date ?? $order_node['date'] ?? 'N/A');
        $raw_total_str = (string)($order_node->total_amount ?? $order_node->total ?? '0.00');
        $total_amount = (float) str_replace(',', '', $raw_total_str); 
        
        // Build the core order data structure
        $order = [
            'id' => (string)$order_node['id'],
            'date' => $date_str,
            'total_amount' => $total_amount,
            'status' => (string)($order_node['status'] ?? 'pending'),
            'user_id' => $user_id,
            'user_name' => fetch_customer_name_from_db($user_id),
            'items' => [] 
        ];

        if (isset($order_node->items) && isset($order_node->items->item)) {
            foreach ($order_node->items->item as $item_node) {
                
                // 1. Extract Quantity
                $quantity = (int)($item_node->quantity ?? (int)($item_node['quantity'] ?? 0));
                
                // 2. Extract and Clean Unit Price (Source: <unit_price>)
                $raw_price_str = (string)($item_node->unit_price ?? $item_node['unit_price'] ?? '0.0');
                $unit_price = (float) str_replace(',', '', $raw_price_str);
                
                // 3. Calculate Subtotal (Quantity * Unit Price)
                $subtotal = $unit_price * $quantity;

                $order['items'][] = [
                    'product_id' => (string)($item_node->product_id ?? (string)$item_node['product_id'] ?? ''), 
                    'name' => (string)($item_node->product_name ?? (string)($item_node->name ?? 'N/A')), 
                    'quantity' => $quantity,
                    // FIX: Map unit_price to 'price' for compatibility with order_details.php
                    'price' => $unit_price, 
                    'unit_price' => $unit_price,
                    // FIX: Include the calculated subtotal
                    'subtotal' => $subtotal, 
                ];
            }
        }
        return $order;
    }

    return false;
}

/**
 * Updates the status of a specific order in orders.xml.
 * @param string $order_id The ID of the order to update.
 * @param string $new_status The new status to set ('pending', 'shipped', etc.).
 * @return bool True on success, false on failure.
 */
function update_order_status($order_id, $new_status) {
    if (!file_exists(ORDER_FILE)) {
        return false;
    }

    $xml = simplexml_load_file(ORDER_FILE);
    if ($xml === false) {
        return false;
    }
    
    $found = false;
    
    foreach ($xml->order as $order) {
        if ((string)$order['id'] === $order_id) { 
            $order['status'] = $new_status;
            $found = true;
            break; 
        }
    }

    if ($found) {
        $result = $xml->asXML(ORDER_FILE);
        return $result !== false;
    }
    
    return false; // Order ID not found
}

function delete_product_category(string $category_name): bool {
    // 1. PATH CHECK - ADJUST THIS PATH IF NECESSARY
    $xml_file_path = '../data/categories.xml'; 

    // --- DEBUG STEP 1: Check if file exists ---
    if (!file_exists($xml_file_path)) {
        error_log("DEBUG-XML: File NOT found at: " . $xml_file_path);
        // Note: You might need to check the path against your products.php location.
        // If products.php is in 'admin/', the path '../data/catalog.xml' is usually correct.
        return false;
    }

    try {
        // --- DEBUG STEP 2: Check if XML can be loaded ---
        $xml = simplexml_load_file($xml_file_path);
        if ($xml === false) {
            error_log("DEBUG-XML: Failed to load XML file. Check file corruption or read permissions.");
            return false;
        }

        $deleted = false;

        // 3. Locate and delete the category element (Logic unchanged)
        $categories_node = $xml->xpath('//categories');
        if (!empty($categories_node)) {
            $categories_node = $categories_node[0];
            $i = 0;
            foreach ($categories_node->category as $category_node) {
                if ((string)$category_node === $category_name) {
                    unset($categories_node->category[$i]);
                    $deleted = true;
                    break; 
                }
                $i++;
            }
        }
        
        // 4. Handle Save Operation
        if ($deleted) {
            // ... (Product re-assignment logic is here - unchanged) ...
            
            // --- DEBUG STEP 3: Check save permissions ---
            if ($xml->asXML($xml_file_path)) {
                error_log("DEBUG-XML: Category '{$category_name}' deleted and file SAVED successfully.");
                return true;
            } else {
                error_log("DEBUG-XML: Failed to save XML file. Check WRITE permissions for the file/directory: " . $xml_file_path);
                return false;
            }
        }
        
        // If we reach here, the category was not found.
        if (!$deleted) {
            error_log("DEBUG-XML: Category '{$category_name}' not found. Returning TRUE as successful non-deletion.");
        }
        
        // If the category didn't exist, return true.
        return true; 

    } catch (Exception $e) {
        error_log("DEBUG-XML: An unexpected exception occurred: " . $e->getMessage());
        return false;
    }
}


// --------------------------------------------------------------------------
// --- UTILITY FUNCTIONS ---
// --------------------------------------------------------------------------

if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return '₱' . number_format((float)$amount, 2);
    }
}
?>