<?php
/**
 * خروج از سیستم
 */

require_once 'config.php';
require_once 'dbc.php';

// ثبت لاگ خروج
if (isset($_SESSION['user_id'])) {
    db()->insert('logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'logout',
        'module' => 'auth',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // پاک کردن remember token
    db()->update('users', [
        'remember_token' => null
    ], 'id = :id', [':id' => $_SESSION['user_id']]);
}

// پاک کردن cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// نابود کردن session
session_destroy();

// هدایت به صفحه ورود
header('Location: sign.php?logout=1');
exit;
?>