<?php
/**
 * هدر مشترک برای تمام صفحات
 */

if (!isset($_SESSION)) {
    session_start();
}

// چک کردن ورود کاربر
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'sign.php') {
    header('Location: sign.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="سیستم یکپارچه مدیریت سازمان eSmartis">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #4caf50;
            --danger-color: #f44336;
            --warning-color: #ff9800;
            --info-color: #2196f3;
            --dark-color: #2c3e50;
            --light-color: #f5f7fa;
            --border-color: #e0e0e0;
            --text-color: #333;
            --text-muted: #999;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: var(--light-color);
            color: var(--text-color);
            direction: rtl;
            line-height: 1.6;
        }
        
        /* Header Styles */
        .main-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .logout-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        /* Navigation Menu */
        .main-nav {
            display: flex;
            padding: 0 20px;
            overflow-x: auto;
            background: rgba(0,0,0,0.1);
        }
        
        .main-nav::-webkit-scrollbar {
            height: 4px;
        }
        
        .main-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
        }
        
        .nav-item {
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-bottom-color: white;
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }
        
        /* Notifications */
        .notifications {
            position: relative;
        }
        
        .notification-bell {
            position: relative;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            left: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-top {
                padding: 10px 15px;
            }
            
            .logo {
                font-size: 18px;
            }
            
            .user-name span {
                display: none;
            }
            
            .main-nav {
                flex-direction: column;
                display: none;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }
            
            .main-nav.show {
                display: flex;
                max-height: 500px;
            }
            
            .nav-item {
                border-bottom: 1px solid rgba(255,255,255,0.1);
                border-left: none;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'sign.php'): ?>
    <header class="main-header">
        <div class="header-top">
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="logo">
                🏢 eSmartis ERP
            </a>
            
            <div class="user-info">
                <div class="notifications">
                    <span class="notification-bell">
                        🔔
                        <span class="notification-badge">3</span>
                    </span>
                </div>
                
                <div class="user-name">
                    👤 <span>خوش آمدید، <?php echo h($_SESSION['fullname']); ?></span>
                </div>
                
                <a href="<?php echo SITE_URL; ?>/logout.php" class="logout-btn">
                    🚪 خروج
                </a>
                
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    ☰
                </button>
            </div>
        </div>
        
        <nav class="main-nav" id="mainNav">
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="nav-item">
                🏠 داشبورد
            </a>
            
            <?php if (check_permission('contacts', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/contacts.php" class="nav-item">
                📇 مخاطبین
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('financial', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/financial.php" class="nav-item">
                💰 مالی
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('projects', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/projects.php" class="nav-item">
                📊 پروژه‌ها
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('contracts', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/contracts.php" class="nav-item">
                📝 قراردادها
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('hr', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/hr.php" class="nav-item">
                👔 منابع انسانی
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('warehouse', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/warehouse.php" class="nav-item">
                📦 انبار
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('procurement', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/procurement.php" class="nav-item">
                🛒 تدارکات
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('engineering', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/engineering.php" class="nav-item">
                ⚙️ مهندسی
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('production', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/production.php" class="nav-item">
                🏭 تولید
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('qc', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/qc.php" class="nav-item">
                ✅ کنترل کیفیت
            </a>
            <?php endif; ?>
            
            <?php if (check_permission('marketing', PERMISSION_READ)): ?>
            <a href="<?php echo SITE_URL; ?>/marketing.php" class="nav-item">
                💼 بازرگانی
            </a>
            <?php endif; ?>
            
            <a href="<?php echo SITE_URL; ?>/calendar.php" class="nav-item">
                📅 تقویم
            </a>
            
            <a href="<?php echo SITE_URL; ?>/notes.php" class="nav-item">
                📝 یادداشت
            </a>
            
            <a href="<?php echo SITE_URL; ?>/messenger.php" class="nav-item">
                💬 پیام‌رسان
            </a>
            
            <?php if ($_SESSION['is_admin']): ?>
            <a href="<?php echo SITE_URL; ?>/admin.php" class="nav-item">
                ⚙️ مدیریت
            </a>
            <?php endif; ?>
        </nav>
    </header>
    
    <script>
        function toggleMobileMenu() {
            const nav = document.getElementById('mainNav');
            nav.classList.toggle('show');
        }
        
        // Active menu item
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.nav-item').forEach(item => {
            if (item.getAttribute('href').includes(currentPage)) {
                item.classList.add('active');
            }
        });
    </script>
    <?php endif; ?>