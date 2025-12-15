<?php
// Email verification endpoint: handles verification code input via POST and updates status in the database

// Database Connection (Loads $con variable)
require_once 'db.php';
global $con; // Make sure $con is available

$message = '';
$success = false;

// Check if the verification form was submitted
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['verify'])) {
    $code = trim($_POST['verification_code'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($code === '' || $email === '') {
        $message = 'Please enter both your email and the verification code.';
    } else {
        // --- FIX: Step 1. Find the token (regardless of expiration) to provide a specific error message ---
        $sql_find = "
            SELECT u.id, u.is_verified, t.expires_at 
            FROM users u
            JOIN user_tokens t ON u.id = t.user_id
            WHERE u.email = ? AND t.token = ? AND t.type = 'VERIFICATION'
        ";
        $stmt_find = mysqli_prepare($con, $sql_find);
        mysqli_stmt_bind_param($stmt_find, "ss", $email, $code);
        mysqli_stmt_execute($stmt_find);
        $result_find = mysqli_stmt_get_result($stmt_find);
        $user_data = mysqli_fetch_assoc($result_find);
        mysqli_stmt_close($stmt_find);
        
        if (!$user_data) {
            // Case 1: Token not found (wrong code/email or token deleted)
            // Nagiging malinaw na Invalid Code ang problema, hindi expiration.
            $message = 'Invalid verification code or email combination.';

        } else {
            // Case 2: Token is found, now check expiration and status
            $current_time = time();
            $expiry_time = strtotime($user_data['expires_at']);
            
            if ($user_data['is_verified'] == 1) {
                // Case 3: User is already verified
                $message = 'This account is already verified! You can proceed to log in.';
                $success = true;
                
            } elseif ($current_time > $expiry_time) {
                // Case 4: Token is correct but EXPIRED -> Provide specific error
                $message = 'The verification code has **expired**. Please re-register or use a "Resend Code" option to receive a new one.';
                
                // Optional: Delete the expired token after informing the user
                $user_id = $user_data['id'];
                $stmt_delete = mysqli_prepare($con, "DELETE FROM user_tokens WHERE user_id = ? AND type = 'VERIFICATION' AND expires_at < NOW()");
                mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);
                
            } else {
                // Case 5: Token is valid and not expired -> PROCEED TO VERIFY
                $user_id = $user_data['id'];

                // 1. Mark user as verified
                $stmt_update = mysqli_prepare($con, "UPDATE users SET is_verified = 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt_update, "i", $user_id);
                $update_success = mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);

                if ($update_success) {
                    // 2. Delete the used token
                    $stmt_delete_token = mysqli_prepare($con, "DELETE FROM user_tokens WHERE user_id = ? AND type = 'VERIFICATION'");
                    mysqli_stmt_bind_param($stmt_delete_token, "i", $user_id);
                    mysqli_stmt_execute($stmt_delete_token);
                    mysqli_stmt_close($stmt_delete_token);

                    $success = true;
                    $message = 'Your account has been successfully verified! You can now log in.';
                } else {
                    $message = 'Verification failed: Could not update user status. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - Baked by the Crater</title>
    <link rel="stylesheet" href="style.css">
    </head>
<body>
    <div class="status-box">
        <header class="header">
            <div class="brand">CRATER BAKERY</div>
        </header>

        <?php if ($success): ?>
            <p class="status success">VERIFICATION SUCCESSFUL</p>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php" class="btn">Go to Login</a>
        <?php elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $message !== ''): ?> 
             <p class="status error">VERIFICATION ERROR</p>
            <p><?php echo htmlspecialchars($message); ?></p>
            <?php if (strpos($message, 'expired') === false): // Show register/login link if not expired error ?>
                <a href="login.php" class="btn">Go to Login</a>
            <?php else: // Show registration link for expired code ?>
                <a href="register.php" class="btn">Try Registering Again</a>
            <?php endif; ?>
        <?php else: ?>
            <p class="status">ENTER VERIFICATION CODE</p>
            <p>Please enter the verification code sent to your email address.</p>

            <form method="POST" action="verify.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <?php 
                        // Get email from POST (if submitted and failed) OR from GET (if redirected from register.php)
                        $default_email = htmlspecialchars($_POST['email'] ?? ($_GET['email'] ?? '')); 
                    ?>
                    <input type="email" id="email" name="email" required value="<?php echo $default_email; ?>">
                </div>
                <div class="form-group">
                    <label for="verification_code">Verification Code</label>
                    <input type="text" id="verification_code" name="verification_code" required>
                </div>
                <button type="submit" name="verify" class="btn-primary">Verify Account</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>