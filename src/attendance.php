<?php
/**
 * سیستم حضور و غیاب
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'حضور و غیاب';
require_once 'header.php';

check_login();

if (!check_permission('hr', PERMISSION_READ)) {
    die('<div class="container"><div class="alert alert-error">شما مجوز دسترسی به این بخش را ندارید.</div></div>');
}

// تاریخ انتخابی (پیش‌فرض: امروز)
$selectedDate = sanitize_input($_GET['date'] ?? date('Y-m-d'));
$month = (int)($_GET['month'] ?? date('m'));
$year = (int)($_GET['year'] ?? date('Y'));

// پردازش ثبت حضور/غیاب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_permission('hr', PERMISSION_WRITE)) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_attendance') {
        $employeeId = (int)$_POST['employee_id'];
        $date = $_POST['date'];
        $checkIn = $_POST['check_in'] ?? null;
        $checkOut = $_POST['check_out'] ?? null;
        $status = $_POST['status'];
        
        // محاسبه ساعات کار
        $workHours = 0;
        $overtimeHours = 0;
        
        if ($checkIn && $checkOut) {
            $start = new DateTime($checkIn);
            $end = new DateTime($checkOut);
            $interval = $start->diff($end);
            $totalHours = $interval->h + ($interval->i / 60);
            
            // ساعات کاری استاندارد: 8 ساعت
            $standardHours = 8;
            $workHours = min($totalHours, $standardHours);
            $overtimeHours = max(0, $totalHours - $standardHours);
        }
        
        $data = [
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'work_hours' => $workHours,
            'overtime_hours' => $overtimeHours,
            'status' => $status,
            'notes' => $_POST['notes'] ?? ''
        ];
        
        // چک کردن وجود رکورد
        $exists = db()->exists(
            'hr_attendance',
            'employee_id = :emp AND attendance_date = :date',
            [':emp' => $employeeId, ':date' => $date]
        );
        
        if ($exists) {
            db()->query(
                "UPDATE hr_attendance SET 
                 check_in = :check_in, check_out = :check_out,
                 work_hours = :work_hours, overtime_hours = :overtime_hours,
                 status = :status, notes = :notes
                 WHERE employee_id = :employee_id AND attendance_date = :attendance_date",
                $data
            );
            $success = 'حضور و غیاب به‌روزرسانی شد';
        } else {
            db()->insert('hr_attendance', $data);
            $success = 'حضور و غیاب ثبت شد';
        }
    }
}

// دریافت لیست کارکنان فعال
$employees = db()->select(
    "SELECT e.id, e.employee_code, c.name, e.position
     FROM hr_employees e
     INNER JOIN contacts c ON c.id = e.contact_id
     WHERE e.status = 'active'
     ORDER BY c.name"
);

// دریافت حضور و غیاب تاریخ انتخابی
$attendanceData = db()->select(
    "SELECT a.*, e.employee_code, c.name
     FROM hr_attendance a
     INNER JOIN hr_employees e ON e.id = a.employee_id
     INNER JOIN contacts c ON c.id = e.contact_id
     WHERE a.attendance_date = :date
     ORDER BY c.name",
    [':date' => $selectedDate]
);

// ایجاد آرایه‌ای از حضور برای دسترسی سریع
$attendanceMap = [];
foreach ($attendanceData as $att) {
    $attendanceMap[$att['employee_id']] = $att;
}

// آمار روز
$stats = [
    'present' => db()->count('hr_attendance', 'attendance_date = :date AND status = "present"', [':date' => $selectedDate]),
    'absent' => db()->count('hr_attendance', 'attendance_date = :date AND status = "absent"', [':date' => $selectedDate]),
    'late' => db()->count('hr_attendance', 'attendance_date = :date AND status = "late"', [':date' => $selectedDate]),
    'leave' => db()->count('hr_attendance', 'attendance_date = :date AND status = "leave"', [':date' => $selectedDate]),
];

// آمار ماهانه
$monthStats = db()->selectOne(
    "SELECT 
        COUNT(DISTINCT employee_id) as total_employees,
        AVG(work_hours) as avg_work_hours,
        SUM(overtime_hours) as total_overtime
     FROM hr_attendance
     WHERE YEAR(attendance_date) = :year AND MONTH(attendance_date) = :month",
    [':year' => $year, ':month' => $month]
);
?>

<style>
    .attendance-container {
        padding: 20px;
    }
    
    .date-selector {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }
    
    .form-group {
        flex: 1;
        min-width: 200px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .stat-label {
        color: #666;
        font-size: 13px;
        margin-top: 5px;
    }
    
    .attendance-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .table-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    thead {
        background: #f8f9fa;
    }
    
    th {
        padding: 12px;
        text-align: right;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 2px solid #e0e0e0;
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
        display: inline-block;
    }
    
    .status-present { background: #e8f5e9; color: #388e3c; }
    .status-absent { background: #ffebee; color: #d32f2f; }
    .status-late { background: #fff3e0; color: #f57c00; }
    .status-leave { background: #e3f2fd; color: #1976d2; }
    .status-half_day { background: #f3e5f5; color: #7b1fa2; }
    
    .time-input {
        padding: 6px 10px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        width: 90px;
        font-size: 13px;
    }
    
    .quick-mark-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .mark-present {
        background: #4caf50;
        color: white;
    }
    
    .mark-absent {
        background: #f44336;
        color: white;
    }
    
    .mark-late {
        background: #ff9800;
        color: white;
    }
    
    @media (max-width: 768px) {
        .date-selector {
            flex-direction: column;
        }
        
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .attendance-table-container {
            overflow-x: auto;
        }
        
        table {
            min-width: 800px;
        }
    }
</style>

<div class="attendance-container">
    <!-- انتخاب تاریخ -->
    <div class="date-selector">
        <div class="form-group">
            <label>📅 تاریخ</label>
            <input type="date" id="selectedDate" value="<?php echo h($selectedDate); ?>"
                   onchange="window.location.href='?date=' + this.value">
        </div>
        
        <div class="form-group">
            <label>📊 ماه</label>
            <select id="selectedMonth" onchange="changeMonth()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                        <?php echo en2fa($m); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>📅 سال</label>
            <select id="selectedYear" onchange="changeMonth()">
                <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                        <?php echo en2fa($y); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <button class="btn btn-primary" onclick="syncDevice()">
            🔄 همگام‌سازی دستگاه
        </button>
        
        <button class="btn btn-primary" onclick="exportReport()">
            📥 خروجی Excel
        </button>
    </div>
    
    <!-- آمار روز -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-value" style="color: #4caf50;"><?php echo en2fa($stats['present']); ?></div>
            <div class="stat-label">حاضر</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color: #f44336;"><?php echo en2fa($stats['absent']); ?></div>
            <div class="stat-label">غایب</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color: #ff9800;"><?php echo en2fa($stats['late']); ?></div>
            <div class="stat-label">تأخیر</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color: #2196f3;"><?php echo en2fa($stats['leave']); ?></div>
            <div class="stat-label">مرخصی</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo en2fa(number_format($monthStats['avg_work_hours'] ?? 0, 1)); ?></div>
            <div class="stat-label">میانگین ساعات کار</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo en2fa(number_format($monthStats['total_overtime'] ?? 0, 1)); ?></div>
            <div class="stat-label">اضافه کاری ماه</div>
        </div>
    </div>
    
    <!-- جدول حضور و غیاب -->
    <div class="attendance-table-container">
        <div class="table-header">
            <h3>📋 حضور و غیاب - <?php echo en2fa(date('Y/m/d', strtotime($selectedDate))); ?></h3>
            <span><?php echo en2fa(count($employees)); ?> نفر</span>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ردیف</th>
                    <th>کد پرسنلی</th>
                    <th>نام و نام خانوادگی</th>
                    <th>سمت</th>
                    <th>ورود</th>
                    <th>خروج</th>
                    <th>ساعات کار</th>
                    <th>اضافه کاری</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row = 0;
                foreach ($employees as $emp): 
                    $row++;
                    $att = $attendanceMap[$emp['id']] ?? null;
                ?>
                    <tr>
                        <td><?php echo en2fa($row); ?></td>
                        <td><?php echo h($emp['employee_code']); ?></td>
                        <td><?php echo h($emp['name']); ?></td>
                        <td><?php echo h($emp['position']); ?></td>
                        <td>
                            <?php if ($att): ?>
                                <?php echo $att['check_in'] ? en2fa(date('H:i', strtotime($att['check_in']))) : '-'; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($att): ?>
                                <?php echo $att['check_out'] ? en2fa(date('H:i', strtotime($att['check_out']))) : '-'; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($att): ?>
                                <?php echo en2fa(number_format($att['work_hours'], 1)); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($att && $att['overtime_hours'] > 0): ?>
                                <?php echo en2fa(number_format($att['overtime_hours'], 1)); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($att): ?>
                                <?php
                                $statusLabels = [
                                    'present' => 'حاضر',
                                    'absent' => 'غایب',
                                    'late' => 'تأخیر',
                                    'half_day' => 'نیمه وقت',
                                    'leave' => 'مرخصی'
                                ];
                                ?>
                                <span class="status-badge status-<?php echo h($att['status']); ?>">
                                    <?php echo $statusLabels[$att['status']] ?? $att['status']; ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-absent">ثبت نشده</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="quick-mark-btn mark-present" 
                                    onclick="markAttendance(<?php echo $emp['id']; ?>, 'present')">
                                ✓ حاضر
                            </button>
                            <button class="quick-mark-btn mark-absent" 
                                    onclick="markAttendance(<?php echo $emp['id']; ?>, 'absent')">
                                ✗ غایب
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function changeMonth() {
        const month = document.getElementById('selectedMonth').value;
        const year = document.getElementById('selectedYear').value;
        window.location.href = '?month=' + month + '&year=' + year;
    }
    
    function markAttendance(employeeId, status) {
        const date = document.getElementById('selectedDate').value;
        const checkIn = status === 'present' ? '08:00' : null;
        const checkOut = status === 'present' ? '16:00' : null;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="mark_attendance">
            <input type="hidden" name="employee_id" value="${employeeId}">
            <input type="hidden" name="date" value="${date}">
            <input type="hidden" name="check_in" value="${checkIn || ''}">
            <input type="hidden" name="check_out" value="${checkOut || ''}">
            <input type="hidden" name="status" value="${status}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
    
    function syncDevice() {
        alert('امکان همگام‌سازی با دستگاه ساعت‌زنی در نسخه‌های آینده فعال خواهد شد');
    }
    
    function exportReport() {
        alert('خروجی Excel در نسخه‌های آینده فعال خواهد شد');
    }
</script>

<?php require_once 'footer.php'; ?>