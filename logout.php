<?php
session_start();

// ── Secure logout (Task 3) ────────────────────────────────────────────────────
// 1. Clear all session variables
$_SESSION = [];

// 2. Delete the session cookie from the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy the session on the server
session_destroy();

// Redirect to login with logout success flag
header("Location: login.php?logout=1");
exit();
?>
