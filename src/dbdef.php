<?php
/**
 * تزریق داده‌های پیش‌فرض به دیتابیس
 */

require_once 'config.php';
require_once 'dbc.php';

class DatabaseDefaults {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * تزریق تمام داده‌های پیش‌فرض
     */
    public function inject() {
        $this->injectAdminUser();
        $this->injectPermissions();
        $this->injectSettings();
        $this->injectWarehouses();
        $this->injectProjectItemTemplates();
        
        echo "داده‌های پیش‌فرض با موفقیت تزریق شدند.\n";
    }
    
    /**
     * ایجاد کاربر مدیر پیش‌فرض
     */
    private function injectAdminUser() {
        // چک کردن وجود کاربر admin
        if ($this->db->exists('users', 'username = :username', [':username' => 'admin'])) {
            echo "کاربر admin از قبل وجود دارد.\n";
            return;
        }
        
        // ایجاد کاربر admin
        $hashedPassword = password_hash('654321', HASH_ALGO, ['cost' => HASH_COST]);
        
        $userId = $this->db->insert('users', [
            'username' => 'admin',
            'password' => $hashedPassword,
            'fullname' => 'مدیر سیستم',
            'email' => 'admin@esmartis.ir',
            'is_admin' => 1,
            'is_active' => 1
        ]);
        
        if ($userId) {
            echo "کاربر مدیر (admin) با موفقیت ایجاد شد.\n";
            echo "نام کاربری: admin\n";
            echo "رمز عبور: 654321\n";
            echo "*** لطفاً رمز عبور را تغییر دهید! ***\n";
        }
    }
    
