<?php
/**
 * خروج از سیستم
 * Logout and Session Destruction
 */

require_once 'config.php';
require_once 'dbc.php';

// شروع session اگر شروع نشده باشد
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ثبت لاگ خروج
if (isset($_SESSION['user_id'])) {
    try {
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'logout',
            'module' => 'auth',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // خطا در ثبت لاگ را نادیده می‌گیریم
        error_log("Logout log error: " . $e->getMessage());
    }
    
    // حذف remember token از دیتابیس
    if (isset($_COOKIE['remember_token'])) {
        try {
            db()->update('users', [
                'remember_token' => null
            ], 'id = :id', [':id' => $_SESSION['user_id']]);
        } catch (Exception $e) {
            error_log("Remove remember token error: " . $e->getMessage());
        }
    }
}

// حذف remember token cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// نابود کردن تمام session variables
$_SESSION = array();

// حذف session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// نابود کردن session
session_destroy();

// هدایت به صفحه ورود
redirect(SITE_URL . '/sign.php?logout=1');
?>