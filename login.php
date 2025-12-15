<?php
session_start();

// Database Connection (Loads $con variable)
require_once 'db.php';
global $con; // Make sure $con is available

// Configuration
define('SITE_URL', 'http://localhost');
define('GOOGLE_CLIENT_ID', '127969994010-0celbu5s9hk69daminm6ob43otafc0mv.apps.googleusercontent.com'); // Client ID updated

$error = '';
$success = '';

// Check for redirects from register.php or google_callback.php
// *** FIX: Removed redundant 'verification_needed' message since user is redirected to verify.php ***
// if (isset($_GET['verification_needed'])) {
//     $success = 'Registration successful! Please check your email for a verification code and enter it on the verification page to complete your account activation.';
// }
if (isset($_GET['mail_error'])) {
    $error = 'There was an issue sending the verification email. Please try registering again or contact support.';
}
if (isset($_GET['reset_success'])) {
    $success = 'Password reset successful! You can now log in with your new password.';
}
if (isset($_GET['logout'])) {
    $success = 'You have been successfully logged out.';
}


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        // Use prepared statement to find user by email
        $sql = "SELECT id, password_hash, first_name, is_verified, provider FROM users WHERE email = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($user_data) {
            // User found, verify password
            if (password_verify($password, $user_data['password_hash'])) {
                if ($user_data['is_verified'] == 0) {
                    $error = 'Your account is registered but not yet verified. Please check your email for the verification code.';
                    // Optionally redirect to verification page here: header('Location: verify.php?email=' . urlencode($email));
                } else {
                    // Success! Start session
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['email'] = $email;
                    $_SESSION['first_name'] = $user_data['first_name'];
                    $_SESSION['logged_in'] = true;

                    // Update last_login timestamp
                    $stmt_update_login = mysqli_prepare($con, "UPDATE users SET last_login = NOW() WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_update_login, "i", $user_data['id']);
                    mysqli_stmt_execute($stmt_update_login);
                    mysqli_stmt_close($stmt_update_login);

                    header('Location: index.php'); // Redirect to dashboard/home
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Baked by the Crater</title>
    <link rel="stylesheet" href="style.css">
    <!-- Required meta tag for Google Sign-In -->
    <meta name="google-signin-client_id" content="<?php echo GOOGLE_CLIENT_ID; ?>">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <div class="login-box">
        <div class="header">
            <h1>Baked by the Crater</h1> <p>Sign in</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="action-links">
                <a href="forgot_password.php">Forgot password?</a>
            </div>

            <button type="submit" name="login" class="btn-primary">Sign in</button>
        </form>

        <div class="divider"><span>or continue with</span></div>

        <div class="sso-buttons">
            <div id="googleButton"></div>
        </div>

        <div class="footer-link">
            Don't have an account? <a href="register.php">Create one</a>
        </div>
    </div>

    <script>
        function handleCredentialResponse(response) {
            // Send the credential (JWT) to google_callback.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'google_callback.php';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'credential';
            input.value = response.credential;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        window.onload = function () {
            if (window.google && window.google.accounts && window.google.accounts.id) {
                google.accounts.id.initialize({
                    client_id: "<?php echo GOOGLE_CLIENT_ID; ?>",
                    callback: handleCredentialResponse
                });

                google.accounts.id.renderButton(
                    document.getElementById("googleButton"),
                    {
                        theme: "filled_black",
                        size: "large",
                        width: 400,
                        text: "continue_with"
                    }
                );
            }
        }
    </script>
</body>
</html>
