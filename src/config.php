<?php
/**
 * فایل پیکربندی اصلی سیستم ERP
 * eSmartis ERP System Configuration
 */

// تنظیمات امنیتی
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات زبان و زمان
date_default_timezone_set('Asia/Tehran');
setlocale(LC_ALL, 'fa_IR.UTF-8');
mb_internal_encoding('UTF-8');

// تنظیمات Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // در production باید 1 باشد
ini_set('session.gc_maxlifetime', 3600); // 1 ساعت

// ثابت‌های سیستم
define('SITE_ROOT', __DIR__);
define('SITE_URL', './erp');
define('SITE_TITLE', 'سیستم یکپارچه مدیریت - eSmartis');
define('SITE_LANG', 'fa');
define('SITE_DIRECTION', 'rtl');
define('DATE_FORMAT', 'Y/m/d');
define('DATETIME_FORMAT', 'Y/m/d H:i:s');

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_NAME', 'esmartis_erp');
define('DB_USER', 'esmartis_user');
define('DB_PASS', 'esmartis1364');
define('DB_CHARSET', 'utf8mb4');

// تنظیمات فایل
define('UPLOAD_DIR', SITE_ROOT . '/uploads');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip']);

// سطوح دسترسی
define('PERMISSION_NONE', 0);
define('PERMISSION_READ', 1);
define('PERMISSION_WRITE', 2);
define('PERMISSION_FULL', 3);

// وضعیت‌های مختلف
define('STATUS_DRAFT', 0);
define('STATUS_PENDING', 1);
define('STATUS_APPROVED', 2);
define('STATUS_REJECTED', 3);
define('STATUS_COMPLETED', 4);
define('STATUS_CANCELLED', 5);

// تنظیمات امنیتی
define('HASH_ALGO', PASSWORD_DEFAULT);
define('HASH_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_COOLDOWN', 180); // 3 دقیقه

// ماژول‌های سیستم
define('MODULES', [
    'admin' => 'مدیریت سیستم',
    'users' => 'کاربران',
    'contacts' => 'مخاطبین',
    'notes' => 'یادداشت‌ها',
    'calendar' => 'تقویم',
    'financial' => 'مالی',
    'projects' => 'پروژه‌ها',
    'contracts' => 'قراردادها',
    'hr' => 'منابع انسانی',
    'warehouse' => 'انبار',
    'procurement' => 'تدارکات',
    'engineering' => 'مهندسی',
    'production' => 'تولید',
    'qc' => 'کنترل کیفیت',
    'marketing' => 'بازرگانی',
    'sell' => 'فروش',
    'messenger' => 'پیام‌رسان',
    'meetings' => 'جلسات'
]);

// تابع autoload برای کلاس‌ها
spl_autoload_register(function ($class) {
    $file = SITE_ROOT . '/classes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// تابع helper برای escape کردن داده‌ها
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// تابع برای redirect
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// تابع برای چک کردن ورود کاربر
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        redirect(SITE_URL . '/sign.php');
    }
    
    // چک کردن timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_destroy();
        redirect(SITE_URL . '/sign.php?timeout=1');
    }
    
    $_SESSION['last_activity'] = time();
}

// تابع برای چک کردن مجوز
function check_permission($module, $level = PERMISSION_READ) {
    if (!isset($_SESSION['permissions'][$module])) {
        return false;
    }
    return $_SESSION['permissions'][$module] >= $level;
}

// تابع برای نمایش پیام
function show_message($msg, $type = 'info') {
    $types = ['success' => 'موفق', 'error' => 'خطا', 'warning' => 'هشدار', 'info' => 'اطلاعات'];
    $icons = ['success' => '✓', 'error' => '✗', 'warning' => '⚠', 'info' => 'ℹ'];
    
    return '<div class="alert alert-' . $type . '" role="alert">
        <span class="alert-icon">' . $icons[$type] . '</span>
        <span class="alert-text">' . h($msg) . '</span>
    </div>';
}

// تابع برای تولید CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// تابع برای چک کردن CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// تابع برای sanitize کردن ورودی
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return trim(strip_tags($data));
}

// تابع برای validate کردن ایمیل
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// تابع برای validate کردن شماره موبایل ایرانی
function validate_mobile($mobile) {
    return preg_match('/^09[0-9]{9}$/', $mobile);
}

// تابع برای تبدیل اعداد انگلیسی به فارسی
function en2fa($str) {
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace($en, $fa, $str);
}

// تابع برای تبدیل اعداد فارسی به انگلیسی
function fa2en($str) {
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($fa, $en, $str);
}

// شروع session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>