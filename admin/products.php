<?php
// products.php
require('db.php'); // Now includes format_currency()
require('xml_utils.php'); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define the root upload directory (one level up from /admin/)
const UPLOAD_DIR = '../uploads/';

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
if ($con !== false && isset($con) && $con instanceof mysqli) {
    $stmt = $con->prepare("SELECT name FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin_name = htmlspecialchars($result->fetch_assoc()['name']);
    }
    if (isset($stmt)) $stmt->close();
}


// --- FETCH CATEGORIES ---
// NOTE: Assuming get_product_categories() exists and works (mock or real)
$categories = get_product_categories();
$category_options = array_combine($categories, $categories);


// --- STATE MANAGEMENT ---
$message = '';
$message_type = 'msg-ok';
$is_edit_mode = false;
$edit_product = [];

// --------------------------------------------------------------------------
// --- HANDLE ACTIONS (ADD CATEGORY, ADD/EDIT PRODUCT, DELETE PRODUCT) ---
// --------------------------------------------------------------------------

// 1. HANDLE CATEGORY SUBMISSION 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $new_category = trim($_POST['new_category'] ?? '');
    
    // NOTE: Assuming add_product_category() exists and works (mock or real)
    if (empty($new_category)) {
        $message = "Error: Category name cannot be empty.";
        $message_type = 'msg-err';
    } elseif (add_product_category($new_category)) {
        $message = "Category '{$new_category}' added successfully! Reloading categories...";
        // Re-fetch categories to update the list and dropdown
        $categories = get_product_categories();
        $category_options = array_combine($categories, $categories);
    } else {
        $message = "Error adding category. It might already exist or a file error occurred.";
        $message_type = 'msg-err';
    }
}

// 1b. HANDLE CATEGORY DELETION (ADDED BLOCK)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $category_to_delete = trim($_POST['category_name'] ?? '');
    
    // NOTE: Assuming delete_product_category() exists and works (mock or real)
    if (empty($category_to_delete)) {
        $message = "Error: Category name for deletion cannot be empty.";
        $message_type = 'msg-err';
    } elseif (delete_product_category($category_to_delete)) {
        // NOTE: Also assuming that deleting a category removes it from products
        // or assigns them to a 'General' category in the background function.
        $message = "Category '{$category_to_delete}' deleted successfully! Reloading categories...";
        // Re-fetch categories to update the list and dropdown
        $categories = get_product_categories();
        $category_options = array_combine($categories, $categories);
    } else {
        $message = "Error deleting category '{$category_to_delete}'. It might not exist or a file error occurred.";
        $message_type = 'msg-err';
    }
}


// 2. HANDLE ADD/UPDATE SUBMISSION (PRODUCT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'add' || ($_POST['action'] ?? '') === 'update')) {
    $product_data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price' => (float) ($_POST['price'] ?? 0),
        'stock' => (int) ($_POST['stock'] ?? 0),
        'category' => trim($_POST['category'] ?? ''),
    ];
    $product_id = (int) ($_POST['product_id'] ?? 0);

    // --- IMAGE HANDLING LOGIC ---
    $image_path_to_save = trim($_POST['existing_image_path'] ?? ''); 
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $file_name = basename($file['name']);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
             $message = "Error: Only JPG, JPEG, PNG, and GIF files are allowed.";
             $message_type = 'msg-err';
             goto end_of_post_check; 
        }

        $new_filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = UPLOAD_DIR . $new_filename;
        
        if (!is_dir(UPLOAD_DIR)) {
            if (!@mkdir(UPLOAD_DIR, 0777, true)) {
                 $message = "Error: Failed to create upload directory. Check folder permissions for: " . UPLOAD_DIR;
                 $message_type = 'msg-err';
                 goto end_of_post_check; 
            }
        }

        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $image_path_to_save = str_replace('../', '', $target_file); 
            
            if (!empty($_POST['existing_image_path'])) {
                $old_file_path = '../' . $_POST['existing_image_path']; 
                if (file_exists($old_file_path) && is_file($old_file_path)) {
                    @unlink($old_file_path);
                }
            }
        } else {
            $message = "Error uploading image.";
            $message_type = 'msg-err';
            if ($_POST['action'] === 'add') { goto end_of_post_check; } 
        }
    }
    
    $product_data['image_path'] = $image_path_to_save;

    // NOTE: Assuming add_product_to_xml() and update_product_in_xml() exist and work (mock or real)
    if (empty($product_data['name']) || $product_data['price'] <= 0) {
        $message = "Error: Product name and price are required.";
        $message_type = 'msg-err';
    } else {
        if ($_POST['action'] === 'add') {
            if (add_product_to_xml($product_data)) {
                $message = "Product '{$product_data['name']}' added successfully!";
            } else {
                $message = "Error adding product to the catalog.";
                $message_type = 'msg-err';
            }
        } elseif ($_POST['action'] === 'update' && $product_id > 0) {
            if (update_product_in_xml($product_id, $product_data)) {
                $message = "Product ID #{$product_id} updated successfully!";
            } else {
                $message = "Error updating product ID #{$product_id}. Product not found or file error.";
                $message_type = 'msg-err';
            }
        }
    }
    
    end_of_post_check:
}


