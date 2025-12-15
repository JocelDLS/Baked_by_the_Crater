<?php
// 1. Start the session
// This is necessary to access session variables and functions.
session_start();

// 2. Unset all session variables
// This clears the $_SESSION array.
$_SESSION = array();

// 3. Destroy the session cookie
// If you are using session cookies, this line makes sure the session ID is no longer valid.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session itself
// This physically removes the session data from the server.
session_destroy();

// 5. Redirect the user
// Redirect the user to the login page. The 'logout' parameter is used by login.php
// (as seen in your code snippet) to display the "You have been successfully logged out" message.
header("Location: index.php?logout");
exit;
?>  