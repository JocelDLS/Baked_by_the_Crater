<?php
session_start();
require_once 'db.php';
global $con;

date_default_timezone_set('Asia/Manila');

$message = '';
$email = '';
$user_id = null;

/* ---------------------------------------------------------
   DETERMINE STEP CORRECTLY
--------------------------------------------------------- */
if (isset($_SESSION['reset_email']) && isset($_SESSION['reset_user_id'])) {
    $current_step = 'reset_password';
    $email = $_SESSION['reset_email'];
    $user_id = $_SESSION['reset_user_id'];
} else {
    $current_step = 'verify_code';
}

/* ---------------------------------------------------------
   STEP 1 — VERIFY CODE
--------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['verify_code']) && $current_step === 'verify_code') {

    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['reset_code'] ?? '');

    if ($email === '' || $code === '') {
        $message = 'Please enter both your email and the reset code.';
    } else {

        $sql_find = "
            SELECT u.id, u.email, t.expires_at
            FROM users u
            JOIN user_tokens t ON u.id = t.user_id
            WHERE LOWER(u.email) = LOWER(?)
              AND t.token = ?
              AND t.type = 'PASSWORD_RESET'
        ";

        $stmt = mysqli_prepare($con, $sql_find);
        mysqli_stmt_bind_param($stmt, "ss", $email, $code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$row) {
            $message = 'Invalid email or reset code.';
        } else {
            if (time() > strtotime($row['expires_at'])) {
                $message = 'The reset code has expired. Please request a new one.';
            } else {
                $_SESSION['reset_email'] = $row['email'];
                $_SESSION['reset_user_id'] = $row['id'];

                header("Location: reset_password.php");
                exit;
            }
        }
    }
}

/* ---------------------------------------------------------
   STEP 2 — UPDATE PASSWORD
--------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['reset_password']) && $current_step === 'reset_password') {

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password === '' || $confirm_password === '') {
        $message = 'Both password fields are required.';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
    } else {

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = mysqli_prepare($con, "UPDATE users SET password_hash = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $password_hash, $user_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            $stmt_del = mysqli_prepare($con, "DELETE FROM user_tokens WHERE user_id = ? AND type='PASSWORD_RESET'");
            mysqli_stmt_bind_param($stmt_del, "i", $user_id);
            mysqli_stmt_execute($stmt_del);
            mysqli_stmt_close($stmt_del);

            unset($_SESSION['reset_email'], $_SESSION['reset_user_id']);

            header("Location: login.php?reset_success=1");
            exit;
        } else {
            $message = 'Something went wrong updating your password.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Baked by the Crater</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="login-box">
    <div class="header">
        <h1>Baked by the Crater</h1>
        <p>Password Reset</p>
    </div>

    <?php if ($message): ?>
        <p class="alert alert-error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($current_step === 'verify_code'): ?>

        <form method="POST" action="reset_password.php">
            <p class="subtitle">Verify the 6-digit code sent to your email.</p>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? ($_GET['email'] ?? '')) ?>"
                       required>
            </div>

            <div class="form-group">
                <label>6-Digit Reset Code</label>
                <input type="text" name="reset_code" required>
            </div>

            <button type="submit" name="verify_code" class="btn-primary">Verify Code</button>
        </form>

        <div class="login-link">
            Didn’t get the code? <a href="forgot_password.php">Resend Code</a>
        </div>

    <?php else: ?>

        <form method="POST" action="reset_password.php">
            <p class="subtitle">Set your new password for <b><?= htmlspecialchars($email) ?></b></p>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button type="submit" name="reset_password" class="btn-primary">Set New Password</button>
        </form>

        <div class="login-link">
            Return to <a href="login.php">Sign in</a>
        </div>

    <?php endif; ?>

</div>

</body>
</html>
