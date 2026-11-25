<?php
// logout.php
declare(strict_types=1);
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/helpers/functions.php';

if (isset($_SESSION['user_id'])) {
    log_audit('user_logout', $_SESSION['user_id'], null, 'user', $_SESSION['user_id']);
}

// 1. Unset all session variables
$_SESSION = [];

// 2. Clear session cookie
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

// 3. Clear remember token cookie if set
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// 4. Destroy PHP session completely
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 5. Start clean session for flash notification and redirect to login
session_start();
flash('info', 'You have been successfully signed out.');
redirect('login.php');
