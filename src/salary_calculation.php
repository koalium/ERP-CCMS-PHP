<?php
/**
 * سیستم محاسبه حقوق و دستمزد
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'محاسبه حقوق';
require_once 'header.php';

check_login();

if (!check_permission('hr', PERMISSION_WRITE)) {
    die('<div class="container"><div class="alert alert-error">شما مجوز دسترسی به این بخش را ندارید.</div></div>');
}

$month = (int)($_GET['month'] ?? date('m'));
$year = (int)($_GET['year'] ?? date('Y'));
$error = '';
$success = '';

// پردازش محاسبه حقوق
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'calculate_all') {
        $calculatedMonth = (int)$_POST['month'];
        $calculatedYear = (int)$_POST['year'];
        
        // دریافت لیست کارکنان فعال
        $employees = db()->select(
            "SELECT e.id, e.employee_code
             FROM hr_employees e
             WHERE e.status = 'active'"
        );
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($employees as $emp) {
            if (calculateSalary($emp['id'], $calculatedYear, $calculatedMonth)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        $success = "محاسبه حقوق انجام شد. موفق: " . en2fa($successCount) . " نفر";
        if ($errorCount > 0) {
            $error = "خطا در محاسبه " . en2fa($errorCount) . " نفر";
        }
        
        // به‌روزرسانی صفحه
        $month = $calculatedMonth;
        $year = $calculatedYear;
    }
}

/**
 * محاسبه حقوق یک کارمند
 */
function calculateSalary($employeeId, $year, $month) {
    // دریافت اطلاعات حقوقی
    $salaryInfo = db()->selectOne(
        "SELECT * FROM hr_salary_info 
         WHERE employee_id = :emp 
         AND effective_date <= :date
         ORDER BY effective_date DESC 
         LIMIT 1",
        [
            ':emp' => $employeeId,
            ':date' => "$year-$month-01"
        ]
    );
    
    if (!$salaryInfo) {
        return false;
    }
    
    // تعداد روزهای کاری ماه (فرض: 26 روز)
    $workingDaysInMonth = 26;
    
    // محاسبه حضور و غیاب
    $attendance = db()->selectOne(
        "SELECT 
            COUNT(CASE WHEN status = 'present' OR status = 'late' THEN 1 END) as working_days,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days,
            SUM(COALESCE(overtime_hours, 0)) as total_overtime
         FROM hr_attendance
         WHERE employee_id = :emp
         AND YEAR(attendance_date) = :year
         AND MONTH(attendance_date) = :month",
        [':emp' => $employeeId, ':year' => $year, ':month' => $month]
    );
    
    $workingDays = (int)($attendance['working_days'] ?? 0);
    $absentDays = (int)($attendance['absent_days'] ?? 0);
    $leaveDays = (int)($attendance['leave_days'] ?? 0);
    $overtimeHours = (float)($attendance['total_overtime'] ?? 0);
    
    // حقوق پایه
    $baseSalary = (float)$salaryInfo['base_salary'];
    
    // کسورات غیبت (حقوق روزانه * تعداد روز غیبت)
    $dailySalary = $baseSalary / $workingDaysInMonth;
    $absentDeduction = $dailySalary * $absentDays;
    
    // محاسبه حقوق پس از کسر غیبت
    $effectiveBaseSalary = $baseSalary - $absentDeduction;
    
    // محاسبه اضافه کاری (1.4 برابر دستمزد ساعتی)
    $hourlyRate = $baseSalary / ($workingDaysInMonth * 8);
    $overtimeAmount = $overtimeHours * $hourlyRate * 1.4;
    
    // مزایا
    $totalAllowances = 
        (float)$salaryInfo['housing_allowance'] +
        (float)$salaryInfo['transportation_allowance'] +
        (float)$salaryInfo['food_allowance'] +
        (float)$salaryInfo['family_allowance'] +
        (float)$salaryInfo['other_allowances'];
    
    // حقوق ناخالص
    $grossSalary = $effectiveBaseSalary + $totalAllowances + $overtimeAmount;
    
    // کسورات
    $insuranceDeduction = (float)$salaryInfo['insurance_deduction'];
    $taxDeduction = (float)$salaryInfo['tax_deduction'];
    
    // کسر وام (اگر وجود دارد)
    $loanDeduction = 0;
    $activeLoan = db()->selectOne(
        "SELECT monthly_amount FROM hr_loans 
         WHERE employee_id = :emp 
         AND status = 'active'
         AND (end_date IS NULL OR end_date >= :date)
         LIMIT 1",
        [':emp' => $employeeId, ':date' => "$year-$month-01"]
    );
    
    if ($activeLoan) {
        $loanDeduction = (float)$activeLoan['monthly_amount'];
    }
    
    $otherDeductions = (float)$salaryInfo['other_deductions'];
    $totalDeductions = $insuranceDeduction + $taxDeduction + $loanDeduction + $otherDeductions;
    
    // حقوق خالص
    $netSalary = $grossSalary - $totalDeductions;
    
    // ذخیره در دیتابیس
    $data = [
        'employee_id' => $employeeId,
        'year' => $year,
        'month' => $month,
        'base_salary' => $effectiveBaseSalary,
        'total_allowances' => $totalAllowances,
        'overtime_amount' => $overtimeAmount,
        'bonus_amount' => 0,
        'gross_salary' => $grossSalary,
        'total_deductions' => $totalDeductions,
        'net_salary' => $netSalary,
        'working_days' => $workingDays,
        'absent_days' => $absentDays,
        'leave_days' => $leaveDays,
        'overtime_hours' => $overtimeHours,
        'status' => 'calculated',
        'created_by' => $_SESSION['user_id']
    ];
    
    // چک کردن وجود رکورد
    $exists = db()->exists(
        'hr_monthly_salaries',
        'employee_id = :emp AND year = :year AND month = :month',
        [':emp' => $employeeId, ':year' => $year, ':month' => $month]
    );
    
    if ($exists) {
        db()->query(
            "UPDATE hr_monthly_salaries SET 
             base_salary = :base_salary,
             total_allowances = :total_allowances,
             overtime_amount = :overtime_amount,
             gross_salary = :gross_salary,
             total_deductions = :total_deductions,
             net_salary = :net_salary,
             working_days = :working_days,
             absent_days = :absent_days,
             leave_days = :leave_days,
             overtime_hours = :overtime_hours,
             status = :status
             WHERE employee_id = :employee_id AND year = :year AND month = :month",
            $data
        );
    } else {
        db()->insert('hr_monthly_salaries', $data);
    }
    
    return true;
}

