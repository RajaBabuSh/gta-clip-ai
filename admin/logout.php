<?php
// admin/logout.php
declare(strict_types=1);
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/helpers/functions.php';

if (isset($_SESSION['admin_id'])) {
    log_audit('admin_logout', null, $_SESSION['admin_id'], 'admin', $_SESSION['admin_id']);
}

$_SESSION = [];

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

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

session_start();
flash('info', 'Admin signed out securely.');
redirect('admin/login.php');
