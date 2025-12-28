# راهنمای نصب سریع eSmartis ERP

## 🚀 نصب در 5 دقیقه

### پیش‌نیازها
- PHP 7.4 یا بالاتر
- MySQL 5.7 یا MariaDB 10.2+
- Apache/Nginx
- افزونه‌های PHP: PDO, PDO_MySQL, mbstring, json

---

## گام 1: دانلود فایل‌ها

```bash
git clone https://github.com/your-repo/esmartis-erp.git
cd esmartis-erp
```

یا فایل ZIP را دانلود و extract کنید.

---

## گام 2: ایجاد دیتابیس

وارد MySQL شوید:
```bash
mysql -u root -p
```

دستورات زیر را اجرا کنید:
```sql
CREATE DATABASE esmartis_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'esmartis_user'@'localhost' IDENTIFIED BY 'esmartis1364';
GRANT ALL PRIVILEGES ON esmartis_erp.* TO 'esmartis_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## گام 3: تنظیم اطلاعات دیتابیس

فایل `config.php` را باز کنید و در صورت نیاز اطلاعات دیتابیس را ویرایش کنید:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'esmartis_erp');
define('DB_USER', 'esmartis_user');
define('DB_PASS', 'esmartis1364');
```

---

## گام 4: اجرای Migration

از terminal در پوشه پروژه:

```bash
php dbi.php
```

خروجی باید باشد: "تمام جداول با موفقیت ایجاد شدند."

سپس:
```bash
php dbdef.php
```

خروجی باید شامل:
- "کاربر مدیر (admin) با موفقیت ایجاد شد"
- "مجوزهای پیش‌فرض با موفقیت تزریق شدند"
- "تنظیمات پیش‌فرض با موفقیت تزریق شدند"

---

## گام 5: تنظیم مجوزها

```bash
chmod -R 755 /path/to/erp
chmod -R 777 uploads/
chmod -R 777 logs/

# ایجاد پوشه‌ها در صورت عدم وجود
mkdir -p uploads/messages
mkdir -p logs
```

---

## گام 6: دسترسی به سیستم

مرورگر خود را باز کنید و به آدرس زیر بروید:
```
http://localhost/erp/
```

یا:
```
http://your-domain.com/
```

---

## 🔐 اطلاعات ورود پیش‌فرض

**نام کاربری:** `admin`  
**رمز عبور:** `654321`

⚠️ **هشدار امنیتی مهم:**
- **حتماً** بعد از اولین ورود رمز عبور را تغییر دهید
- در محیط production از HTTPS استفاده کنید
- رمز دیتابیس را تغییر دهید

---

## 📋 چک‌لیست بعد از نصب

- [ ] وارد سیستم شدید؟
- [ ] رمز عبور admin را تغییر دادید؟
- [ ] تنظیمات سیستم را بررسی کردید؟
- [ ] مجوزهای کاربران را تنظیم کردید؟
- [ ] پوشه‌های uploads و logs مجوز نوشتن دارند؟

---

## 🔧 تنظیمات اضافی

### تنظیم Apache

فایل `.htaccess` در root پروژه:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]

# امنیت
<FilesMatch "^(config|dbc|dbi|dbdef)\.php$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

### تنظیم Nginx

در فایل کانفیگ سایت:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}

# محافظت از فایل‌های حساس
location ~ ^/(config|dbc|dbi|dbdef)\.php$ {
    deny all;
}
```

### تنظیم PHP

در `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 300
memory_limit = 256M
```

---

## 🐛 رفع مشکلات رایج

### خطا: Cannot connect to database
- اطلاعات دیتابیس در `config.php` را بررسی کنید
- مطمئن شوید MySQL در حال اجرا است
- دسترسی کاربر به دیتابیس را چک کنید

### خطا: Permission denied
```bash
chmod -R 755 /path/to/erp
chmod -R 777 uploads/
chmod -R 777 logs/
```

### صفحه سفید (White Screen)
- خطاها را در `logs/php-errors.log` بررسی کنید
- `display_errors = On` در php.ini (فقط در development)

### جداول ایجاد نشدند
- از terminal دستور `php dbi.php` را اجرا کنید
- خطاها را در output بررسی کنید

---

## 📞 دریافت کمک

اگر با مشکلی مواجه شدید:
1. فایل‌های لاگ را بررسی کنید: `logs/php-errors.log`
2. اطمینان حاصل کنید تمام پیش‌نیازها نصب شده‌اند
3. به بخش Issues در GitHub مراجعه کنید
4. با تیم پشتیبانی تماس بگیرید: support@esmartis.ir

---

## ✅ نصب موفقیت‌آمیز!

اگر همه مراحل را با موفقیت طی کردید، سیستم آماده استفاده است! 🎉

حالا می‌توانید:
- کاربران جدید اضافه کنید
- مجوزها را تنظیم کنید
- مخاطبین را وارد کنید
- پروژه‌ها را ایجاد کنید
- و...

**موفق باشید!** 🚀