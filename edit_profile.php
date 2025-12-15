<?php
// 1. Start the session and include database connection
session_start();
include 'db.php'; // Assumed to define $con (MySQLi connection object)

// 2. Check if the user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

$user_id = $_SESSION['user_id'];
$user_data = [];
$message = ''; // Success message
$error = '';   // Error message

// --- 3. Handle Form Submission (POST Request) ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 3a. Sanitize and validate mandatory inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['new_password'] ?? '';
    $password_confirm = $_POST['confirm_password'] ?? '';

    // Re-populate user_data with POSTed values in case of validation error
    $user_data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'address' => $address,
        'email' => $_POST['email_display'] ?? '' // Email is read-only, but grab it for display
    ];

    // Basic Validation Checks
    if (empty($first_name) || empty($last_name) || empty($phone) || empty($address)) {
        $error = "All fields (except New Password) are required.";
    } elseif (!empty($password) && $password !== $password_confirm) {
        $error = "New password and confirmation do not match.";
    } elseif (!empty($password) && strlen($password) < 8) {
        $error = "New password must be at least 8 characters long.";
    }

    if (empty($error)) {
        // 3b. Prepare the UPDATE query
        if (!empty($password)) {
            // Case 1: Updating profile AND password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, password_hash = ? WHERE id = ?";
            $stmt = $con->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssssi", $first_name, $last_name, $phone, $address, $password_hash, $user_id);
            }
        } else {
            // Case 2: Updating only profile details
            $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?";
            $stmt = $con->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $address, $user_id);
            }
        }

        if ($stmt) {
            if ($stmt->execute()) {
                $message = "Profile updated successfully!" . (!empty($password) ? " Your password has also been changed." : "");
                // Note: The form will display updated $user_data thanks to array re-population above.
            } else {
                $error = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database prepare error: " . $con->error;
        }
    }
}


// --- 4. Fetch Current User Data (Initial GET or after POST) ---
// We must always fetch fresh data to fill the form correctly, especially if the POST was successful.
if (empty($user_data['email']) || (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') || !empty($message)) {
    if ($stmt = $con->prepare("SELECT email, first_name, last_name, phone, address FROM users WHERE id = ?")) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Prioritize POST data in case of error, otherwise use fresh DB data
                $user_data = array_merge($row, $user_data); 
            } else {
                $error = "User profile could not be found. Please log out and try again.";
            }
        } else {
            $error = "Error fetching user data: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Database prepare error: " . $con->error;
    }
}

// --- 5. Setup HTML/Layout ---
$page_title = "Edit Profile";
$active_page = 'profile'; 

include 'header.php'; 

?>
<main>
    <section class="page-content">
        <div class="container" style="display: flex; justify-content: center;">

            <div class="login-box" style="padding: 30px;"> 
                <div class="header">
                    <div class="brand">Edit Your Profile</div>
                    <div class="subtitle">Update your personal details and contact information.</div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <?php if (empty($error) || ($error && $user_data)): // Only show form if data is available ?>

                    <form action="edit_profile.php" method="POST">
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="hidden" name="email_display" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"> 
                            <input type="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" disabled>
                            <small class="text-muted" style="display: block; margin-top: 5px; font-size: 11px;">Email cannot be changed here.</small>
                        </div>

                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone <span class="required">*</span></label>
                            <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Shipping Address <span class="required">*</span></label>
                            <textarea id="address" name="address" required><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="divider"><span>Optional Password Change</span></div>

                        <div class="form-group">
                            <label for="new_password">New Password (Leave blank to keep current)</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">Save Changes</button>
                        
                        <div style="margin-top: 15px; text-align: center;">
                            <a href="profile.php" class="btn-tertiary">Cancel / Back to Profile</a>
                        </div>
                    </form>

                <?php endif; ?>

            </div> </div> </section>
</main>
<?php 
include 'footer.php'; 
?>