<?php
/**
 * admin.php - پنل مدیریت سیستم
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'jalali-converter.php';

check_login();

if (!$_SESSION['is_admin']) {
    die('شما دسترسی به پنل مدیریت ندارید.');
}

// آمار کلی سیستم
$systemStats = [
    'users' => db()->count('users', 'is_active = 1'),
    'total_transactions' => db()->count('warehouse_transactions'),
    'pending_approvals' => db()->count('warehouse_transactions', 'status = "pending"'),
    'active_projects' => db()->count('projects', 'status = "active"'),
    'total_items' => db()->count('warehouse_items', 'is_active = 1'),
    'low_stock_items' => db()->selectOne("
        SELECT COUNT(*) as count FROM warehouse_items 
        WHERE is_active = 1 AND current_stock <= min_stock AND min_stock > 0
    ")['count'],
];

// آخرین فعالیت‌ها
$recentLogs = db()->select("
    SELECT l.*, u.fullname 
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC
    LIMIT 10
");

// کاربران آنلاین (فعالیت در 15 دقیقه اخیر)
$onlineUsers = db()->select("
    SELECT DISTINCT u.* FROM users u
    JOIN logs l ON l.user_id = u.id
    WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    AND u.is_active = 1
    ORDER BY l.created_at DESC
");

$today_jalali = jalaliToday();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - <?php echo SITE_TITLE; ?></title>
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
        
        .main-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .main-header .container {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .main-header .logo {
            font-size: 28px;
            font-weight: bold;
        }
        
        .main-header .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .main-header a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .main-header a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .welcome {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .welcome h1 {
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        
        .stat-card.warning {
            border: 2px solid #ff9800;
        }
        
        .stat-card.warning .value {
            color: #ff9800;
        }
        
        .admin-modules {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .admin-modules h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .module-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .module-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .module-btn .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .activity-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .activity-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .activity-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-list li {
            padding: 12px;
            border-right: 3px solid #667eea;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .activity-list .user {
            font-weight: bold;
            color: #667eea;
        }
        
        .activity-list .time {
            color: #999;
            font-size: 11px;
            display: block;
            margin-top: 5px;
        }
        
        .online-users {
            list-style: none;
        }
        
        .online-users li {
            padding: 12px;
            background: #e8f5e9;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .online-users .status-dot {
            width: 10px;
            height: 10px;
            background: #4caf50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        footer {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 50px;
        }
        
        @media (max-width: 768px) {
            .activity-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-header">
        <div class="container">
            <div class="logo">
                ⚙️ پنل مدیریت eSmartis
            </div>
            <div class="user-info">
                <span>📅 <?php echo en2fa($today_jalali); ?></span>
                <span>👤 <?php echo h($_SESSION['fullname']); ?></span>
                <a href="dashboard.php">🏠 داشبورد</a>
                <a href="logout.php">🚪 خروج</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h1>پنل مدیریت سیستم 🔐</h1>
            <p>مدیریت کامل تمام بخش‌های سیستم</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="value"><?php echo en2fa($systemStats['users']); ?></div>
                <div class="label">کاربران فعال</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📊</div>
                <div class="value"><?php echo en2fa($systemStats['active_projects']); ?></div>
                <div class="label">پروژه‌های فعال</div>
            </div>
            
            <div class="stat-card warning">
                <div class="icon">⏳</div>
                <div class="value"><?php echo en2fa($systemStats['pending_approvals']); ?></div>
                <div class="label">در انتظار تایید</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📦</div>
                <div class="value"><?php echo en2fa($systemStats['total_items']); ?></div>
                <div class="label">اقلام انبار</div>
            </div>
            
            <div class="stat-card warning">
                <div class="icon">⚠️</div>
                <div class="value"><?php echo en2fa($systemStats['low_stock_items']); ?></div>
                <div class="label">موجودی کم</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">🔄</div>
                <div class="value"><?php echo en2fa($systemStats['total_transactions']); ?></div>
                <div class="label">کل تراکنش‌ها</div>
            </div>
        </div>
        
        <div class="admin-modules">
            <h2>ماژول‌های مدیریتی</h2>
            <div class="modules-grid">
                <a href="users.php" class="module-btn">
                    <div class="icon">👥</div>
                    <div>مدیریت کاربران</div>
                </a>
                
                <a href="permission.php" class="module-btn">
                    <div class="icon">🔐</div>
                    <div>مجوزها</div>
                </a>
                
                <a href="warehouse_items.php" class="module-btn">
                    <div class="icon">📦</div>
                    <div>انبارداری</div>
                </a>
                
                <a href="financial.php" class="module-btn">
                    <div class="icon">💰</div>
                    <div>امور مالی</div>
                </a>
                
                <a href="projects.php" class="module-btn">
                    <div class="icon">📊</div>
                    <div>پروژه‌ها</div>
                </a>
                
                <a href="hr.php" class="module-btn">
                    <div class="icon">👔</div>
                    <div>منابع انسانی</div>
                </a>
                
                <a href="contacts.php" class="module-btn">
                    <div class="icon">📇</div>
                    <div>مخاطبین</div>
                </a>
                
                <a href="settings.php" class="module-btn">
                    <div class="icon">⚙️</div>
                    <div>تنظیمات</div>
                </a>
            </div>
        </div>
        
        <div class="activity-section">
            <div class="activity-card">
                <h3>📝 آخرین فعالیت‌ها</h3>
                <ul class="activity-list">
                    <?php foreach ($recentLogs as $log): ?>
                    <li>
                        <span class="user"><?php echo h($log['fullname']); ?></span>
                        انجام داد: <?php echo h($log['action']); ?>
                        در بخش <?php echo h($log['module']); ?>
                        <span class="time">
                            <?php echo en2fa(formatJalaliDate($log['created_at'], 'Y/m/d H:i')); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="activity-card">
                <h3>🟢 کاربران آنلاین</h3>
                <?php if (count($onlineUsers) > 0): ?>
                <ul class="online-users">
                    <?php foreach ($onlineUsers as $user): ?>
                    <li>
                        <span class="status-dot"></span>
                        <span><?php echo h($user['fullname']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">
                    هیچ کاربر آنلاینی یافت نشد
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <footer>
        <p>© <?php echo date('Y'); ?> eSmartis - سیستم یکپارچه مدیریت سازمان</p>
        <p style="font-size: 12px; margin-top: 10px;">
            طراحی و توسعه: <strong>Ashkarian.r</strong>
        </p>
    </footer>
</body>
</html>