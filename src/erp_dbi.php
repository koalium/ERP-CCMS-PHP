<?php
/**
 * Database Migration - ایجاد جداول دیتابیس
 */

require_once 'config.php';
require_once 'dbc.php';

class DatabaseMigration {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * اجرای تمام migrations
     */
    public function migrate() {
        $this->createUsersTable();
        $this->createPermissionsTable();
        $this->createUserPermissionsTable();
        $this->createLoginAttemptsTable();
        $this->createContactsTable();
        $this->createContactDetailsTable();
        $this->createNotesTable();
        $this->createCalendarEventsTable();
        $this->createRemindersTable();
        $this->createAccountsTable();
        $this->createTransactionsTable();
        $this->createProjectsTable();
        $this->createTasksTable();
        $this->createContractsTable();
        $this->createHREmployeesTable();
        $this->createHRLeavesTable();
        $this->createWarehousesTable();
        $this->createWarehouseItemsTable();
        $this->createWarehouseTransactionsTable();
        $this->createProductsTable();
        $this->createPartsTable();
        $this->createBOMTable();
        $this->createTendersTable();
        $this->createProposalsTable();
        $this->createQCFormsTable();
        $this->createMessagesTable();
        $this->createMeetingsTable();
        $this->createDocumentsTable();
        $this->createMTOTables();
        $this->createITPTables();
        $this->createNCRTable();
        $this->createMaterialRequestTables();
        $this->createLogsTable();
        
        echo "تمام جداول با موفقیت ایجاد شدند.\n";
    }
    