    /**
     * تزریق مجوزهای پیش‌فرض
     */
    private function injectPermissions() {
        $permissions = [
            // مجوزهای مدیریتی
            ['name' => 'admin_users', 'display_name' => 'مدیریت کاربران', 'module' => 'admin', 'description' => 'مدیریت کاربران سیستم'],
            ['name' => 'admin_permissions', 'display_name' => 'مدیریت مجوزها', 'module' => 'admin', 'description' => 'تعیین مجوزهای دسترسی'],
            ['name' => 'admin_logs', 'display_name' => 'مشاهده لاگ‌ها', 'module' => 'admin', 'description' => 'مشاهده لاگ‌های سیستم'],
            ['name' => 'admin_settings', 'display_name' => 'تنظیمات سیستم', 'module' => 'admin', 'description' => 'تنظیمات عمومی سیستم'],
            
            // مجوزهای مخاطبین
            ['name' => 'contacts_read', 'display_name' => 'مشاهده مخاطبین', 'module' => 'contacts', 'description' => 'مشاهده لیست مخاطبین'],
            ['name' => 'contacts_write', 'display_name' => 'ویرایش مخاطبین', 'module' => 'contacts', 'description' => 'افزودن و ویرایش مخاطبین'],
            ['name' => 'contacts_delete', 'display_name' => 'حذف مخاطبین', 'module' => 'contacts', 'description' => 'حذف مخاطبین'],
            
            // مجوزهای مالی
            ['name' => 'financial_read', 'display_name' => 'مشاهده امور مالی', 'module' => 'financial', 'description' => 'مشاهده حساب‌ها و تراکنش‌ها'],
            ['name' => 'financial_write', 'display_name' => 'ثبت تراکنش', 'module' => 'financial', 'description' => 'ثبت و ویرایش تراکنش‌ها'],
            ['name' => 'financial_approve', 'display_name' => 'تایید تراکنش', 'module' => 'financial', 'description' => 'تایید تراکنش‌های مالی'],
            ['name' => 'financial_accounts', 'display_name' => 'مدیریت حساب‌ها', 'module' => 'financial', 'description' => 'مدیریت حساب‌های بانکی'],
            
            // مجوزهای پروژه
            ['name' => 'projects_read', 'display_name' => 'مشاهده پروژه‌ها', 'module' => 'projects', 'description' => 'مشاهده لیست پروژه‌ها'],
            ['name' => 'projects_write', 'display_name' => 'ویرایش پروژه‌ها', 'module' => 'projects', 'description' => 'افزودن و ویرایش پروژه‌ها'],
            ['name' => 'projects_tasks', 'display_name' => 'مدیریت وظایف', 'module' => 'projects', 'description' => 'مدیریت وظایف پروژه'],
            
            // مجوزهای قراردادها
            ['name' => 'contracts_read', 'display_name' => 'مشاهده قراردادها', 'module' => 'contracts', 'description' => 'مشاهده قراردادها'],
            ['name' => 'contracts_write', 'display_name' => 'ویرایش قراردادها', 'module' => 'contracts', 'description' => 'افزودن و ویرایش قراردادها'],
            ['name' => 'contracts_approve', 'display_name' => 'تایید قراردادها', 'module' => 'contracts', 'description' => 'تایید نهایی قراردادها'],
            
            // مجوزهای منابع انسانی
            ['name' => 'hr_read', 'display_name' => 'مشاهده منابع انسانی', 'module' => 'hr', 'description' => 'مشاهده اطلاعات پرسنلی'],
            ['name' => 'hr_write', 'display_name' => 'ویرایش پرسنل', 'module' => 'hr', 'description' => 'افزودن و ویرایش اطلاعات پرسنلی'],
            ['name' => 'hr_salary', 'display_name' => 'مدیریت حقوق', 'module' => 'hr', 'description' => 'مدیریت حقوق و دستمزد'],
            ['name' => 'hr_leaves', 'display_name' => 'مدیریت مرخصی', 'module' => 'hr', 'description' => 'مدیریت مرخصی‌ها'],
            
            // مجوزهای انبار
            ['name' => 'warehouse_read', 'display_name' => 'مشاهده انبار', 'module' => 'warehouse', 'description' => 'مشاهده موجودی انبار'],
            ['name' => 'warehouse_in', 'display_name' => 'ورود به انبار', 'module' => 'warehouse', 'description' => 'ثبت ورود کالا'],
            ['name' => 'warehouse_out', 'display_name' => 'خروج از انبار', 'module' => 'warehouse', 'description' => 'ثبت خروج کالا'],
            ['name' => 'warehouse_manage', 'display_name' => 'مدیریت انبار', 'module' => 'warehouse', 'description' => 'مدیریت کامل انبار'],
            
            // مجوزهای تدارکات
            ['name' => 'procurement_read', 'display_name' => 'مشاهده تدارکات', 'module' => 'procurement', 'description' => 'مشاهده درخواست‌های خرید'],
            ['name' => 'procurement_request', 'display_name' => 'درخواست خرید', 'module' => 'procurement', 'description' => 'ثبت درخواست خرید'],
            ['name' => 'procurement_price', 'display_name' => 'استعلام قیمت', 'module' => 'procurement', 'description' => 'استعلام و ثبت قیمت'],
            ['name' => 'procurement_approve', 'display_name' => 'تایید خرید', 'module' => 'procurement', 'description' => 'تایید درخواست‌های خرید'],
            
            // مجوزهای مهندسی
            ['name' => 'engineering_read', 'display_name' => 'مشاهده مهندسی', 'module' => 'engineering', 'description' => 'مشاهده اسناد فنی'],
            ['name' => 'engineering_write', 'display_name' => 'ویرایش مهندسی', 'module' => 'engineering', 'description' => 'افزودن و ویرایش اسناد فنی'],
            ['name' => 'engineering_approve', 'display_name' => 'تایید فنی', 'module' => 'engineering', 'description' => 'تایید اسناد فنی'],
            ['name' => 'engineering_products', 'display_name' => 'مدیریت محصولات', 'module' => 'engineering', 'description' => 'مدیریت محصولات و قطعات'],
            
            // مجوزهای تولید
            ['name' => 'production_read', 'display_name' => 'مشاهده تولید', 'module' => 'production', 'description' => 'مشاهده فرآیند تولید'],
            ['name' => 'production_write', 'display_name' => 'ثبت تولید', 'module' => 'production', 'description' => 'ثبت دستور کار و گزارش'],
            ['name' => 'production_manage', 'display_name' => 'مدیریت تولید', 'module' => 'production', 'description' => 'مدیریت کامل تولید'],
            
            // مجوزهای کنترل کیفیت
            ['name' => 'qc_read', 'display_name' => 'مشاهده کیفیت', 'module' => 'qc', 'description' => 'مشاهده فرم‌های کنترل کیفیت'],
            ['name' => 'qc_write', 'display_name' => 'ثبت کیفیت', 'module' => 'qc', 'description' => 'ثبت فرم‌های کنترل کیفیت'],
            ['name' => 'qc_approve', 'display_name' => 'تایید کیفیت', 'module' => 'qc', 'description' => 'تایید نتایج کنترل کیفیت'],
            
            // مجوزهای بازرگانی و فروش
            ['name' => 'marketing_read', 'display_name' => 'مشاهده بازرگانی', 'module' => 'marketing', 'description' => 'مشاهده مناقصات و پیشنهادات'],
            ['name' => 'marketing_write', 'display_name' => 'ویرایش بازرگانی', 'module' => 'marketing', 'description' => 'افزودن و ویرایش مناقصات'],
            ['name' => 'marketing_tenders', 'display_name' => 'مدیریت مناقصات', 'module' => 'marketing', 'description' => 'مدیریت کامل مناقصات'],
            ['name' => 'sell_read', 'display_name' => 'مشاهده فروش', 'module' => 'sell', 'description' => 'مشاهده اطلاعات فروش'],
            ['name' => 'sell_write', 'display_name' => 'ثبت فروش', 'module' => 'sell', 'description' => 'ثبت و پیگیری فروش'],
            
            // مجوزهای عمومی
            ['name' => 'calendar_personal', 'display_name' => 'تقویم شخصی', 'module' => 'calendar', 'description' => 'مدیریت تقویم شخصی'],
            ['name' => 'calendar_shared', 'display_name' => 'تقویم مشترک', 'module' => 'calendar', 'description' => 'مشاهده تقویم مشترک'],
            ['name' => 'notes_personal', 'display_name' => 'یادداشت‌های شخصی', 'module' => 'notes', 'description' => 'مدیریت یادداشت‌های شخصی'],
            ['name' => 'messenger_use', 'display_name' => 'استفاده از پیام‌رسان', 'module' => 'messenger', 'description' => 'ارسال و دریافت پیام'],
            ['name' => 'meetings_read', 'display_name' => 'مشاهده جلسات', 'module' => 'meetings', 'description' => 'مشاهده صورتجلسات'],
            ['name' => 'meetings_write', 'display_name' => 'ثبت جلسات', 'module' => 'meetings', 'description' => 'ثبت و ویرایش جلسات'],
            ['name' => 'documents_read', 'display_name' => 'مشاهده اسناد', 'module' => 'documents', 'description' => 'مشاهده اسناد'],
            ['name' => 'documents_upload', 'display_name' => 'آپلود اسناد', 'module' => 'documents', 'description' => 'آپلود و مدیریت اسناد']
        ];
        
        foreach ($permissions as $permission) {
            // چک کردن عدم وجود مجوز
            if (!$this->db->exists('permissions', 'name = :name', [':name' => $permission['name']])) {
                $this->db->insert('permissions', $permission);
            }
        }
        
        echo "مجوزهای پیش‌فرض با موفقیت تزریق شدند.\n";
    }
    
