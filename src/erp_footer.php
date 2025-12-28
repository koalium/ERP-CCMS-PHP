<?php
/**
 * فوتر مشترک سیستم
 * Common Footer for All Pages
 */
?>

<footer style="background: #2c3e50; color: white; padding: 30px 20px; margin-top: 40px;">
    <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
        <div>
            <h3 style="margin-bottom: 15px; font-size: 18px;">🏢 eSmartis ERP</h3>
            <p style="line-height: 1.8; color: #ecf0f1; font-size: 14px;">
                سیستم یکپارچه مدیریت منابع سازمانی
                <br>
                مدیریت حرفه‌ای پروژه‌ها، مالی، انبار، مهندسی و تولید
            </p>
        </div>
        
        <div>
            <h3 style="margin-bottom: 15px; font-size: 18px;">📞 تماس با ما</h3>
            <p style="line-height: 1.8; color: #ecf0f1; font-size: 14px;">
                📧 ایمیل: info@esmartis.ir
                <br>
                📱 تلفن: 021-12345678
                <br>
                🌐 وب‌سایت: www.esmartis.ir
            </p>
        </div>
        
        <div>
            <h3 style="margin-bottom: 15px; font-size: 18px;">🔗 لینک‌های مفید</h3>
            <ul style="list-style: none; line-height: 2; font-size: 14px;">
                <li><a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: #3498db; text-decoration: none;">داشبورد</a></li>
                <li><a href="<?php echo SITE_URL; ?>/documents.php" style="color: #3498db; text-decoration: none;">مستندات</a></li>
                <li><a href="<?php echo SITE_URL; ?>/help.php" style="color: #3498db; text-decoration: none;">راهنما</a></li>
                <li><a href="<?php echo SITE_URL; ?>/support.php" style="color: #3498db; text-decoration: none;">پشتیبانی</a></li>
            </ul>
        </div>
        
        <div>
            <h3 style="margin-bottom: 15px; font-size: 18px;">📊 آمار سیستم</h3>
            <p style="line-height: 1.8; color: #ecf0f1; font-size: 14px;">
                نسخه: <strong>1.0.0</strong>
                <br>
                تاریخ: <?php echo en2fa(date('Y/m/d')); ?>
                <br>
                ساعت: <?php echo en2fa(date('H:i')); ?>
            </p>
        </div>
    </div>
    
    <div style="max-width: 1200px; margin: 30px auto 0; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; font-size: 13px; color: #95a5a6;">
        <p>
            © <?php echo en2fa(date('Y')); ?> eSmartis. تمامی حقوق محفوظ است.
            <br>
            طراحی و توسعه توسط <strong style="color: #3498db;">Ashkarian.r</strong>
        </p>
        <p style="margin-top: 10px; font-size: 12px;">
            ساخته شده با ❤️ برای مدیریت بهتر سازمان‌ها
        </p>
    </div>
</footer>

<!-- اسکریپت‌های مشترک -->
<script>
    // تابع نمایش پیام‌های موقت
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db'};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // انیمیشن‌ها
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // نمایش پیام‌های PHP از query string
    <?php if (isset($_GET['success'])): ?>
        showToast('<?php echo h($_GET['success']); ?>', 'success');
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        showToast('<?php echo h($_GET['error']); ?>', 'error');
    <?php endif; ?>
</script>

</body>
</html>