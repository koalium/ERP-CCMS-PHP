<?php
/**
 * جداول اضافی منابع انسانی
 * این کد باید به dbi.php اضافه شود
 */


/**
 * جدول حضور و غیاب
 */
private function createAttendanceTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        check_in TIME,
        check_out TIME,
        work_hours DECIMAL(5, 2) DEFAULT 0,
        overtime_hours DECIMAL(5, 2) DEFAULT 0,
        status ENUM('present', 'absent', 'late', 'half_day', 'leave') DEFAULT 'present',
        notes TEXT,
        device_id VARCHAR(50) COMMENT 'شناسه دستگاه ساعت‌زنی',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (employee_id, attendance_date),
        INDEX idx_date (attendance_date),
        INDEX idx_employee_date (employee_id, attendance_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول تنظیمات مرخصی
 */
private function createLeaveTypesTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_leave_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type_code VARCHAR(20) UNIQUE NOT NULL,
        days_per_year INT DEFAULT 0,
        requires_approval TINYINT(1) DEFAULT 1,
        is_paid TINYINT(1) DEFAULT 1,
        max_consecutive_days INT DEFAULT 0,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول موجودی مرخصی
 */
private function createLeaveBalanceTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_leave_balance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        leave_type_id INT NOT NULL,
        year INT NOT NULL,
        total_days DECIMAL(5, 2) DEFAULT 0,
        used_days DECIMAL(5, 2) DEFAULT 0,
        remaining_days DECIMAL(5, 2) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_type_id) REFERENCES hr_leave_types(id) ON DELETE RESTRICT,
        UNIQUE KEY unique_balance (employee_id, leave_type_id, year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول وام‌ها
 */
private function createLoansTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        purpose TEXT,
        installments INT NOT NULL,
        monthly_amount DECIMAL(15, 2) NOT NULL,
        remaining_amount DECIMAL(15, 2) NOT NULL,
        start_date DATE,
        end_date DATE,
        status ENUM('pending', 'approved', 'rejected', 'active', 'completed', 'cancelled') DEFAULT 'pending',
        approved_by INT,
        approved_at DATETIME,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_employee (employee_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول پرداخت اقساط وام
 */
private function createLoanPaymentsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_loan_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        installment_number INT NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        payment_date DATE NOT NULL,
        status ENUM('pending', 'paid', 'skipped') DEFAULT 'pending',
        paid_at DATETIME,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (loan_id) REFERENCES hr_loans(id) ON DELETE CASCADE,
        INDEX idx_loan (loan_id),
        INDEX idx_date (payment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول اطلاعات حقوق
 */
private function createSalaryInfoTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_salary_info (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        base_salary DECIMAL(15, 2) NOT NULL,
        housing_allowance DECIMAL(15, 2) DEFAULT 0,
        transportation_allowance DECIMAL(15, 2) DEFAULT 0,
        food_allowance DECIMAL(15, 2) DEFAULT 0,
        family_allowance DECIMAL(15, 2) DEFAULT 0,
        other_allowances DECIMAL(15, 2) DEFAULT 0,
        insurance_deduction DECIMAL(15, 2) DEFAULT 0,
        tax_deduction DECIMAL(15, 2) DEFAULT 0,
        loan_deduction DECIMAL(15, 2) DEFAULT 0,
        other_deductions DECIMAL(15, 2) DEFAULT 0,
        effective_date DATE NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        INDEX idx_employee (employee_id),
        INDEX idx_date (effective_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول محاسبه حقوق ماهانه
 */
private function createMonthlySalariesTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_monthly_salaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        year INT NOT NULL,
        month INT NOT NULL,
        base_salary DECIMAL(15, 2) NOT NULL,
        total_allowances DECIMAL(15, 2) DEFAULT 0,
        overtime_amount DECIMAL(15, 2) DEFAULT 0,
        bonus_amount DECIMAL(15, 2) DEFAULT 0,
        gross_salary DECIMAL(15, 2) NOT NULL,
        total_deductions DECIMAL(15, 2) DEFAULT 0,
        net_salary DECIMAL(15, 2) NOT NULL,
        working_days INT DEFAULT 0,
        absent_days INT DEFAULT 0,
        leave_days INT DEFAULT 0,
        overtime_hours DECIMAL(5, 2) DEFAULT 0,
        status ENUM('draft', 'calculated', 'approved', 'paid') DEFAULT 'draft',
        paid_date DATE,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_salary (employee_id, year, month),
        INDEX idx_year_month (year, month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول ارزیابی عملکرد
 */
private function createEvaluationsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        evaluator_id INT NOT NULL,
        evaluation_period_start DATE NOT NULL,
        evaluation_period_end DATE NOT NULL,
        overall_score DECIMAL(5, 2),
        work_quality_score DECIMAL(5, 2),
        productivity_score DECIMAL(5, 2),
        teamwork_score DECIMAL(5, 2),
        innovation_score DECIMAL(5, 2),
        punctuality_score DECIMAL(5, 2),
        strengths TEXT,
        weaknesses TEXT,
        goals TEXT,
        comments TEXT,
        status ENUM('draft', 'pending', 'completed', 'approved') DEFAULT 'draft',
        approved_by INT,
        approved_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_employee (employee_id),
        INDEX idx_period (evaluation_period_start, evaluation_period_end)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول قراردادهای کاری
 */
private function createEmploymentContractsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_employment_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        contract_type ENUM('permanent', 'temporary', 'project', 'internship') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        salary DECIMAL(15, 2) NOT NULL,
        working_hours_per_week INT DEFAULT 40,
        probation_period_months INT DEFAULT 0,
        terms TEXT,
        status ENUM('draft', 'active', 'expired', 'terminated') DEFAULT 'draft',
        signed_date DATE,
        attachments TEXT COMMENT 'JSON array',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_employee (employee_id),
        INDEX idx_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول مدارک پرسنلی
 */
private function createEmployeeDocumentsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_employee_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        document_type VARCHAR(50) NOT NULL,
        document_number VARCHAR(100),
        title VARCHAR(200) NOT NULL,
        description TEXT,
        file_path VARCHAR(500),
        issue_date DATE,
        expiry_date DATE,
        is_verified TINYINT(1) DEFAULT 0,
        verified_by INT,
        verified_at DATETIME,
        uploaded_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_employee (employee_id),
        INDEX idx_type (document_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول استعفا و اخراج
 */
private function createResignationsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_resignations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        type ENUM('resignation', 'termination', 'retirement') NOT NULL,
        submission_date DATE NOT NULL,
        effective_date DATE NOT NULL,
        reason TEXT,
        notice_period_days INT DEFAULT 0,
        clearance_status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        final_settlement_amount DECIMAL(15, 2),
        severance_pay DECIMAL(15, 2),
        unused_leave_days DECIMAL(5, 2),
        status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        approved_by INT,
        approved_at DATETIME,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_employee (employee_id),
        INDEX idx_dates (submission_date, effective_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول صندوق کارگری
 */
private function createWorkersFundTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_workers_fund (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        transaction_date DATE NOT NULL,
        type ENUM('contribution', 'withdrawal', 'interest', 'loan', 'adjustment') NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        balance DECIMAL(15, 2) NOT NULL,
        description TEXT,
        reference_id INT COMMENT 'ID مرجع (مثل شناسه وام)',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_employee_date (employee_id, transaction_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول تنظیمات دستگاه ساعت‌زنی
 */
private function createAttendanceDevicesTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_attendance_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(50) UNIQUE NOT NULL,
        device_name VARCHAR(100) NOT NULL,
        device_type VARCHAR(50),
        ip_address VARCHAR(45),
        port INT,
        location VARCHAR(200),
        api_endpoint VARCHAR(500),
        api_key VARCHAR(255),
        is_active TINYINT(1) DEFAULT 1,
        last_sync DATETIME,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول درخواست‌های استخدام
 */
private function createRecruitmentTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_recruitment_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        position_title VARCHAR(200) NOT NULL,
        department VARCHAR(100),
        employment_type ENUM('full_time', 'part_time', 'contract', 'intern') NOT NULL,
        number_of_positions INT DEFAULT 1,
        required_qualifications TEXT,
        job_description TEXT,
        salary_range_min DECIMAL(15, 2),
        salary_range_max DECIMAL(15, 2),
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        deadline_date DATE,
        status ENUM('open', 'in_progress', 'filled', 'cancelled') DEFAULT 'open',
        requested_by INT NOT NULL,
        approved_by INT,
        approved_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

/**
 * جدول متقاضیان استخدام
 */
private function createApplicantsTable() {
    $sql = "CREATE TABLE IF NOT EXISTS hr_applicants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recruitment_id INT NOT NULL,
        full_name VARCHAR(200) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        resume_path VARCHAR(500),
        cover_letter TEXT,
        education_level VARCHAR(100),
        years_of_experience INT,
        current_position VARCHAR(100),
        expected_salary DECIMAL(15, 2),
        interview_date DATETIME,
        interview_score DECIMAL(5, 2),
        interview_notes TEXT,
        status ENUM('applied', 'screening', 'interview', 'offer', 'hired', 'rejected') DEFAULT 'applied',
        rejection_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recruitment_id) REFERENCES hr_recruitment_requests(id) ON DELETE CASCADE,
        INDEX idx_recruitment (recruitment_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $this->db->query($sql);
}

createAttendanceTable();
createLeaveTypesTable();
createLeaveBalanceTable();
createLoansTable();
createLoanPaymentsTable();
createSalaryInfoTable();
createMonthlySalariesTable();
createEvaluationsTable();
createEmploymentContractsTable();
createEmployeeDocumentsTable();
createResignationsTable();
createApplicantsTable();
createRecruitmentTable();
createAttendanceDevicesTable();
createWorkersFundTable();
?>