    /**
     * تنظیمات پیش‌فرض سیستم
     */
    private function injectSettings() {
        // ایجاد جدول تنظیمات اگر وجود ندارد
        $sql = "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) UNIQUE NOT NULL,
            value TEXT,
            type VARCHAR(50) DEFAULT 'string',
            category VARCHAR(50),
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        
        $settings = [
            ['key' => 'site_language', 'value' => 'fa', 'type' => 'string', 'category' => 'general', 'description' => 'زبان پیش‌فرض سیستم'],
            ['key' => 'site_theme', 'value' => 'dark-blue', 'type' => 'string', 'category' => 'appearance', 'description' => 'تم پیش‌فرض'],
            ['key' => 'date_format', 'value' => 'jalali', 'type' => 'string', 'category' => 'general', 'description' => 'فرمت تاریخ (jalali/gregorian)'],
            ['key' => 'timezone', 'value' => 'Asia/Tehran', 'type' => 'string', 'category' => 'general', 'description' => 'منطقه زمانی'],
            ['key' => 'session_timeout', 'value' => '3600', 'type' => 'integer', 'category' => 'security', 'description' => 'مدت زمان نشست (ثانیه)'],
            ['key' => 'max_upload_size', 'value' => '10485760', 'type' => 'integer', 'category' => 'files', 'description' => 'حداکثر حجم آپلود (بایت)'],
            ['key' => 'items_per_page', 'value' => '20', 'type' => 'integer', 'category' => 'general', 'description' => 'تعداد آیتم در هر صفحه'],
            ['key' => 'company_name', 'value' => 'eSmartis', 'type' => 'string', 'category' => 'company', 'description' => 'نام شرکت'],
            ['key' => 'company_address', 'value' => '', 'type' => 'string', 'category' => 'company', 'description' => 'آدرس شرکت'],
            ['key' => 'company_phone', 'value' => '', 'type' => 'string', 'category' => 'company', 'description' => 'تلفن شرکت'],
            ['key' => 'company_email', 'value' => 'info@esmartis.ir', 'type' => 'string', 'category' => 'company', 'description' => 'ایمیل شرکت'],
            ['key' => 'currency_default', 'value' => 'IRR', 'type' => 'string', 'category' => 'financial', 'description' => 'واحد پول پیش‌فرض'],
            ['key' => 'enable_notifications', 'value' => '1', 'type' => 'boolean', 'category' => 'general', 'description' => 'فعال‌سازی اعلان‌ها'],
            ['key' => 'enable_email', 'value' => '0', 'type' => 'boolean', 'category' => 'general', 'description' => 'فعال‌سازی ارسال ایمیل']
        ];
        
        foreach ($settings as $setting) {
            if (!$this->db->exists('settings', '`key` = :key', [':key' => $setting['key']])) {
                $this->db->insert('settings', $setting);
            }
        }
        
        echo "تنظیمات پیش‌فرض با موفقیت تزریق شدند.\n";
    }
    
