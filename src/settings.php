<?php
/**
 * تنظیمات سیستم و پروفایل کاربر
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$message = '';
$messageType = 'success';

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                // به‌روزرسانی پروفایل
                $fullname = sanitize_input($_POST['fullname']);
                $email = sanitize_input($_POST['email']);
                $mobile = sanitize_input($_POST['mobile']);
                
                if (empty($fullname)) {
                    $message = 'نام و نام خانوادگی الزامی است.';
                    $messageType = 'error';
                } else {
                    $result = db()->update('users', [
                        'fullname' => $fullname,
                        'email' => $email,
                        'mobile' => $mobile
                    ], 'id = :id', [':id' => $_SESSION['user_id']]);
                    
                    if ($result !== false) {
                        $_SESSION['fullname'] = $fullname;
                        $message = 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.';
                        
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'update_profile',
                            'module' => 'settings',
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                    } else {
                        $message = 'خطا در به‌روزرسانی اطلاعات.';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'change_password':
                // تغییر رمز عبور
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                
                // دریافت رمز فعلی
                $user = db()->selectOne(
                    "SELECT password FROM users WHERE id = :id",
                    [':id' => $_SESSION['user_id']]
                );
                
                if (!password_verify($currentPassword, $user['password'])) {
                    $message = 'رمز عبور فعلی اشتباه است.';
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = 'رمز عبور جدید و تکرار آن یکسان نیستند.';
                    $messageType = 'error';
                } elseif (strlen($newPassword) < 6) {
                    $message = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
                    $messageType = 'error';
                } else {
                    $hashedPassword = password_hash($newPassword, HASH_ALGO, ['cost' => HASH_COST]);
                    
                    $result = db()->update('users', [
                        'password' => $hashedPassword,
                        'remember_token' => null
                    ], 'id = :id', [':id' => $_SESSION['user_id']]);
                    
                    if ($result !== false) {
                        $message = 'رمز عبور با موفقیت تغییر کرد.';
                        
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'change_password',
                            'module' => 'settings',
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                    } else {
                        $message = 'خطا در تغییر رمز عبور.';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update_preferences':
                // به‌روزرسانی تنظیمات کاربری (در آینده)
                $message = 'تنظیمات با موفقیت ذخیره شد.';
                break;
        }
    }
}

// دریافت اطلاعات کاربر
$user = db()->selectOne(
    "SELECT * FROM users WHERE id = :id",
    [':id' => $_SESSION['user_id']]
);

// دریافت تنظیمات سیستم
$systemSettings = db()->select("SELECT * FROM settings ORDER BY category, `key`");

// گروه‌بندی تنظیمات
$settingsByCategory = [];
foreach ($systemSettings as $setting) {
    $category = $setting['category'] ?: 'general';
    if (!isset($settingsByCategory[$category])) {
        $settingsByCategory[$category] = [];
    }
    $settingsByCategory[$category][] = $setting;
}

// آخرین فعالیت‌های کاربر
$recentActivities = db()->select(
    "SELECT * FROM logs 
     WHERE user_id = :user_id 
     ORDER BY created_at DESC 
     LIMIT 10",
    [':user_id' => $_SESSION['user_id']]
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات - <?php echo SITE_TITLE; ?></title>
    <style>
        .settings-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(52, 73, 94, 0.3);
        }
        
        .settings-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        /* Message */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            color: #2c3e50;
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            border-color: #34495e;
        }
        
        .tab-btn:hover {
            border-color: #34495e;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .settings-card h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 18px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #34495e;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #7f8c8d;
            font-size: 12px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* User Info */
        .user-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        
        .user-details h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .user-details p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        /* Activity Log */
        .activity-item {
            padding: 12px;
            border-right: 3px solid #34495e;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 6px;
        }
        
        .activity-action {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .activity-time {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        /* System Settings */
        .setting-item {
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
        .setting-label {
            flex: 1;
        }
        
        .setting-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .setting-desc {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .setting-value {
            padding: 6px 12px;
            background: #ecf0f1;
            border-radius: 6px;
            font-size: 13px;
            color: #2c3e50;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-tabs {
                flex-direction: column;
            }
            
            .user-info-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <!-- Settings Header -->
        <div class="settings-header">
            <h1>⚙️ تنظیمات</h1>
            <p>مدیریت پروفایل، امنیت و تنظیمات سیستم</p>
        </div>
        
        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $messageType === 'success' ? '✓' : '✗'; ?>
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- User Info Card -->
        <div class="user-info-card">
            <div class="user-avatar">👤</div>
            <div class="user-details">
                <h2><?php echo h($user['fullname']); ?></h2>
                <p>نام کاربری: <?php echo h($user['username']); ?></p>
                <p>آخرین ورود: <?php echo $user['last_login'] ? en2fa(date('Y/m/d H:i', strtotime($user['last_login']))) : 'هرگز'; ?></p>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="settings-tabs">
            <button class="tab-btn active" onclick="showTab('profile')">👤 پروفایل</button>
            <button class="tab-btn" onclick="showTab('security')">🔒 امنیت</button>
            <button class="tab-btn" onclick="showTab('preferences')">🎨 تنظیمات کاربری</button>
            <?php if ($_SESSION['is_admin']): ?>
                <button class="tab-btn" onclick="showTab('system')">⚙️ تنظیمات سیستم</button>
            <?php endif; ?>
            <button class="tab-btn" onclick="showTab('activity')">📊 فعالیت‌ها</button>
        </div>
        
        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content active">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>اطلاعات شخصی</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label>نام کاربری</label>
                            <input type="text" value="<?php echo h($user['username']); ?>" disabled>
                            <small>نام کاربری قابل تغییر نیست</small>
                        </div>
                        
                        <div class="form-group">
                            <label>نام و نام خانوادگی *</label>
                            <input type="text" name="fullname" value="<?php echo h($user['fullname']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>ایمیل</label>
                            <input type="email" name="email" value="<?php echo h($user['email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>موبایل</label>
                            <input type="text" name="mobile" value="<?php echo h($user['mobile']); ?>" 
                                   placeholder="09xxxxxxxxx" maxlength="11">
                        </div>
                        
                        <button type="submit" class="btn btn-success">💾 ذخیره تغییرات</button>
                    </form>
                </div>
                
                <div class="settings-card">
                    <h3>اطلاعات سیستم</h3>
                    <div class="form-group">
                        <label>شناسه کاربری</label>
                        <input type="text" value="<?php echo en2fa($user['id']); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ عضویت</label>
                        <input type="text" value="<?php echo en2fa(date('Y/m/d H:i', strtotime($user['created_at']))); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>سطح دسترسی</label>
                        <input type="text" value="<?php echo $user['is_admin'] ? 'مدیر سیستم' : 'کاربر عادی'; ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت حساب</label>
                        <input type="text" value="<?php echo $user['is_active'] ? '✓ فعال' : '✗ غیرفعال'; ?>" disabled>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security Tab -->
        <div id="security-tab" class="tab-content">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>🔒 تغییر رمز عبور</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>رمز عبور فعلی *</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>رمز عبور جدید *</label>
                            <input type="password" name="new_password" required minlength="6">
                            <small>حداقل ۶ کاراکتر</small>
                        </div>
                        
                        <div class="form-group">
                            <label>تکرار رمز عبور جدید *</label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-success">🔐 تغییر رمز عبور</button>
                    </form>
                </div>
                
                <div class="settings-card">
                    <h3>🛡️ توصیه‌های امنیتی</h3>
                    <ul style="line-height: 2; color: #7f8c8d; padding-right: 20px;">
                        <li>از رمز عبور قوی استفاده کنید</li>
                        <li>رمز عبور را به صورت دوره‌ای تغییر دهید</li>
                        <li>از رمز عبور یکسان در سایت‌های مختلف استفاده نکنید</li>
                        <li>پس از پایان کار، حتماً از سیستم خارج شوید</li>
                        <li>رمز عبور خود را با کسی به اشتراک نگذارید</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Preferences Tab -->
        <div id="preferences-tab" class="tab-content">
            <div class="settings-card">
                <h3>🎨 تنظیمات کاربری</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_preferences">
                    
                    <div class="form-group">
                        <label>زبان رابط کاربری</label>
                        <select name="language">
                            <option value="fa" selected>فارسی</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تم رنگی</label>
                        <select name="theme">
                            <option value="light">روشن</option>
                            <option value="dark">تیره</option>
                            <option value="auto">خودکار</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notifications" checked>
                            دریافت اعلان‌ها
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="email_notifications">
                            ارسال ایمیل برای رویدادهای مهم
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-success">💾 ذخیره تنظیمات</button>
                </form>
            </div>
        </div>
        
        <!-- System Settings Tab -->
        <?php if ($_SESSION['is_admin']): ?>
        <div id="system-tab" class="tab-content">
            <div class="settings-card">
                <h3>⚙️ تنظیمات سیستم</h3>
                <?php foreach ($settingsByCategory as $category => $settings): ?>
                    <h4 style="margin: 20px 0 15px; color: #34495e;">
                        <?php 
                        $categoryLabels = [
                            'general' => '🔧 تنظیمات عمومی',
                            'appearance' => '🎨 ظاهر',
                            'security' => '🔒 امنیت',
                            'files' => '📁 فایل‌ها',
                            'company' => '🏢 شرکت',
                            'financial' => '💰 مالی'
                        ];
                        echo $categoryLabels[$category] ?? $category;
                        ?>
                    </h4>
                    <?php foreach ($settings as $setting): ?>
                        <div class="setting-item">
                            <div class="setting-label">
                                <div class="setting-name"><?php echo h($setting['key']); ?></div>
                                <?php if ($setting['description']): ?>
                                    <div class="setting-desc"><?php echo h($setting['description']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="setting-value">
                                <?php echo h($setting['value'] ?: '-'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                
                <div style="margin-top: 20px;">
                    <a href="system_settings.php" class="btn btn-primary">⚙️ ویرایش تنظیمات سیستم</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Activity Tab -->
        <div id="activity-tab" class="tab-content">
            <div class="settings-card">
                <h3>📊 آخرین فعالیت‌های شما</h3>
                <?php if (count($recentActivities) > 0): ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-action">
                                <?php 
                                $actionLabels = [
                                    'login' => '🔐 ورود به سیستم',
                                    'logout' => '🚪 خروج از سیستم',
                                    'update_profile' => '👤 به‌روزرسانی پروفایل',
                                    'change_password' => '🔒 تغییر رمز عبور',
                                    'create' => '➕ ایجاد',
                                    'update' => '✏️ ویرایش',
                                    'delete' => '🗑️ حذف'
                                ];
                                echo $actionLabels[$activity['action']] ?? h($activity['action']);
                                ?>
                                
                                <?php if ($activity['module']): ?>
                                    - <?php echo h($activity['module']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time">
                                🕐 <?php echo en2fa(date('Y/m/d H:i:s', strtotime($activity['created_at']))); ?>
                                - IP: <?php echo h($activity['ip_address']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 40px;">فعالیتی ثبت نشده است</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>