    /**
     * جدول کاربران
     */
    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            mobile VARCHAR(11),
            is_admin TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            last_login DATETIME,
            remember_token VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول مجوزها
     */
    private function createPermissionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            description TEXT,
            module VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول مجوزهای کاربران
     */
    private function createUserPermissionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            access_level TINYINT DEFAULT 1 COMMENT '0:none, 1:read, 2:write, 3:full',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_permission (user_id, permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول تلاش‌های ورود
     */
    private function createLoginAttemptsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username_ip (username, ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول مخاطبین
     */
    private function createContactsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('person', 'company', 'organization') NOT NULL,
            name VARCHAR(200) NOT NULL,
            company_name VARCHAR(200),
            national_id VARCHAR(20),
            registration_number VARCHAR(50),
            category VARCHAR(50),
            is_customer TINYINT(1) DEFAULT 0,
            is_vendor TINYINT(1) DEFAULT 0,
            is_employee TINYINT(1) DEFAULT 0,
            notes TEXT,
            tags VARCHAR(500),
            image VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_type (type),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول جزئیات مخاطبین (ایمیل، تلفن، آدرس و...)
     */
    private function createContactDetailsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS contact_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contact_id INT NOT NULL,
            type ENUM('email', 'phone', 'mobile', 'fax', 'address', 'website', 'social') NOT NULL,
            label VARCHAR(50),
            value TEXT NOT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
            INDEX idx_contact_type (contact_id, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول یادداشت‌ها
     */
    private function createNotesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content LONGTEXT,
            category VARCHAR(50),
            tags VARCHAR(500),
            is_private TINYINT(1) DEFAULT 1,
            shared_with TEXT COMMENT 'JSON array of user IDs',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول رویدادهای تقویم
     */
    private function createCalendarEventsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS calendar_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            start_date DATE NOT NULL,
            start_time TIME,
            end_date DATE,
            end_time TIME,
            location VARCHAR(255),
            category VARCHAR(50),
            color VARCHAR(7),
            is_all_day TINYINT(1) DEFAULT 0,
            reminder_minutes INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول یادآورها
     */
    private function createRemindersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            remind_date DATE NOT NULL,
            remind_time TIME NOT NULL,
            is_sent TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, remind_date, is_sent)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول حساب‌های مالی
     */
    private function createAccountsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('bank', 'cash', 'wallet', 'custom') NOT NULL,
            name VARCHAR(200) NOT NULL,
            account_number VARCHAR(50),
            iban VARCHAR(50),
            shaba VARCHAR(26),
            bank_name VARCHAR(100),
            currency VARCHAR(3) DEFAULT 'IRR',
            balance DECIMAL(20, 2) DEFAULT 0,
            category VARCHAR(50),
            owner_contact_id INT,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (owner_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
            INDEX idx_type (type),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول تراکنش‌های مالی
     */
    private function createTransactionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('transfer', 'income', 'expense') NOT NULL,
            status ENUM('draft', 'pending', 'confirmed', 'cancelled') DEFAULT 'draft',
            from_account_id INT,
            to_account_id INT,
            amount DECIMAL(20, 2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'IRR',
            exchange_rate DECIMAL(10, 4) DEFAULT 1,
            category VARCHAR(50),
            purpose TEXT,
            check_number VARCHAR(50),
            check_date DATE,
            reference_number VARCHAR(100),
            contact_id INT,
            project_id INT,
            attachments TEXT COMMENT 'JSON array',
            tags VARCHAR(500),
            notes TEXT,
            created_by INT NOT NULL,
            confirmed_by INT,
            transaction_date DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            confirmed_at DATETIME,
            FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
            FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_date (transaction_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول پروژه‌ها
     */
    private function createProjectsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            client_contact_id INT,
            manager_user_id INT,
            status ENUM('draft', 'planning', 'active', 'on_hold', 'completed', 'cancelled') DEFAULT 'draft',
            start_date DATE,
            end_date DATE,
            budget DECIMAL(20, 2),
            currency VARCHAR(3) DEFAULT 'IRR',
            location VARCHAR(255),
            tags VARCHAR(500),
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (client_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
            FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_status (status),
            INDEX idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول وظایف
     */
    private function createTasksTable() {
        $sql = "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            assigned_to INT,
            status ENUM('todo', 'in_progress', 'review', 'done', 'cancelled') DEFAULT 'todo',
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            start_date DATE,
            due_date DATE,
            completed_date DATE,
            progress INT DEFAULT 0,
            estimated_hours DECIMAL(10, 2),
            actual_hours DECIMAL(10, 2),
            parent_task_id INT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_project_status (project_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول قراردادها
     */
    private function createContractsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_number VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(200) NOT NULL,
            type VARCHAR(50),
            party_contact_id INT,
            project_id INT,
            amount DECIMAL(20, 2),
            currency VARCHAR(3) DEFAULT 'IRR',
            start_date DATE,
            end_date DATE,
            status ENUM('draft', 'pending', 'active', 'completed', 'terminated') DEFAULT 'draft',
            description TEXT,
            terms TEXT,
            attachments TEXT COMMENT 'JSON array',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (party_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_number (contract_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول کارکنان
     */
    private function createHREmployeesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS hr_employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_code VARCHAR(20) UNIQUE NOT NULL,
            contact_id INT NOT NULL,
            user_id INT,
            position VARCHAR(100),
            department VARCHAR(100),
            employment_type ENUM('full_time', 'part_time', 'contract', 'intern') NOT NULL,
            employment_date DATE,
            resignation_date DATE,
            salary DECIMAL(15, 2),
            salary_currency VARCHAR(3) DEFAULT 'IRR',
            insurance_number VARCHAR(50),
            tax_code VARCHAR(50),
            bank_account VARCHAR(50),
            status ENUM('active', 'suspended', 'resigned', 'terminated') DEFAULT 'active',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE RESTRICT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_code (employee_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول مرخصی‌ها
     */
    private function createHRLeavesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS hr_leaves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            type ENUM('annual', 'sick', 'unpaid', 'emergency', 'other') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days INT NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            approved_by INT,
            approved_at DATETIME,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_employee_date (employee_id, start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول انبارها
     */
    private function createWarehousesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS warehouses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            type ENUM('main', 'site', 'waste', 'project', 'electronic') NOT NULL,
            location VARCHAR(255),
            manager_user_id INT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_code (code),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول اقلام انبار
     */
    private function createWarehouseItemsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS warehouse_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            subcategory VARCHAR(100),
            unit VARCHAR(20),
            min_stock DECIMAL(15, 3) DEFAULT 0,
            max_stock DECIMAL(15, 3),
            current_stock DECIMAL(15, 3) DEFAULT 0,
            unit_price DECIMAL(15, 2),
            currency VARCHAR(3) DEFAULT 'IRR',
            specifications TEXT COMMENT 'JSON',
            attachments TEXT COMMENT 'JSON array',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_category (category),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول تراکنش‌های انبار
     */
    private function createWarehouseTransactionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS warehouse_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('in', 'out', 'transfer', 'adjustment') NOT NULL,
            status ENUM('pending', 'approved', 'completed', 'rejected') DEFAULT 'pending',
            warehouse_id INT NOT NULL,
            item_id INT NOT NULL,
            quantity DECIMAL(15, 3) NOT NULL,
            unit_price DECIMAL(15, 2),
            total_price DECIMAL(20, 2),
            reference_number VARCHAR(100),
            from_warehouse_id INT,
            to_warehouse_id INT,
            project_id INT,
            contact_id INT,
            reason TEXT,
            notes TEXT,
            requested_by INT,
            approved_by INT,
            transaction_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME,
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
            FOREIGN KEY (item_id) REFERENCES warehouse_items(id) ON DELETE RESTRICT,
            FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
            FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
            FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_warehouse_item (warehouse_id, item_id),
            INDEX idx_date (transaction_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول محصولات
     */
    private function createProductsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            type VARCHAR(50),
            description TEXT,
            specifications TEXT COMMENT 'JSON',
            parent_product_id INT,
            version VARCHAR(20),
            status ENUM('development', 'active', 'obsolete') DEFAULT 'development',
            attachments TEXT COMMENT 'JSON array',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_code (code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول قطعات
     */
    private function createPartsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS parts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_number VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            specifications TEXT COMMENT 'JSON',
            unit VARCHAR(20),
            weight DECIMAL(10, 3),
            material VARCHAR(100),
            supplier_contact_id INT,
            unit_price DECIMAL(15, 2),
            currency VARCHAR(3) DEFAULT 'IRR',
            lead_time_days INT,
            min_order_qty DECIMAL(10, 2),
            drawings TEXT COMMENT 'JSON array',
            status ENUM('active', 'obsolete', 'discontinued') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
            INDEX idx_part_number (part_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول BOM (Bill of Materials)
     */
    private function createBOMTable() {
        $sql = "CREATE TABLE IF NOT EXISTS bom (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            part_id INT NOT NULL,
            quantity DECIMAL(10, 3) NOT NULL,
            unit VARCHAR(20),
            reference_designator VARCHAR(50),
            notes TEXT,
            version VARCHAR(20),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE RESTRICT,
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول مناقصات
     */
    private function createTendersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS tenders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tender_number VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(200) NOT NULL,
            client VARCHAR(200),
            description TEXT,
            status ENUM('identified', 'reviewing', 'proposal_sent', 'won', 'lost', 'cancelled') DEFAULT 'identified',
            deadline_date DATE,
            submission_date DATE,
            opening_date DATE,
            estimated_value DECIMAL(20, 2),
            currency VARCHAR(3) DEFAULT 'IRR',
            category VARCHAR(100),
            location VARCHAR(255),
            attachments TEXT COMMENT 'JSON array',
            notes TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_number (tender_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول پیشنهادات
     */
    private function createProposalsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS proposals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            proposal_number VARCHAR(50) UNIQUE NOT NULL,
            tender_id INT,
            project_id INT,
            type ENUM('technical', 'financial', 'combined', 'final') NOT NULL,
            title VARCHAR(200) NOT NULL,
            status ENUM('draft', 'review', 'submitted', 'accepted', 'rejected') DEFAULT 'draft',
            total_price DECIMAL(20, 2),
            currency VARCHAR(3) DEFAULT 'IRR',
            validity_days INT,
            delivery_time_days INT,
            payment_terms TEXT,
            technical_specs TEXT,
            content TEXT,
            attachments TEXT COMMENT 'JSON array',
            prepared_by INT NOT NULL,
            reviewed_by INT,
            approved_by INT,
            submitted_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE SET NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_number (proposal_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول فرم‌های کنترل کیفیت
     */
    private function createQCFormsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS qc_forms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            form_number VARCHAR(50) UNIQUE NOT NULL,
            type VARCHAR(50) NOT NULL,
            project_id INT,
            product_id INT,
            title VARCHAR(200) NOT NULL,
            status ENUM('open', 'in_progress', 'completed', 'approved', 'rejected') DEFAULT 'open',
            inspection_date DATE,
            inspector_user_id INT,
            result ENUM('pass', 'fail', 'conditional') DEFAULT NULL,
            checklist TEXT COMMENT 'JSON',
            findings TEXT,
            corrective_actions TEXT,
            attachments TEXT COMMENT 'JSON array',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (inspector_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_number (form_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول پیام‌ها
     */
    private function createMessagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT,
            group_id INT,
            subject VARCHAR(200),
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at DATETIME,
            attachments TEXT COMMENT 'JSON array',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_sender (sender_id),
            INDEX idx_receiver (receiver_id),
            INDEX idx_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول جلسات
     */
    private function createMeetingsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS meetings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_number VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(200) NOT NULL,
            type VARCHAR(50),
            meeting_date DATE NOT NULL,
            meeting_time TIME NOT NULL,
            duration_minutes INT,
            location VARCHAR(255),
            agenda TEXT,
            attendees TEXT COMMENT 'JSON array of user IDs',
            minutes TEXT,
            decisions TEXT,
            action_items TEXT COMMENT 'JSON array',
            status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
            attachments TEXT COMMENT 'JSON array',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_number (meeting_number),
            INDEX idx_date (meeting_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول اسناد
     */
    private function createDocumentsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_number VARCHAR(50) UNIQUE,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            type VARCHAR(50),
            category VARCHAR(100),
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            mime_type VARCHAR(100),
            project_id INT,
            contract_id INT,
            related_type VARCHAR(50),
            related_id INT,
            tags VARCHAR(500),
            uploaded_by INT NOT NULL,
            is_public TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_type (type),
            INDEX idx_category (category),
            INDEX idx_doc_number (doc_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        
        // جدول نسخه‌های اسناد
        $sql2 = "CREATE TABLE IF NOT EXISTS document_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            version_number DECIMAL(10,2) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            status ENUM('draft', 'review', 'approved', 'obsolete', 'superseded') DEFAULT 'draft',
            change_notes TEXT,
            uploaded_by INT NOT NULL,
            approved_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_doc_version (document_id, version_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql2);
    }
    
    /**
     * جدول MTO (Material Take-Off)
     */
    private function createMTOTables() {
        $sql = "CREATE TABLE IF NOT EXISTS mtos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mto_number VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            project_id INT,
            product_id INT,
            status ENUM('draft', 'review', 'approved', 'issued', 'rejected') DEFAULT 'draft',
            created_by INT NOT NULL,
            approved_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            approved_at DATETIME,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_mto_number (mto_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        
        // جدول اقلام MTO
        $sql2 = "CREATE TABLE IF NOT EXISTS mto_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mto_id INT NOT NULL,
            part_id INT,
            item_code VARCHAR(50),
            description VARCHAR(500),
            quantity DECIMAL(15,3) NOT NULL,
            unit VARCHAR(20),
            unit_price DECIMAL(15,2),
            notes TEXT,
            FOREIGN KEY (mto_id) REFERENCES mtos(id) ON DELETE CASCADE,
            FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql2);
    }
    
    /**
     * جدول ITP (Inspection and Test Plan)
     */
    private function createITPTables() {
        $sql = "CREATE TABLE IF NOT EXISTS itps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            itp_number VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            project_id INT,
            product_id INT,
            category VARCHAR(100),
            status ENUM('draft', 'active', 'in_progress', 'completed', 'on_hold') DEFAULT 'draft',
            created_by INT NOT NULL,
            approved_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_itp_number (itp_number),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        
        // جدول نقاط بازرسی ITP
        $sql2 = "CREATE TABLE IF NOT EXISTS itp_checkpoints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            itp_id INT NOT NULL,
            checkpoint_number VARCHAR(50),
            description TEXT NOT NULL,
            acceptance_criteria TEXT,
            inspection_stage VARCHAR(100),
            hold_point TINYINT(1) DEFAULT 0,
            witness_point TINYINT(1) DEFAULT 0,
            status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
            inspector_user_id INT,
            inspection_date DATE,
            result TEXT,
            attachments TEXT,
            FOREIGN KEY (itp_id) REFERENCES itps(id) ON DELETE CASCADE,
            FOREIGN KEY (inspector_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql2);
    }
    
    /**
     * جدول NCR (Non-Conformance Report)
     */
    private function createNCRTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ncrs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ncr_number VARCHAR(50) UNIQUE NOT NULL,
            reference_number VARCHAR(50),
            project_id INT,
            product_id INT,
            location VARCHAR(255),
            nonconformance TEXT NOT NULL,
            description TEXT,
            severity ENUM('critical', 'major', 'minor', 'observation') NOT NULL,
            status ENUM('open', 'investigating', 'in_correction', 'closed', 'rejected') DEFAULT 'open',
            reported_by INT NOT NULL,
            reported_date DATE NOT NULL,
            assigned_to INT,
            root_cause TEXT,
            corrective_action TEXT,
            preventive_action TEXT,
            closed_by INT,
            closed_date DATE,
            verification TEXT,
            attachments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_ncr_number (ncr_number),
            INDEX idx_status (status),
            INDEX idx_severity (severity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * جدول Material Request (درخواست مواد)
     */
    private function createMaterialRequestTables() {
        $sql = "CREATE TABLE IF NOT EXISTS material_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mr_number VARCHAR(50) UNIQUE NOT NULL,
            project_id INT,
            warehouse_id INT,
            purpose TEXT NOT NULL,
            priority ENUM('urgent', 'high', 'normal', 'low') DEFAULT 'normal',
            required_date DATE,
            status ENUM('pending', 'approved', 'partially_issued', 'issued', 'rejected', 'cancelled') DEFAULT 'pending',
            requested_by INT NOT NULL,
            approved_by INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            approved_at DATETIME,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
            FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_mr_number (mr_number),
            INDEX idx_status (status),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        
        // جدول اقلام درخواست مواد
        $sql2 = "CREATE TABLE IF NOT EXISTS mr_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mr_id INT NOT NULL,
            item_id INT,
            item_code VARCHAR(50),
            description VARCHAR(500),
            quantity DECIMAL(15,3) NOT NULL,
            unit VARCHAR(20),
            quantity_issued DECIMAL(15,3) DEFAULT 0,
            notes TEXT,
            FOREIGN KEY (mr_id) REFERENCES material_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES warehouse_items(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql2);
    }
    
    /**
     * جدول لاگ‌های سیستم
     */
    private function createLogsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            module VARCHAR(50) NOT NULL,
            record_id INT,
            old_data TEXT COMMENT 'JSON',
            new_data TEXT COMMENT 'JSON',
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_module (module),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
}

// اجرای migration در صورت فراخوانی مستقیم فایل
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $migration = new DatabaseMigration();
    $migration->migrate();
}
?>