    /**
     * ایجاد انبارهای پیش‌فرض
     */
    private function injectWarehouses() {
        $warehouses = [
            ['code' => 'WH-MAIN', 'name' => 'انبار اصلی', 'type' => 'main'],
            ['code' => 'WH-SITE', 'name' => 'انبار پای کار', 'type' => 'site'],
            ['code' => 'WH-WASTE', 'name' => 'انبار زایعات', 'type' => 'waste'],
            ['code' => 'WH-ELEC', 'name' => 'انبار الکترونیک', 'type' => 'electronic']
        ];
        
        foreach ($warehouses as $warehouse) {
            if (!$this->db->exists('warehouses', 'code = :code', [':code' => $warehouse['code']])) {
                $this->db->insert('warehouses', $warehouse);
            }
        }
        
        echo "انبارهای پیش‌فرض با موفقیت ایجاد شدند.\n";
    }
    
    /**
     * تزریق قالب‌های پیش‌فرض آیتم‌های پروژه
     */
    private function injectProjectItemTemplates() {
        $templates = [
            [
                'template_code' => 'TEXTILE_STATION',
                'template_name' => 'ایستگاه کنترل هوای صنعت نساجی',
                'item_type' => 'textile_station',
                'description' => 'سیستم کامل کنترل دما و رطوبت برای سالن‌های نساجی',
                'default_specifications' => json_encode([
                    'air_flow' => 'متغیر',
                    'cooling_capacity' => 'بر اساس متراژ',
                    'heating_capacity' => 'بر اساس متراژ',
                    'humidity_control' => 'دارد',
                    'filtration' => 'G4 + F7'
                ])
            ],
            [
                'template_code' => 'TEXTILE_AC_STATION',
                'template_name' => 'ایستگاه تهویه صنعت نساجی',
                'item_type' => 'textile_ac_station',
                'description' => 'ایستگاه تهویه مطبوع ویژه صنایع نساجی با قابلیت کنترل رطوبت',
                'default_specifications' => json_encode([
                    'capacity_cfm' => '',
                    'static_pressure' => '',
                    'filter_type' => 'G4, F7',
                    'humidifier' => 'ultrasonic'
                ])
            ],
            [
                'template_code' => 'TEXTILE_FILTER_PLANT',
                'template_name' => 'فیلترخانه صنعت نساجی',
                'item_type' => 'textile_filter_plant',
                'description' => 'سیستم فیلتراسیون مرکزی برای تصفیه هوای ورودی',
                'default_specifications' => json_encode([
                    'filter_stages' => 'G4, F7, F9',
                    'airflow_capacity' => '',
                    'pressure_drop' => '',
                    'filter_area' => ''
                ])
            ],
            [
                'template_code' => 'BOILER',
                'template_name' => 'دیگ بخار',
                'item_type' => 'boiler',
                'description' => 'دیگ بخار صنعتی',
                'default_specifications' => json_encode([
                    'capacity_ton' => '',
                    'pressure_bar' => '',
                    'fuel_type' => 'gas/diesel',
                    'efficiency' => '> 90%'
                ])
            ],
            [
                'template_code' => 'TEXTILE_AC_PLC',
                'template_name' => 'تابلو PLC ایستگاه تهویه نساجی',
                'item_type' => 'textile_ac_station_plc',
                'description' => 'سیستم کنترل اتوماتیک با PLC',
                'default_specifications' => json_encode([
                    'plc_brand' => 'Siemens/Schneider',
                    'io_count' => '',
                    'hmi_size' => '7-10 inch',
                    'communication' => 'Ethernet, ModBus'
                ])
            ],
            [
                'template_code' => 'FILTER_PLANT_PLC',
                'template_name' => 'تابلو PLC فیلترخانه',
                'item_type' => 'textile_filter_plant_plc',
                'description' => 'سیستم کنترل اتوماتیک فیلترخانه',
                'default_specifications' => json_encode([
                    'pressure_monitoring' => 'دارد',
                    'filter_clogging_alarm' => 'دارد',
                    'automatic_fan_control' => 'دارد'
                ])
            ],
            [
                'template_code' => 'BOILER_PLC',
                'template_name' => 'تابلو PLC دیگ بخار',
                'item_type' => 'boiler_plc',
                'description' => 'سیستم کنترل و ایمنی دیگ بخار',
                'default_specifications' => json_encode([
                    'burner_control' => 'دارد',
                    'level_control' => 'دارد',
                    'pressure_control' => 'دارد',
                    'safety_interlocks' => 'کامل'
                ])
            ],
            [
                'template_code' => 'LOUVER',
                'template_name' => 'لوور هوای تازه',
                'item_type' => 'louver',
                'description' => 'لوور آلومینیومی برای ورود/خروج هوا',
                'default_specifications' => json_encode([
                    'material' => 'aluminum',
                    'blade_angle' => '45 degree',
                    'weather_protection' => 'دارد'
                ])
            ],
            [
                'template_code' => 'DUCT',
                'template_name' => 'کانال کشی',
                'item_type' => 'duct',
                'description' => 'شبکه کانال‌های هوا',
                'default_specifications' => json_encode([
                    'material' => 'galvanized steel',
                    'insulation' => '50mm rockwool',
                    'sealing' => 'class C'
                ])
            ],
            [
                'template_code' => 'DAMPER',
                'template_name' => 'دمپر',
                'item_type' => 'damper',
                'description' => 'دمپر کنترل جریان هوا',
                'default_specifications' => json_encode([
                    'type' => 'butterfly/multi-blade',
                    'actuator' => 'electric/pneumatic',
                    'control_signal' => '0-10V / 4-20mA'
                ])
            ],
            [
                'template_code' => 'AIRWASHER_LEGACY',
                'template_name' => 'ایرواشر قدیمی',
                'item_type' => 'airwasher_legacy',
                'description' => 'سیستم رطوبت‌زنی و خنک‌سازی تبخیری',
                'default_specifications' => json_encode([
                    'water_circulation' => 'پمپ سیرکولاسیون',
                    'nozzle_type' => 'spray nozzles',
                    'pad_material' => 'cellulose/plastic'
                ])
            ],
            [
                'template_code' => 'AIRWASHER_FOG',
                'template_name' => 'سیستم مه‌پاش',
                'item_type' => 'airwasher_fog',
                'description' => 'سیستم رطوبت‌زنی فوق ریز (فوگر)',
                'default_specifications' => json_encode([
                    'pump_pressure' => '70 bar',
                    'nozzle_size' => '0.2mm',
                    'control_type' => 'modulating'
                ])
            ],
            [
                'template_code' => 'AXIAL_FAN',
                'template_name' => 'فن محوری',
                'item_type' => 'axial_fan',
                'description' => 'فن جریان محوری',
                'default_specifications' => json_encode([
                    'airflow_m3h' => '',
                    'static_pressure_pa' => '',
                    'motor_power_kw' => '',
                    'drive_type' => 'direct/belt'
                ])
            ],
            [
                'template_code' => 'CENTRIFUGE_FAN',
                'template_name' => 'فن سانتریفیوژ',
                'item_type' => 'centrifuge_fan',
                'description' => 'فن گریز از مرکز',
                'default_specifications' => json_encode([
                    'type' => 'forward/backward curved',
                    'airflow_m3h' => '',
                    'static_pressure_pa' => '',
                    'motor_power_kw' => ''
                ])
            ],
            [
                'template_code' => 'FAN_RING',
                'template_name' => 'رینگ فن',
                'item_type' => 'fan_ring',
                'description' => 'رینگ نگهدارنده و محافظ فن',
                'default_specifications' => json_encode([
                    'material' => 'galvanized steel',
                    'fan_diameter' => '',
                    'mounting_type' => 'wall/roof'
                ])
            ],
            [
                'template_code' => 'FAN_PLENUM',
                'template_name' => 'پلنوم فن',
                'item_type' => 'fan_plenum',
                'description' => 'محفظه فن با قابلیت اتصال به کانال',
                'default_specifications' => json_encode([
                    'material' => 'galvanized steel',
                    'insulation' => '50mm',
                    'access_door' => 'دارد'
                ])
            ],
            [
                'template_code' => 'VENT',
                'template_name' => 'ونت تخلیه',
                'item_type' => 'vent',
                'description' => 'دریچه تخلیه هوا',
                'default_specifications' => json_encode([
                    'type' => 'gravity/powered',
                    'backdraft_damper' => 'دارد',
                    'material' => 'aluminum'
                ])
            ],
            [
                'template_code' => 'AIR_ROTARY_FILTER',
                'template_name' => 'فیلتر روتاری',
                'item_type' => 'air_rotary_filter',
                'description' => 'فیلتر خودکار با شستشوی چرخشی',
                'default_specifications' => json_encode([
                    'filter_class' => 'G4/F7',
                    'cleaning_system' => 'automatic washing',
                    'rotation_motor' => 'geared motor'
                ])
            ],
            [
                'template_code' => 'DUST_COLLECTOR',
                'template_name' => 'دست کلکتور',
                'item_type' => 'dust_collector',
                'description' => 'سیستم جمع‌آوری گرد و غبار',
                'default_specifications' => json_encode([
                    'type' => 'bag/cartridge',
                    'airflow_m3h' => '',
                    'filter_area_m2' => '',
                    'cleaning' => 'pulse jet'
                ])
            ],
            [
                'template_code' => 'AC_PACKAGE',
                'template_name' => 'پکیج تهویه مطبوع',
                'item_type' => 'air_condition_package',
                'description' => 'دستگاه تهویه مطبوع پکیج',
                'default_specifications' => json_encode([
                    'capacity_ton' => '',
                    'cooling_kw' => '',
                    'heating_kw' => '',
                    'refrigerant' => 'R410A/R32'
                ])
            ],
            [
                'template_code' => 'FAN_SILENCER',
                'template_name' => 'صداخفه‌کن فن',
                'item_type' => 'fan_silencer',
                'description' => 'صداخفه‌کن آکوستیک',
                'default_specifications' => json_encode([
                    'insertion_loss_db' => '15-25',
                    'length_m' => '1-2',
                    'material' => 'acoustic foam'
                ])
            ],
            [
                'template_code' => 'COIL_HEATING',
                'template_name' => 'کویل گرمایش',
                'item_type' => 'coil_heating',
                'description' => 'کویل گرمایش آب گرم یا بخار',
                'default_specifications' => json_encode([
                    'fluid_type' => 'hot water/steam',
                    'capacity_kw' => '',
                    'rows' => '3-6',
                    'fin_spacing' => '2.5mm'
                ])
            ],
            [
                'template_code' => 'AIR_COOLER',
                'template_name' => 'کولر آبی',
                'item_type' => 'air_cooler',
                'description' => 'کولر تبخیری',
                'default_specifications' => json_encode([
                    'airflow_m3h' => '',
                    'pad_thickness' => '100-150mm',
                    'pump_power' => '',
                    'water_tank' => ''
                ])
            ],
            [
                'template_code' => 'SHELL_TUBE',
                'template_name' => 'مبدل حرارتی Shell & Tube',
                'item_type' => 'shell_tube',
                'description' => 'مبدل حرارتی پوسته و لوله',
                'default_specifications' => json_encode([
                    'capacity_kw' => '',
                    'tube_material' => 'copper/stainless',
                    'shell_material' => 'carbon steel',
                    'passes' => '2/4'
                ])
            ],
            [
                'template_code' => 'CONTROL_BOARD',
                'template_name' => 'تابلو کنترل',
                'item_type' => 'control_board',
                'description' => 'تابلو برق و کنترل',
                'default_specifications' => json_encode([
                    'protection_ip' => 'IP54/IP65',
                    'mcb_type' => 'schneider/abb',
                    'contactor' => 'دارد',
                    'indicator_lights' => 'دارد'
                ])
            ],
            [
                'template_code' => 'CUSTOM',
                'template_name' => 'آیتم سفارشی',
                'item_type' => 'custom',
                'description' => 'آیتم سفارشی با مشخصات دلخواه',
                'default_specifications' => json_encode([])
            ]
        ];
        
        foreach ($templates as $template) {
            if (!$this->db->exists('project_item_templates', 'template_code = :code', [':code' => $template['template_code']])) {
                $this->db->insert('project_item_templates', $template);
            }
        }
        
        echo "قالب‌های پیش‌فرض آیتم‌های پروژه با موفقیت ایجاد شدند.\n";
    }
}

// اجرای injection در صورت فراخوانی مستقیم فایل
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $defaults = new DatabaseDefaults();
    $defaults->inject();
}
?>