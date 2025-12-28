<?php
/**
 * صفحه اصلی سیستم
 * Main Index Page - Redirect to appropriate page
 */

require_once 'config.php';

// چک کردن وجود session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// اگر کاربر لاگین است، به داشبورد یا پنل ادمین هدایت می‌شود
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        redirect(SITE_URL . '/admin.php');
    } else {
        redirect(SITE_URL . '/dashboard.php');
    }
} else {
    // اگر لاگین نیست، به صفحه ورود هدایت می‌شود
    redirect(SITE_URL . '/sign.php');
}
?>