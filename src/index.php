<?php
/**
 * صفحه اصلی سیستم - Index
 */

require_once 'config.php';

// چک کردن ورود کاربر
if (isset($_SESSION['user_id'])) {
    // کاربر لاگین کرده، هدایت به داشبورد
    if ($_SESSION['is_admin']) {
        header('Location: admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// کاربر لاگین نکرده، هدایت به صفحه ورود
header('Location: sign.php');
exit;
?>