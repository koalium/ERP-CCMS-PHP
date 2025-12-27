<?php
/**
 * مدیریت مجوزهای کاربر
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('admin', PERMISSION_WRITE)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$userId = (int)($_GET['user_id'] ?? 0);

if (!$userId) {
    die('کاربر مشخص نشده است.');
}

// بارگذاری اطلاعات کاربر
$user = db()->selectOne("SELECT * FROM users WHERE id = :id", [':id' => $userId]);

if (!$user) {
    die('کاربر یافت نشد.');
}

$error = '';
$success = '';

if (isset($_GET['success'])) {
    $success = 'کاربر با موفقیت ایجاد شد. اکنون مجوزهای دسترسی را تنظیم کنید.';
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی.';
    } else {
        db()->beginTransaction();
        
        try {
            // حذف مجوزهای قبلی
            db()->delete('user_permissions', 'user_id = :user_id', [':user_id' => $userId]);
            
            // افزودن مجوزهای جدید
            $permissions = $_POST['permissions'] ?? [];
            
            foreach ($permissions as $permId => $level) {
                if ($level > 0) {
                    db()->insert('user_permissions', [
                        'user_id' => $userId,
                        'permission_id' => (int)$permId,
                        'access_level' => (int)$level
                    ]);
                }
            }
            
            db()->commit();
            
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'update_permissions',
                'module' => 'users',
                'record_id' => $userId,
                'new_data' => json_encode($permissions),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $success = 'مجوزها با موفقیت به‌روزرسانی شدند.';
        } catch (Exception $e) {
            db()->rollback();
            $error = 'خطا در به‌روزرسانی مجوزها.';
        }
    }
}

// دریافت تمام مجوزها
$allPermissions = db()->select("SELECT * FROM permissions ORDER BY module, name");

// دریافت مجوزهای فعلی کاربر
$userPermissions = db()->select(
    "SELECT permission_id, access_level FROM user_permissions WHERE user_id = :user_id",
    [':user_id' => $userId]
);

$currentPermissions = [];
foreach ($userPermissions as $perm) {
    $currentPermissions[$perm['permission_id']] = $perm['access_level'];
}

// گروه‌بندی مجوزها بر اساس ماژول
$groupedPermissions = [];
foreach ($allPermissions as $perm) {
    $groupedPermissions[$perm['module']][] = $perm;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مجوزها - <?php echo h($user['fullname']); ?> - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: #f5f7fa;
            direction: rtl;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }
        
        .user-details h3 {
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .user-details p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .info-box {
            background: #e3f2fd;
            border-right: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #1976d2;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .info-box p {
            color: #1565c0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .permissions-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .quick-actions {
            padding: 20px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-success { background: #4caf50; color: white; }
        .btn-warning { background: #ff9800; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .module-section {
            border-bottom: 1px solid #f0f0f0;
        }
        
        .module-section:last-child {
            border-bottom: none;
        }
        
        .module-header {
            padding: 15px 20px;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        
        .module-header:hover {
            background: #e9ecef;
        }
        
        .module-header h3 {
            color: #2c3e50;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .module-count {
            font-size: 12px;
            color: #666;
            background: white;
            padding: 4px 10px;
            border-radius: 12px;
        }
        
        .module-content {
            padding: 20px;
            display: none;
        }
        
        .module-content.active {
            display: block;
        }
        
        .permissions-table {
            width: 100%;
        }
        
        .permission-row {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .permission-row:last-child {
            border-bottom: none;
        }
        
        .permission-info h4 {
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .permission-info p {
            color: #666;
            font-size: 12px;
        }
        
        .permission-levels {
            display: flex;
            gap: 10px;
        }
        
        .level-option {
            flex: 1;
            position: relative;
        }
        
        .level-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .level-label {
            display: block;
            padding: 10px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }
        
        .level-option input[type="radio"]:checked + .level-label {
            border-color: #667eea;
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        
        .level-option input[type="radio"]:checked + .level-label.level-none {
            background: #e0e0e0;
            border-color: #999;
            color: #666;
        }
        
        .level-option input[type="radio"]:checked + .level-label.level-read {
            background: #4caf50;
            border-color: #4caf50;
        }
        
        .level-option input[type="radio"]:checked + .level-label.level-write {
            background: #ff9800;
            border-color: #ff9800;
        }
        
        .level-option input[type="radio"]:checked + .level-label.level-full {
            background: #f44336;
            border-color: #f44336;
        }
        
        .form-actions {
            padding: 20px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .permission-row {
                grid-template-columns: 1fr;
            }
            
            .permission-levels {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="users.php">کاربران</a>
                <span>›</span>
                <a href="user.php?action=view&id=<?php echo $userId; ?>"><?php echo h($user['fullname']); ?></a>
                <span>›</span>
                <span>مجوزها</span>
            </div>
            <h1>🔐 مدیریت مجوزهای دسترسی</h1>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo mb_substr($user['fullname'], 0, 1, 'UTF-8'); ?>
                </div>
                <div class="user-details">
                    <h3><?php echo h($user['fullname']); ?></h3>
                    <p>نام کاربری: <?php echo h($user['username']); ?> | <?php echo $user['is_admin'] ? '🔑 مدیر سیستم' : '👤 کاربر عادی'; ?></p>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($user['is_admin']): ?>
            <div class="info-box">
                <h3>⚠️ توجه</h3>
                <p>این کاربر <strong>مدیر سیستم</strong> است و به طور خودکار به تمام بخش‌ها دسترسی کامل دارد. تنظیم مجوزهای جزئی برای مدیران تاثیری ندارد.</p>
            </div>
        <?php else: ?>
            <div class="info-box">
                <h3>💡 راهنما</h3>
                <p>
                    <strong>هیچ:</strong> کاربر به این بخش دسترسی ندارد. |
                    <strong>خواندن:</strong> فقط مشاهده اطلاعات. |
                    <strong>نوشتن:</strong> ایجاد و ویرایش. |
                    <strong>کامل:</strong> تمام عملیات شامل حذف.
                </p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="permissionsForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="permissions-container">
                <div class="quick-actions">
                    <button type="button" class="btn btn-sm btn-success" onclick="setAllPermissions(1)">
                        ✅ همه خواندن
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" onclick="setAllPermissions(2)">
                        ✏️ همه نوشتن
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="setAllPermissions(3)">
                        🔓 همه کامل
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="setAllPermissions(0)">
                        ❌ حذف همه
                    </button>
                </div>
                
                <?php 
                $moduleIcons = [
                    'admin' => '⚙️',
                    'contacts' => '📇',
                    'financial' => '💰',
                    'projects' => '🏗️',
                    'contracts' => '📝',
                    'hr' => '👔',
                    'warehouse' => '📦',
                    'procurement' => '🛒',
                    'engineering' => '⚙️',
                    'production' => '🏭',
                    'qc' => '✅',
                    'marketing' => '💼',
                    'sell' => '💵',
                    'calendar' => '📅',
                    'notes' => '📋',
                    'messenger' => '💬',
                    'meetings' => '🤝',
                    'documents' => '📁'
                ];
                
                $moduleNames = [
                    'admin' => 'مدیریت سیستم',
                    'contacts' => 'مخاطبین',
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
                    'calendar' => 'تقویم',
                    'notes' => 'یادداشت',
                    'messenger' => 'پیام‌رسان',
                    'meetings' => 'جلسات',
                    'documents' => 'اسناد'
                ];
                
                foreach ($groupedPermissions as $module => $permissions): 
                    $icon = $moduleIcons[$module] ?? '📌';
                    $moduleName = $moduleNames[$module] ?? $module;
                ?>
                    <div class="module-section">
                        <div class="module-header" onclick="toggleModule(this)">
                            <h3>
                                <?php echo $icon; ?>
                                <?php echo $moduleName; ?>
                            </h3>
                            <span class="module-count"><?php echo en2fa(count($permissions)); ?> مجوز</span>
                        </div>
                        <div class="module-content">
                            <div class="permissions-table">
                                <?php foreach ($permissions as $perm): 
                                    $currentLevel = $currentPermissions[$perm['id']] ?? 0;
                                ?>
                                    <div class="permission-row">
                                        <div class="permission-info">
                                            <h4><?php echo h($perm['display_name']); ?></h4>
                                            <p><?php echo h($perm['description']); ?></p>
                                        </div>
                                        <div class="permission-levels">
                                            <div class="level-option">
                                                <input type="radio" 
                                                       name="permissions[<?php echo $perm['id']; ?>]" 
                                                       value="0" 
                                                       id="perm_<?php echo $perm['id']; ?>_0"
                                                       <?php echo $currentLevel === 0 ? 'checked' : ''; ?>>
                                                <label for="perm_<?php echo $perm['id']; ?>_0" class="level-label level-none">
                                                    ❌<br>هیچ
                                                </label>
                                            </div>
                                            <div class="level-option">
                                                <input type="radio" 
                                                       name="permissions[<?php echo $perm['id']; ?>]" 
                                                       value="1" 
                                                       id="perm_<?php echo $perm['id']; ?>_1"
                                                       <?php echo $currentLevel === 1 ? 'checked' : ''; ?>>
                                                <label for="perm_<?php echo $perm['id']; ?>_1" class="level-label level-read">
                                                    👁<br>خواندن
                                                </label>
                                            </div>
                                            <div class="level-option">
                                                <input type="radio" 
                                                       name="permissions[<?php echo $perm['id']; ?>]" 
                                                       value="2" 
                                                       id="perm_<?php echo $perm['id']; ?>_2"
                                                       <?php echo $currentLevel === 2 ? 'checked' : ''; ?>>
                                                <label for="perm_<?php echo $perm['id']; ?>_2" class="level-label level-write">
                                                    ✏️<br>نوشتن
                                                </label>
                                            </div>
                                            <div class="level-option">
                                                <input type="radio" 
                                                       name="permissions[<?php echo $perm['id']; ?>]" 
                                                       value="3" 
                                                       id="perm_<?php echo $perm['id']; ?>_3"
                                                       <?php echo $currentLevel === 3 ? 'checked' : ''; ?>>
                                                <label for="perm_<?php echo $perm['id']; ?>_3" class="level-label level-full">
                                                    🔓<br>کامل
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="form-actions">
                    <a href="users.php" class="btn btn-secondary">↩ بازگشت به لیست</a>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="location.reload()">
                            🔄 بازنشانی
                        </button>
                        <button type="submit" class="btn btn-primary">
                            💾 ذخیره مجوزها
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        function toggleModule(header) {
            const content = header.nextElementSibling;
            content.classList.toggle('active');
        }
        
        function setAllPermissions(level) {
            const radios = document.querySelectorAll('input[type="radio"][value="' + level + '"]');
            radios.forEach(radio => {
                radio.checked = true;
            });
        }
        
        // باز کردن اولین ماژول به طور پیش‌فرض
        document.addEventListener('DOMContentLoaded', function() {
            const firstModule = document.querySelector('.module-content');
            if (firstModule) {
                firstModule.classList.add('active');
            }
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>