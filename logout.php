<?php
// logout.php
require_once __DIR__ . '/config/config.php'; // Garante que a sessão está iniciada e SITE_URL está definido

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, 
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// SITE_URL definido em config.php
header('Location: ' . SITE_URL . '/index.html?status=logout_success');
exit;
