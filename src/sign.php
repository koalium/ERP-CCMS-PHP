<?php
/**
 * صفحه ورود به سیستم
 */

require_once 'config.php';
require_once 'dbc.php';

// چک کردن ورود قبلی
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_admin']) {
        redirect(SITE_URL . '/admin.php');
    } else {
        redirect(SITE_URL . '/dashboard.php');
    }
}

$error = '';
$success = '';

// بررسی timeout
if (isset($_GET['timeout'])) {
    $error = 'نشست شما به پایان رسید. لطفاً مجدداً وارد شوید.';
}

// بررسی logout
if (isset($_GET['logout'])) {
    $success = 'با موفقیت خارج شدید.';
}

// پردازش فرم ورود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // بررسی خالی نبودن فیلدها
    if (empty($username) || empty($password)) {
        $error = 'لطفاً نام کاربری و رمز عبور را وارد کنید.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // بررسی محدودیت تلاش ورود (3 ثانیه بین هر تلاش)
        $recentAttempt = db()->selectOne(
            "SELECT attempted_at FROM login_attempts 
             WHERE username = :username AND ip_address = :ip 
             ORDER BY attempted_at DESC LIMIT 1",
            [':username' => $username, ':ip' => $ip]
        );
        
        if ($recentAttempt) {
            $timeDiff = time() - strtotime($recentAttempt['attempted_at']);
            if ($timeDiff < LOGIN_COOLDOWN) {
                $error = 'تلاش‌های متعدد برای ورود. لطفاً ' . (LOGIN_COOLDOWN - $timeDiff) . ' ثانیه صبر کنید.';
            }
        }
        
        // ثبت تلاش ورود
        db()->insert('login_attempts', [
            'username' => $username,
            'ip_address' => $ip
        ]);
        
        if (empty($error)) {
            // جستجوی کاربر
            $user = db()->selectOne(
                "SELECT * FROM users WHERE username = :username AND is_active = 1",
                [':username' => $username]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                // ورود موفق
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['last_activity'] = time();
                
                // به‌روزرسانی زمان آخرین ورود
                $token = null;
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', false, true);
                }
                
                db()->update('users', [
                    'last_login' => date('Y-m-d H:i:s'),
                    'remember_token' => $token
                ], 'id = :id', [':id' => $user['id']]);
                
                // بارگذاری مجوزها
                $permissions = db()->select(
                    "SELECT p.name, p.module, up.access_level 
                     FROM user_permissions up
                     JOIN permissions p ON p.id = up.permission_id
                     WHERE up.user_id = :user_id",
                    [':user_id' => $user['id']]
                );
                
                $_SESSION['permissions'] = [];
                foreach ($permissions as $perm) {
                    $_SESSION['permissions'][$perm['module']] = $perm['access_level'];
                }
                
                // ثبت لاگ ورود
                db()->insert('logs', [
                    'user_id' => $user['id'],
                    'action' => 'login',
                    'module' => 'auth',
                    'ip_address' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                // پاک کردن تلاش‌های ورود
                db()->delete('login_attempts', 'username = :username', [':username' => $username]);
                
                // هدایت به صفحه مناسب
                if ($user['is_admin']) {
                    redirect(SITE_URL . '/admin.php');
                } else {
                    redirect(SITE_URL . '/dashboard.php');
                }
            } else {
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
                
                // ثبت لاگ ورود ناموفق
                db()->insert('logs', [
                    'user_id' => null,
                    'action' => 'login_failed',
                    'module' => 'auth',
                    'ip_address' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'old_data' => json_encode(['username' => $username])
                ]);
            }
        }
    }
}

// چک کردن remember me cookie
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id'])) {
    $token = $_COOKIE['remember_token'];
    $user = db()->selectOne(
        "SELECT * FROM users WHERE remember_token = :token AND is_active = 1",
        [':token' => $token]
    );
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['last_activity'] = time();
        
        if ($user['is_admin']) {
            redirect(SITE_URL . '/admin.php');
        } else {
            redirect(SITE_URL . '/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 90%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input {
            margin-left: 8px;
            width: auto;
        }
        
        .remember-me label {
            color: #666;
            font-size: 14px;
            cursor: pointer;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
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
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 12px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>eSmartis ERP</h1>
            <p>سیستم یکپارچه مدیریت سازمان</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">نام کاربری</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo h($_POST['username'] ?? ''); ?>" 
                       required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">مرا به خاطر بسپار</label>
            </div>
            
            <button type="submit" class="btn-login">ورود به سیستم</button>
        </form>
        
        <div class="footer">
            <p>طراحی و توسعه: Ashkarian.r</p>
            <p>© <?php echo date('Y'); ?> eSmartis. تمامی حقوق محفوظ است.</p>
        </div>
    </div>
</body>
</html>