// 3. HANDLE DELETE REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $product_id = (int) ($_POST['product_id'] ?? 0);
    if ($product_id > 0) {
        // NOTE: Assuming delete_product_from_xml() exists and works (mock or real)
        if (delete_product_from_xml($product_id)) {
            $message = "Product ID #{$product_id} deleted successfully (including associated image).";
        } else {
            $message = "Error deleting product ID #{$product_id}. Product not found or file error.";
            $message_type = 'msg-err';
        }
    }
}

// 4. HANDLE EDIT REQUEST (GET request to load form data)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int) $_GET['id'];
    // NOTE: Assuming get_product_by_id() exists and works (mock or real)
    $edit_product = get_product_by_id($edit_id);
    if ($edit_product) {
        $is_edit_mode = true;
    } else {
        $message = "Error: Product ID #{$edit_id} not found.";
        $message_type = 'msg-err';
    }
}

// --------------------------------------------------------------------------
// --- LOAD ALL PRODUCTS FOR DISPLAY ---
// --------------------------------------------------------------------------
// NOTE: Assuming get_product_catalog_data() exists and works (mock or real)
$products = get_product_catalog_data();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | The Malvar BatCave</title>
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
                <a href="products.php" class="active"><i class='bx bxs-box'></i> Products</a>
                <a href="orders.php"><i class='bx bxs-cart-alt'></i> Orders</a>
                <a href="users.php"><i class='bx bxs-group'></i> Users</a>
                <a href="chats.php"><i class='bx bxs-chat'></i> Chats</a> 
                <a href="settings.php"><i class='bx bxs-cog'></i> Settings</a>
            </nav>
            <a href="logout.php" class="logout-btn"><i class='bx bx-log-out'></i> Logout</a>
        </aside>

        <main class="main-content">
            
            <header class="header">
                <h2>Product Management</h2>
                <div class="profile">
                    <i class='bx bxs-user-circle'></i>
                </div>
            </header>

            <?php if (!empty($message)): ?>
                <div class="msg <?= $message_type; ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <div class="content-grid product-forms-grid"> 

                <div class="card product-form-card">
                    <h4><?= $is_edit_mode ? 'Edit Product ID #' . htmlspecialchars($edit_product['id']) : 'Add New Product'; ?></h4>
                    
                    <form method="POST" action="products.php" enctype="multipart/form-data"> 
                        <input type="hidden" name="action" value="<?= $is_edit_mode ? 'update' : 'add'; ?>">
                        <?php if ($is_edit_mode): ?>
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($edit_product['id']); ?>">
                            <input type="hidden" name="existing_image_path" value="<?= htmlspecialchars($edit_product['image_path'] ?? ''); ?>">
                        <?php endif; ?>

                        <div class="form-group-flex">
                            <div class="form-field-half">
                                <label for="name">Product Name</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($edit_product['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-field-half">
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <?php if (empty($category_options)): ?>
                                        <option value="">-- Add Category First --</option>
                                    <?php endif; ?>
                                    <?php foreach ($category_options as $value => $label): ?>
                                        <option value="<?= $value; ?>" 
                                            <?= ($edit_product['category'] ?? '') === $value ? 'selected' : ''; ?>>
                                            <?= $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <label for="description">Description (Optional)</label>
                        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                        
                        <div class="form-group-flex image-upload-group">
                            <div class="form-field-half">
                                <label for="product_image">Product Image (JPG, PNG, GIF)</label>
                                <input type="file" id="product_image" name="product_image" accept="image/*" <?= !$is_edit_mode ? 'required' : ''; ?>>
                                <?php if ($is_edit_mode && !empty($edit_product['image_path'])): ?>
                                    <p class="text-muted" style="font-size: 0.8rem; margin-top: -10px;">
                                        Current Image: <a href="../<?= htmlspecialchars($edit_product['image_path']); ?>" target="_blank">View</a> | Upload a new file to replace it.
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="form-field-half">
                                <label for="price">Price (₱)</label>
                                <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?= htmlspecialchars($edit_product['price'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="stock">Stock Quantity</label>
                            <input type="number" id="stock" name="stock" min="0" value="<?= htmlspecialchars($edit_product['stock'] ?? ''); ?>" required>
                        </div>
                        

                        <button type="submit" class="btn"><?= $is_edit_mode ? 'Save Changes' : 'Add Product'; ?></button>
                        <?php if ($is_edit_mode): ?>
                            <a href="products.php" class="btn btn-primary" style="margin-top: 10px;">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div> 
                
                <div class="card category-manager-card">
                    <h4>Add New Category</h4>
                    
                    <form method="POST" action="products.php" id="addCategoryForm">
                        <input type="hidden" name="action" value="add_category">
                        <label for="new_category">Category Name</label>
                        <input type="text" id="new_category" name="new_category" placeholder="e.g., 'Gluten-Free'" required>
                        <button type="submit" class="btn">Insert Category</button>
                    </form>

                    <div class="category-list-container">
                        <label>Existing Categories (<?= count($categories); ?>)</label>
                        <ul class="category-list">
                            <?php if (empty($categories)): ?>
                                <li>No categories defined.</li>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <li class="category-list-item">
                                        <?= htmlspecialchars($cat); ?>
                                        <form method="POST" action="products.php" 
                                              style="display: inline-block;"
                                              onsubmit="return confirm('Are you sure you want to delete the category: <?= htmlspecialchars($cat); ?>? NOTE: This may affect products currently using this category.');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_name" value="<?= htmlspecialchars($cat); ?>">
                                            <button type="submit" class="btn btn-small action-delete" style="width: auto; height: 20px; line-height: 20px; padding: 0 5px; font-size: 0.7em;">
                                                <i class='bx bx-x' style="font-size: 0.8em; vertical-align: middle;"></i> Delete
                                            </button>
                                        </form>
                                        </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div> 
            </div> 
            
            <section class="recent-activity" style="margin-top: 20px;">
                <h3>Current Products (<?= count($products); ?>)</h3>
                <div class="activity-box">
                    <?php if (empty($products)): ?>
                        <p>No products currently in the catalog. Add one above.</p>
                    <?php else: ?>
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Image</th> <th>ID</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th class="actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <?php
                                        $stock = (int) $product['stock'];
                                        $status_class = 'in-stock';
                                        $status_text = 'In Stock';
                                        if ($stock <= 0) {
                                            $status_class = 'cancelled'; // Reused cancelled style for 'Out of Stock'
                                            $status_text = 'Out of Stock';
                                        } elseif ($stock < 10) {
                                            $status_class = 'low-stock';
                                            $status_text = 'Low Stock';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($product['image_path'])): ?>
                                                <img src="../<?= htmlspecialchars($product['image_path']); ?>" alt="<?= htmlspecialchars($product['name']); ?>" class="product-thumb">
                                            <?php else: ?>
                                                <i class='bx bxs-box product-thumb-icon'></i>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td><?= htmlspecialchars($product['id']); ?></td>
                                        <td><?= htmlspecialchars($product['name']); ?></td>
                                        <td><?= htmlspecialchars($product['category']); ?></td>
                                        <td><?= format_currency($product['price']); ?></td>
                                        <td><?= htmlspecialchars($stock); ?></td>
                                        <td><span class="stock-status <?= $status_class; ?>"><?= $status_text; ?></span></td>
                                       <td class="action-column text-center">
                                            <a href="products.php?action=edit&id=<?= htmlspecialchars($product['id']); ?>" class="btn btn-small action-edit">Edit</a>
                                            
                                            <form method="POST" action="products.php" onsubmit="return confirm('Are you sure you want to delete product #<?= htmlspecialchars($product['id']); ?>: <?= htmlspecialchars($product['name']); ?>? NOTE: This will delete the product image file.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']); ?>">
                                                <button type="submit" class="btn btn-small action-delete">Delete</button>
                                            </form>
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