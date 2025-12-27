<?php
/**
 * فوتر مشترک برای تمام صفحات
 */
?>
    <?php if (isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'sign.php'): ?>
    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>درباره eSmartis</h3>
                <p>سیستم یکپارچه مدیریت منابع سازمانی</p>
            </div>
            
            <div class="footer-section">
                <h3>دسترسی سریع</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo SITE_URL; ?>/dashboard.php">داشبورد</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/calendar.php">تقویم</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/notes.php">یادداشت‌ها</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>پشتیبانی</h3>
                <ul class="footer-links">
                    <li>📧 info@esmartis.ir</li>
                    <li>📞 تماس با پشتیبانی</li>
                    <li>❓ راهنما</li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>اطلاعات سیستم</h3>
                <p class="system-info">
                    نسخه: 1.0.0<br>
                    کاربران فعال: <?php echo db()->count('users', 'is_active = 1'); ?><br>
                    آخرین به‌روزرسانی: <?php echo en2fa(date('Y/m/d')); ?>
                </p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>© <?php echo en2fa(date('Y')); ?> eSmartis ERP. تمامی حقوق محفوظ است.</p>
            <p>طراحی و توسعه: <strong>Ashkarian.r</strong></p>
        </div>
    </footer>
    
    <style>
        .main-footer {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            margin-top: 50px;
            padding: 40px 0 0;
        }
        
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .footer-section h3 {
            margin-bottom: 15px;
            font-size: 18px;
            color: #ecf0f1;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        
        .footer-section p {
            color: #bdc3c7;
            font-size: 14px;
            line-height: 1.8;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .system-info {
            font-size: 13px;
            line-height: 2;
        }
        
        .footer-bottom {
            background: rgba(0,0,0,0.2);
            text-align: center;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .footer-bottom p {
            margin: 5px 0;
            font-size: 13px;
            color: #95a5a6;
        }
        
        .footer-bottom strong {
            color: #ecf0f1;
        }
        
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .main-footer {
                margin-top: 30px;
                padding: 30px 0 0;
            }
        }
    </style>
    <?php endif; ?>
</body>
</html>