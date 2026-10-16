<?php
/**
 * CivicLens — Secure Logout Handler
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Clear session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to home page
header("Location: index.php");
exit;