// دریافت لیست حقوق محاسبه شده
$salaries = db()->select(
    "SELECT s.*, e.employee_code, c.name, e.position
     FROM hr_monthly_salaries s
     INNER JOIN hr_employees e ON e.id = s.employee_id
     INNER JOIN contacts c ON c.id = e.contact_id
     WHERE s.year = :year AND s.month = :month
     ORDER BY c.name",
    [':year' => $year, ':month' => $month]
);

// آمار کلی
$stats = db()->selectOne(
    "SELECT 
        COUNT(*) as total_employees,
        SUM(gross_salary) as total_gross,
        SUM(net_salary) as total_net,
        SUM(total_deductions) as total_deductions,
        SUM(overtime_amount) as total_overtime
     FROM hr_monthly_salaries
     WHERE year = :year AND month = :month",
    [':year' => $year, ':month' => $month]
) ?: ['total_employees' => 0, 'total_gross' => 0, 'total_net' => 0, 'total_deductions' => 0, 'total_overtime' => 0];
?>

<style>
    .salary-container {
        padding: 20px;
    }
    
    .page-header {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 25px;
    }
    
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .header-left h1 {
        color: #2c3e50;
        font-size: 26px;
        margin-bottom: 10px;
    }
    
    .period-selector {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .period-selector select {
        padding: 8px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-success {
        background: #4caf50;
        color: white;
    }
    
    .btn-warning {
        background: #ff9800;
        color: white;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 13px;
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    th {
        padding: 15px 12px;
        text-align: right;
        font-weight: 600;
    }
    
    td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    tbody tr:hover {
        background: #f8f9fa;
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-calculated { background: #e3f2fd; color: #1976d2; }
    .status-approved { background: #e8f5e9; color: #388e3c; }
    .status-paid { background: #f3e5f5; color: #7b1fa2; }
    
    .action-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin: 0 3px;
    }
    
    @media (max-width: 768px) {
        .table-container {
            overflow-x: auto;
        }
        
        table {
            min-width: 1000px;
        }
    }
</style>

<div class="salary-container">
    <?php if ($success): ?>
        <div style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            ✓ <?php echo h($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            ✗ <?php echo h($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- هدر -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-left">
                <h1>💰 محاسبه حقوق و دستمزد</h1>
                <div class="period-selector">
                    <label>دوره:</label>
                    <select id="monthSelect" onchange="changePeriod()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo en2fa($m); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select id="yearSelect" onchange="changePeriod()">
                        <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo en2fa($y); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="calculate_all">
                <input type="hidden" name="month" value="<?php echo $month; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <button type="submit" class="btn btn-primary" 
                        onclick="return confirm('آیا از محاسبه حقوق همه پرسنل اطمینان دارید؟')">
                    🔄 محاسبه حقوق همه
                </button>
            </form>
        </div>
    </div>
    
    <!-- آمار -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo en2fa($stats['total_employees']); ?></div>
            <div class="stat-label">تعداد پرسنل</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo en2fa(number_format($stats['total_gross'])); ?></div>
            <div class="stat-label">جمع حقوق ناخالص (ریال)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo en2fa(number_format($stats['total_net'])); ?></div>
            <div class="stat-label">جمع حقوق خالص (ریال)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo en2fa(number_format($stats['total_deductions'])); ?></div>
            <div class="stat-label">جمع کسورات (ریال)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo en2fa(number_format($stats['total_overtime'])); ?></div>
            <div class="stat-label">جمع اضافه کاری (ریال)</div>
        </div>
    </div>
    
    <!-- جدول حقوق -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ردیف</th>
                    <th>کد پرسنلی</th>
                    <th>نام</th>
                    <th>سمت</th>
                    <th>روز کاری</th>
                    <th>اضافه کاری</th>
                    <th>حقوق ناخالص</th>
                    <th>کسورات</th>
                    <th>حقوق خالص</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($salaries) > 0): ?>
                    <?php 
                    $row = 0;
                    foreach ($salaries as $salary): 
                        $row++;
                    ?>
                        <tr>
                            <td><?php echo en2fa($row); ?></td>
                            <td><?php echo h($salary['employee_code']); ?></td>
                            <td><?php echo h($salary['name']); ?></td>
                            <td><?php echo h($salary['position']); ?></td>
                            <td><?php echo en2fa($salary['working_days']); ?></td>
                            <td><?php echo en2fa(number_format($salary['overtime_hours'], 1)); ?> ساعت</td>
                            <td><?php echo en2fa(number_format($salary['gross_salary'])); ?></td>
                            <td><?php echo en2fa(number_format($salary['total_deductions'])); ?></td>
                            <td><strong><?php echo en2fa(number_format($salary['net_salary'])); ?></strong></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'calculated' => 'محاسبه شده',
                                    'approved' => 'تایید شده',
                                    'paid' => 'پرداخت شده'
                                ];
                                ?>
                                <span class="status-badge status-<?php echo h($salary['status']); ?>">
                                    <?php echo $statusLabels[$salary['status']] ?? $salary['status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="payslip.php?id=<?php echo $salary['id']; ?>" 
                                   class="action-btn btn-primary" target="_blank">
                                    📄 فیش
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 40px; color: #999;">
                            هنوز حقوق این ماه محاسبه نشده است.
                            <br>
                            از دکمه "محاسبه حقوق همه" استفاده کنید.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function changePeriod() {
        const month = document.getElementById('monthSelect').value;
        const year = document.getElementById('yearSelect').value;
        window.location.href = '?month=' + month + '&year=' + year;
    }
</script>

<?php require_once 'footer.php'; ?>