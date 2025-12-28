<?php
/**
 * هدر مشترک سیستم
 * Common Header for All Pages
 */

if (!isset($_SESSION)) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/favicon.ico">
    
    <!-- سبک‌های مشترک -->
    <style>
        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Navigation Styles */
        .top-nav {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .nav-brand {
            font-size: 20px;
            font-weight: bold;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-menu {
            display: flex;
            gap: 5px;
            list-style: none;
            flex-wrap: wrap;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 6px;
            transition: background 0.3s;
            font-size: 14px;
            display: inline-block;
        }
        
        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .nav-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        @media (max-width: 968px) {
            .nav-menu {
                display: none;
                width: 100%;
                flex-direction: column;
                background: rgba(0, 0, 0, 0.1);
                padding: 10px;
                border-radius: 8px;
                margin-top: 10px;
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="top-nav">
        <div class="nav-container">
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="nav-brand">
                🏢 eSmartis ERP
            </a>
            
            <button class="menu-toggle" onclick="toggleMenu()">☰</button>
            
            <ul class="nav-menu" id="navMenu">
                <li><a href="<?php echo SITE_URL; ?>/dashboard.php">🏠 داشبورد</a></li>
                
                <?php if (check_permission('contacts', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/contacts.php">📇 مخاطبین</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('financial', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/financial.php">💰 مالی</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('projects', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/projects.php">📊 پروژه‌ها</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('warehouse', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/warehouse.php">📦 انبار</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('engineering', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/engineering.php">⚙️ مهندسی</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('qc', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/qc.php">✅ کیفیت</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('documents', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/documents.php">📄 اسناد</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('hr', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/hr.php">👔 HR</a></li>
                <?php endif; ?>
                
                <?php if (check_permission('messenger', PERMISSION_READ)): ?>
                <li><a href="<?php echo SITE_URL; ?>/messenger.php">💬 پیام‌رسان</a></li>
                <?php endif; ?>
                
                <?php if ($_SESSION['is_admin']): ?>
                <li><a href="<?php echo SITE_URL; ?>/admin.php">⚙️ مدیریت</a></li>
                <?php endif; ?>
            </ul>
            
            <div class="nav-user">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo mb_substr($_SESSION['fullname'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <span><?php echo h($_SESSION['fullname']); ?></span>
                </div>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="logout-btn">
                    🚪 خروج
                </a>
            </div>
        </div>
    </nav>
    
    <script>
        function toggleMenu() {
            const menu = document.getElementById('navMenu');
            menu.classList.toggle('active');
        }
    </script>
    <?php endif; ?>
</body>
</html>