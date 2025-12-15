<?php
require('db.php');
session_start();

// 1. AUTO-LOGIN: Check if Session or Cookie exists
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
} elseif (isset($_COOKIE['admin_id'])) {
    $_SESSION['admin_id'] = $_COOKIE['admin_id'];
    header('Location: dashboard.php');
    exit();
}

// 2. LOGIN PROCESS
if (isset($_POST['submit'])) {
    // FIX: This line checks if the 'email' field was posted before trying to access it.
    // If it wasn't, we set it to an empty string to prevent the Warning.
    $email = isset($_POST['email']) ? trim($_POST['email']) : ''; 
    $password = sha1(trim($_POST['password']));

    // Table name is 'admin' and password column is 'password'
    $stmt = $con->prepare("SELECT * FROM admins WHERE email = ? AND password = ?");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $_SESSION['admin_id'] = $row['admin_id'];

        if (isset($_POST['remember'])) {
            setcookie('admin_id', $row['admin_id'], time() + (86400 * 30), "/"); 
        }

        header('Location: dashboard.php');
        exit();
    } else {
        $warning_msg[] = 'Incorrect email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form | Baked by the Crater</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="wrapper">
        <form method="POST" action="">
            <h1>Login</h1>
            
            <div class="input-box">
                <label class="login__label">Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
                <i class='bx bx-user login__icon'></i>
            </div>

            <div class="input-box">
                <label class="login__label">Password</label>
                <input type="password" id="passwordInput" name="password" placeholder="Enter your password" minlength="6" required>
                <i class='bx bx-lock login__icon'></i>
            </div>

            <div class="remember-forgot">
                <label><input type="checkbox" name="remember" value="1"> Remember Me</label>
                <a href="forgot_password.php">Forgot Password?</a> 
            </div>

            <button type="submit" name="submit" class="btn">Log-In</button>

        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <?php include 'alert.php';?>
</body>
</html> 