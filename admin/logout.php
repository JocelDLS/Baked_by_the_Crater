<?php
session_start();

// 1. Unset Session
$_SESSION = array();
session_destroy();

// 2. Destroy Cookie (Set time to past)
if (isset($_COOKIE['admin_id'])) {
    unset($_COOKIE['admin_id']); 
    setcookie('admin_id', '', time() - 3600, '/'); 
}

// 3. Redirect
header("Location: login.php");
exit();
?>