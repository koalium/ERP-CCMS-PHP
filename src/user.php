<?php
/**
 * فرم کاربر - افزودن، ویرایش، مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = sanitize_input($_GET['action'] ?? 'add');
$userId = (int)($_GET['id'] ?? 0);

// چک مجوز
if ($action === 'add' && !check_permission('admin', PERMISSION_WRITE)) {
    die('شما مجوز افزودن کاربر را ندارید.');
}

if ($action === 'edit' && !check_permission('admin', PERMISSION_WRITE)) {
    die('شما مجوز ویرایش کاربر را ندارید.');
}

if ($action === 'delete' && !check_permission('admin', PERMISSION_FULL)) {
    die('شما مجوز حذف کاربر را ندارید.');
}

$user = null;
$error = '';
$success = '';

// حذف کاربر
if ($action === 'delete' && $userId) {
    $user = db()->selectOne("SELECT * FROM users WHERE id = :id", [':id' => $userId]);
    
    if (!$user) {
        die('کاربر یافت نشد.');
    }
    
    if ($user['id'] == $_SESSION['user_id']) {
        die('شما نمی‌توانید خودتان را حذف کنید.');
    }
    
    if (db()->delete('users', 'id = :id', [':id' => $userId])) {
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'delete',
            'module' => 'users',
            'record_id' => $userId,
            'old_data' => json_encode($user),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        redirect(SITE_URL . '/users.php?deleted=1');
    }
}

// بارگذاری اطلاعات کاربر
if (($action === 'edit' || $action === 'view') && $userId) {
    $user = db()->selectOne("SELECT * FROM users WHERE id = :id", [':id' => $userId]);
    if (!$user) {
        die('کاربر یافت نشد.');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $data = [
            'username' => sanitize_input($_POST['username'] ?? ''),
            'fullname' => sanitize_input($_POST['fullname'] ?? ''),
            'email' => sanitize_input($_POST['email'] ?? ''),
            'mobile' => sanitize_input($_POST['mobile'] ?? ''),
            'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // اعتبارسنجی
        if (empty($data['username'])) {
            $error = 'نام کاربری الزامی است.';
        } elseif (strlen($data['username']) < 3) {
            $error = 'نام کاربری باید حداقل 3 کاراکتر باشد.';
        } elseif (empty($data['fullname'])) {
            $error = 'نام و نام خانوادگی الزامی است.';
        } elseif ($data['email'] && !validate_email($data['email'])) {
            $error = 'فرمت ایمیل صحیح نیست.';
        } elseif ($data['mobile'] && !validate_mobile($data['mobile'])) {
            $error = 'شماره موبایل باید 11 رقم و با 09 شروع شود.';
        } else {
            // چک تکراری نبودن نام کاربری
            $existing = db()->selectOne(
                "SELECT id FROM users WHERE username = :username AND id != :id",
                [':username' => $data['username'], ':id' => $userId]
            );
            
            if ($existing) {
                $error = 'این نام کاربری قبلاً استفاده شده است.';
            } else {
                // چک رمز عبور
                if ($action === 'add') {
                    if (empty($password)) {
                        $error = 'رمز عبور الزامی است.';
                    } elseif (strlen($password) < 6) {
                        $error = 'رمز عبور باید حداقل 6 کاراکتر باشد.';
                    } elseif ($password !== $password_confirm) {
                        $error = 'رمز عبور و تکرار آن مطابقت ندارند.';
                    }
                } elseif (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error = 'رمز عبور باید حداقل 6 کاراکتر باشد.';
                    } elseif ($password !== $password_confirm) {
                        $error = 'رمز عبور و تکرار آن مطابقت ندارند.';
                    }
                }
                
                if (empty($error)) {
                    // هش کردن رمز عبور
                    if (!empty($password)) {
                        $data['password'] = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
                    }
                    
                    if ($action === 'add') {
                        $newId = db()->insert('users', $data);
                        
                        if ($newId) {
                            db()->insert('logs', [
                                'user_id' => $_SESSION['user_id'],
                                'action' => 'create',
                                'module' => 'users',
                                'record_id' => $newId,
                                'new_data' => json_encode($data),
                                'ip_address' => $_SERVER['REMOTE_ADDR']
                            ]);
                            
                            redirect(SITE_URL . '/permissions.php?user_id=' . $newId . '&success=1');
                        } else {
                            $error = 'خطا در ایجاد کاربر.';
                        }
                    } else {
                        // حذف فیلد password اگر خالی است
                        if (empty($password)) {
                            unset($data['password']);
                        }
                        
                        $updated = db()->update('users', $data, 'id = :id', [':id' => $userId]);
                        
                        if ($updated !== false) {
                            db()->insert('logs', [
                                'user_id' => $_SESSION['user_id'],
                                'action' => 'update',
                                'module' => 'users',
                                'record_id' => $userId,
                                'old_data' => json_encode($user),
                                'new_data' => json_encode($data),
                                'ip_address' => $_SERVER['REMOTE_ADDR']
                            ]);
                            
                            $success = 'کاربر با موفقیت به‌روزرسانی شد.';
                            $user = db()->selectOne("SELECT * FROM users WHERE id = :id", [':id' => $userId]);
                        } else {
                            $error = 'خطا در به‌روزرسانی کاربر.';
                        }
                    }
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = 'کاربر با موفقیت ایجاد شد.';
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'کاربر جدید' : ($action === 'view' ? 'مشاهده کاربر' : 'ویرایش کاربر'); ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 800px;
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
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
            font-weight: bold;
        }
        
        .form-group label span {
            color: #f44336;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input:disabled,
        .form-group select:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 30px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .info-box {
            background: #f8f9fa;
            border-right: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background 0.3s;
        }
        
        .password-strength-bar.weak {
            width: 33%;
            background: #f44336;
        }
        
        .password-strength-bar.medium {
            width: 66%;
            background: #ff9800;
        }
        
        .password-strength-bar.strong {
            width: 100%;
            background: #4caf50;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
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
                <span><?php echo $action === 'add' ? 'کاربر جدید' : h($user['fullname'] ?? ''); ?></span>
            </div>
            <h1>
                <?php 
                if ($action === 'add') {
                    echo '➕ کاربر جدید';
                } elseif ($action === 'view') {
                    echo '👁 مشاهده کاربر';
                } else {
                    echo '✏️ ویرایش کاربر';
                }
                ?>
            </h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="info-box">
                💡 <strong>نکته:</strong> پس از ایجاد کاربر، حتماً مجوزهای دسترسی او را تنظیم کنید.
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" id="userForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>نام کاربری <span>*</span></label>
                        <input type="text" name="username" required 
                               minlength="3"
                               value="<?php echo h($user['username'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نام و نام خانوادگی <span>*</span></label>
                        <input type="text" name="fullname" required 
                               value="<?php echo h($user['fullname'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>ایمیل</label>
                        <input type="email" name="email" 
                               value="<?php echo h($user['email'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>موبایل</label>
                        <input type="text" name="mobile" 
                               pattern="09[0-9]{9}"
                               placeholder="09xxxxxxxxx"
                               value="<?php echo h($user['mobile'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            رمز عبور <?php echo $action === 'add' ? '<span>*</span>' : '(در صورت تغییر)'; ?>
                        </label>
                        <input type="password" name="password" id="password"
                               minlength="6"
                               <?php echo $action === 'add' ? 'required' : ''; ?>
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        <?php if ($action !== 'view'): ?>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>تکرار رمز عبور <?php echo $action === 'add' ? '<span>*</span>' : ''; ?></label>
                        <input type="password" name="password_confirm" id="password_confirm"
                               minlength="6"
                               <?php echo $action === 'add' ? 'required' : ''; ?>
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="is_admin" id="is_admin"
                                       <?php echo ($user['is_admin'] ?? 0) ? 'checked' : ''; ?>
                                       <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <label for="is_admin">🔑 مدیر سیستم (دسترسی کامل)</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" name="is_active" id="is_active"
                                       <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>
                                       <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <label for="is_active">✅ کاربر فعال (می‌تواند وارد سیستم شود)</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <a href="users.php" class="btn btn-secondary">↩ بازگشت</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? '➕ ایجاد کاربر' : '💾 ذخیره تغییرات'; ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="form-actions">
                        <a href="users.php" class="btn btn-secondary">↩ بازگشت</a>
                        <?php if (check_permission('admin', PERMISSION_WRITE)): ?>
                            <a href="user.php?action=edit&id=<?php echo $userId; ?>" class="btn btn-primary">
                                ✏️ ویرایش
                            </a>
                            <a href="permissions.php?user_id=<?php echo $userId; ?>" class="btn btn-primary">
                                🔐 مدیریت مجوزها
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <?php if ($action !== 'view'): ?>
    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        
        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.length >= 10) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;
                
                strengthBar.className = 'password-strength-bar';
                if (strength <= 2) {
                    strengthBar.classList.add('weak');
                } else if (strength <= 3) {
                    strengthBar.classList.add('medium');
                } else {
                    strengthBar.classList.add('strong');
                }
            });
        }
        
        // Password confirmation
        const confirmInput = document.getElementById('password_confirm');
        if (passwordInput && confirmInput) {
            const form = document.getElementById('userForm');
            form.addEventListener('submit', function(e) {
                if (passwordInput.value !== confirmInput.value) {
                    e.preventDefault();
                    alert('رمز عبور و تکرار آن مطابقت ندارند.');
                    confirmInput.focus();
                }
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>

<?php require_once 'footer.php'; ?>