<?php
/**
 * جداول مربوط به بخش تولید
 * این کدها باید به فایل dbi.php اضافه شوند
 */

/**
 * جدول دستورات کار (Work Orders)
 */
private function createWorkOrdersTable() {
    $sql = "CREATE TABLE IF NOT EXISTS work_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_number VARCHAR(50) UNIQUE NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        project_id INT,
        product_id INT,
        quantity DECIMAL(15, 3) NOT NULL DEFAULT 1,
        unit VARCHAR(20) DEFAULT 'عدد',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        start_date DATE,
        due_date DATE,
        completed_date DATE,
        assigned_to INT,
        estimated_hours DECIMAL(10, 2),
        actual_hours DECIMAL(10, 2),
        progress INT DEFAULT 0,
        specifications TEXT,
        notes TEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
        INDEX idx_number (work_order_number),
        INDEX idx_status (status),
        INDEX idx_priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول گزارش‌های کار (Work Reports)
 */
private function createWorkReportsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS work_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_number VARCHAR(50) UNIQUE NOT NULL,
        work_order_id INT NOT NULL,
        report_date DATE NOT NULL,
        work_hours DECIMAL(10, 2) NOT NULL,
        progress_percentage INT NOT NULL DEFAULT 0,
        work_description TEXT NOT NULL,
        problems TEXT,
        next_steps TEXT,
        quality_status ENUM('excellent', 'good', 'acceptable', 'needs_improvement') DEFAULT 'acceptable',
        notes TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        reported_by INT NOT NULL,
        approved_by INT,
        approved_at DATETIME,
        approval_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_number (report_number),
        INDEX idx_work_order (work_order_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول درخواست‌های متریال (Material Requests)
 */
private function createMaterialRequestsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS material_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_number VARCHAR(50) UNIQUE NOT NULL,
        work_order_id INT,
        project_id INT,
        title VARCHAR(200) NOT NULL,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        required_date DATE,
        purpose TEXT,
        notes TEXT,
        status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        requested_by INT NOT NULL,
        approved_by INT,
        approved_at DATETIME,
        approval_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_number (request_number),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول آیتم‌های درخواست متریال
 */
private function createMaterialRequestItemsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS material_request_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity DECIMAL(15, 3) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES warehouse_items(id) ON DELETE RESTRICT,
        INDEX idx_request (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول اعلان‌ها (Notifications)
 */
private function createNotificationsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT,
        type VARCHAR(50),
        related_type VARCHAR(50),
        related_id INT,
        is_read TINYINT(1) DEFAULT 0,
        read_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * در متد migrate() این فراخوانی‌ها را اضافه کنید:
 */
public function migrate() {
    // ... جداول قبلی ...
    
    $this->createWorkOrdersTable();
    $this->createWorkReportsTable();
    $this->createMaterialRequestsTable();
    $this->createMaterialRequestItemsTable();
    $this->createNotificationsTable();
    
    echo "تمام جداول با موفقیت ایجاد شدند.\n";
}
?>