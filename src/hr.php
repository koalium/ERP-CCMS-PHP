<?php
/**
 * داشبورد منابع انسانی (HR)
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('hr', PERMISSION_READ)) {
    die('شما مجوز دسترسی به بخش منابع انسانی را ندارید.');
}

// دریافت آمار HR
$hrStats = [
    'total_employees' => db()->count('hr_employees', "status = 'active'"),
    'pending_leaves' => db()->count('hr_leaves', "status = 'pending'"),
    'on_leave_today' => db()->count('hr_leaves', "status = 'approved' AND CURDATE() BETWEEN start_date AND end_date"),
    'new_hires_month' => db()->count('hr_employees', "MONTH(employment_date) = MONTH(CURDATE()) AND YEAR(employment_date) = YEAR(CURDATE())"),
];

// کارکنان فعال
$employees = db()->select(
    "SELECT e.*, c.name as full_name, c.national_id
     FROM hr_employees e
     JOIN contacts c ON c.id = e.contact_id
     WHERE e.status = 'active'
     ORDER BY e.employment_date DESC
     LIMIT 10"
);

// درخواست‌های مرخصی در انتظار
$pendingLeaves = db()->select(
    "SELECT l.*, 
     e.employee_code,
     c.name as employee_name
     FROM hr_leaves l
     JOIN hr_employees e ON e.id = l.employee_id
     JOIN contacts c ON c.id = e.contact_id
     WHERE l.status = 'pending'
     ORDER BY l.created_at DESC"
);

// کارکنان در مرخصی امروز
$onLeaveToday = db()->select(
    "SELECT l.*, 
     e.employee_code,
     c.name as employee_name
     FROM hr_leaves l
     JOIN hr_employees e ON e.id = l.employee_id
     JOIN contacts c ON c.id = e.contact_id
     WHERE l.status = 'approved' 
     AND CURDATE() BETWEEN l.start_date AND l.end_date
     ORDER BY l.start_date"
);

// تولدهای این ماه
$birthdays = db()->select(
    "SELECT e.*, c.name as full_name
     FROM hr_employees e
     JOIN contacts c ON c.id = e.contact_id
     WHERE e.status = 'active'
     ORDER BY e.created_at DESC
     LIMIT 5"
);

// آمار بخش‌ها
$departmentStats = db()->select(
    "SELECT department, COUNT(*) as count
     FROM hr_employees
     WHERE status = 'active' AND department IS NOT NULL
     GROUP BY department
     ORDER BY count DESC"
);

// جمع حقوق ماهانه
$salaryStats = db()->selectOne(
    "SELECT 
        SUM(salary) as total_salary,
        AVG(salary) as avg_salary,
        COUNT(*) as employee_count
     FROM hr_employees
     WHERE status = 'active'"
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منابع انسانی - <?php echo SITE_TITLE; ?></title>
    <style>
        .hr-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .hr-header {
            background: linear-gradient(135deg, #8e44ad 0%, #6c3483 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(142, 68, 173, 0.3);
        }
        
        .hr-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 48px;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: linear-gradient(135deg, #8e44ad 0%, #6c3483 100%);
        }
        
        .stat-details h3 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8e44ad 0%, #6c3483 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #8e44ad 0%, #6c3483 100%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-body.no-padding {
            padding: 0;
        }
        
        /* Employee List */
        .employee-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        
        .employee-item:hover {
            background: #f8f9fa;
        }
        
        .employee-item:last-child {
            border-bottom: none;
        }
        
        .employee-info h4 {
            font-size: 14px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .employee-info p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .employee-code {
            padding: 4px 12px;
            background: #f0f0f0;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            color: #666;
        }
        
        /* Leave Request */
        .leave-item {
            padding: 15px;
            border-right: 4px solid #8e44ad;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 6px;
        }
        
        .leave-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .leave-name {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .leave-type {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 10px;
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .leave-dates {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .leave-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .leave-btn {
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .leave-btn.approve {
            background: #4caf50;
            color: white;
        }
        
        .leave-btn.reject {
            background: #f44336;
            color: white;
        }
        
        .leave-btn:hover {
            transform: scale(1.05);
        }
        
        /* Department Chart */
        .department-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .department-item:last-child {
            border-bottom: none;
        }
        
        .department-name {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .department-bar {
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .department-fill {
            height: 100%;
            background: linear-gradient(90deg, #8e44ad 0%, #6c3483 100%);
            transition: width 0.3s;
        }
        
        /* Salary Info */
        .salary-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .salary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .salary-item:last-child {
            border-bottom: none;
        }
        
        .salary-label {
            opacity: 0.8;
        }
        
        .salary-value {
            font-size: 20px;
            font-weight: bold;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="hr-container">
        <!-- HR Header -->
        <div class="hr-header">
            <h1>👔 منابع انسانی (HR)</h1>
            <p>مدیریت پرسنل، حقوق و دستمزد، مرخصی‌ها و امور رفاهی</p>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($hrStats['total_employees']); ?></h3>
                    <p>کل پرسنل فعال</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($hrStats['pending_leaves']); ?></h3>
                    <p>درخواست‌های مرخصی</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏖️</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($hrStats['on_leave_today']); ?></h3>
                    <p>در مرخصی امروز</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($hrStats['new_hires_month']); ?></h3>
                    <p>استخدام این ماه</p>
                </div>
            </div>
        </div>
        
        <!-- Salary Info -->
        <?php if (check_permission('hr', PERMISSION_FULL)): ?>
        <div class="salary-card">
            <h3 style="margin-bottom: 15px; font-size: 18px;">💰 اطلاعات حقوق و دستمزد</h3>
            <div class="salary-item">
                <span class="salary-label">جمع حقوق ماهانه</span>
                <span class="salary-value">
                    <?php echo en2fa(number_format($salaryStats['total_salary'] ?? 0)); ?> ریال
                </span>
            </div>
            <div class="salary-item">
                <span class="salary-label">میانگین حقوق</span>
                <span class="salary-value">
                    <?php echo en2fa(number_format($salaryStats['avg_salary'] ?? 0)); ?> ریال
                </span>
            </div>
            <div class="salary-item">
                <span class="salary-label">تعداد کارمند</span>
                <span class="salary-value">
                    <?php echo en2fa($salaryStats['employee_count'] ?? 0); ?> نفر
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="employees.php" class="btn btn-primary">👥 لیست پرسنل</a>
            <a href="employee.php?action=add" class="btn btn-success">➕ پرسنل جدید</a>
            <a href="hr_leaves.php" class="btn btn-primary">🏖️ مرخصی‌ها</a>
            <a href="hr_payroll.php" class="btn btn-primary">💰 حقوق و دستمزد</a>
            <a href="hr_reports.php" class="btn btn-primary">📊 گزارشات</a>
        </div>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Pending Leave Requests -->
            <?php if (check_permission('hr', PERMISSION_WRITE) && count($pendingLeaves) > 0): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    ⏳ درخواست‌های مرخصی در انتظار
                    <span><?php echo en2fa(count($pendingLeaves)); ?> مورد</span>
                </div>
                <div class="card-body">
                    <?php foreach ($pendingLeaves as $leave): 
                        $leaveTypes = [
                            'annual' => 'استحقاقی',
                            'sick' => 'استعلاجی',
                            'unpaid' => 'بدون حقوق',
                            'emergency' => 'اضطراری',
                            'other' => 'سایر'
                        ];
                    ?>
                        <div class="leave-item">
                            <div class="leave-header">
                                <span class="leave-name"><?php echo h($leave['employee_name']); ?></span>
                                <span class="leave-type"><?php echo $leaveTypes[$leave['type']]; ?></span>
                            </div>
                            <div class="leave-dates">
                                📅 از <?php echo en2fa(date('Y/m/d', strtotime($leave['start_date']))); ?>
                                تا <?php echo en2fa(date('Y/m/d', strtotime($leave['end_date']))); ?>
                                (<?php echo en2fa($leave['days']); ?> روز)
                            </div>
                            <?php if ($leave['reason']): ?>
                                <p style="font-size: 12px; color: #7f8c8d; margin: 5px 0;">
                                    💬 <?php echo h($leave['reason']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (check_permission('hr', PERMISSION_FULL)): ?>
                                <div class="leave-actions">
                                    <button onclick="approveLeave(<?php echo $leave['id']; ?>)" class="leave-btn approve">
                                        ✓ تایید
                                    </button>
                                    <button onclick="rejectLeave(<?php echo $leave['id']; ?>)" class="leave-btn reject">
                                        ✗ رد
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- On Leave Today -->
            <?php if (count($onLeaveToday) > 0): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    🏖️ پرسنل در مرخصی امروز
                    <span><?php echo en2fa(count($onLeaveToday)); ?> نفر</span>
                </div>
                <div class="card-body">
                    <?php foreach ($onLeaveToday as $leave): ?>
                        <div style="padding: 12px 0; border-bottom: 1px solid #f0f0f0;">
                            <div style="font-weight: bold; color: #2c3e50; margin-bottom: 5px;">
                                <?php echo h($leave['employee_name']); ?>
                            </div>
                            <div style="font-size: 12px; color: #7f8c8d;">
                                از <?php echo en2fa(date('Y/m/d', strtotime($leave['start_date']))); ?>
                                تا <?php echo en2fa(date('Y/m/d', strtotime($leave['end_date']))); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Employees -->
            <div class="dashboard-card">
                <div class="card-header">
                    👥 جدیدترین پرسنل
                    <a href="employees.php" style="color: white; text-decoration: none; font-size: 13px;">
                        مشاهده همه
                    </a>
                </div>
                <div class="card-body no-padding">
                    <?php foreach ($employees as $emp): ?>
                        <div class="employee-item">
                            <div class="employee-info">
                                <h4><?php echo h($emp['full_name']); ?></h4>
                                <p>
                                    <?php echo h($emp['position'] ?: 'سمت تعیین نشده'); ?> - 
                                    <?php echo h($emp['department'] ?: 'بخش تعیین نشده'); ?>
                                </p>
                            </div>
                            <span class="employee-code"><?php echo h($emp['employee_code']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Department Stats -->
            <?php if (count($departmentStats) > 0): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    🏢 آمار بخش‌ها
                </div>
                <div class="card-body">
                    <?php 
                    $maxCount = max(array_column($departmentStats, 'count'));
                    foreach ($departmentStats as $dept): 
                        $percent = ($dept['count'] / $maxCount) * 100;
                    ?>
                        <div class="department-item">
                            <div class="department-name">
                                <span><?php echo h($dept['department']); ?></span>
                                <span style="font-weight: bold; color: #8e44ad;">
                                    <?php echo en2fa($dept['count']); ?> نفر
                                </span>
                            </div>
                            <div class="department-bar">
                                <div class="department-fill" style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function approveLeave(id) {
            if (confirm('آیا از تایید این درخواست اطمینان دارید؟')) {
                window.location.href = 'hr_leave.php?action=approve&id=' + id;
            }
        }
        
        function rejectLeave(id) {
            if (confirm('آیا از رد این درخواست اطمینان دارید؟')) {
                window.location.href = 'hr_leave.php?action=reject&id=' + id;
            }
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>