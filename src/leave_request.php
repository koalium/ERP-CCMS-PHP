<?php
/**
 * درخواست و مدیریت مرخصی
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'درخواست مرخصی';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'list';
$leaveId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// دریافت انواع مرخصی
$leaveTypes = db()->select(
    "SELECT * FROM hr_leave_types WHERE is_active = 1 ORDER BY name"
);

// دریافت اطلاعات کارمند جاری
$currentEmployee = db()->selectOne(
    "SELECT e.* FROM hr_employees e
     LEFT JOIN users u ON u.id = e.user_id
     WHERE u.id = :user_id AND e.status = 'active'",
    [':user_id' => $_SESSION['user_id']]
);

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'submit_leave') {
        if (!$currentEmployee) {
            $error = 'شما به عنوان کارمند ثبت نشده‌اید';
        } else {
            $leaveTypeId = (int)$_POST['leave_type_id'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $reason = sanitize_input($_POST['reason'] ?? '');
            
            // محاسبه تعداد روز
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $days = $end->diff($start)->days + 1;
            
            // چک کردن موجودی مرخصی
            $leaveType = db()->selectOne(
                "SELECT * FROM hr_leave_types WHERE id = :id",
                [':id' => $leaveTypeId]
            );
            
            if ($leaveType) {
                $currentYear = date('Y');
                
                // دریافت یا ایجاد موجودی مرخصی
                $balance = db()->selectOne(
                    "SELECT * FROM hr_leave_balance 
                     WHERE employee_id = :emp AND leave_type_id = :type AND year = :year",
                    [':emp' => $currentEmployee['id'], ':type' => $leaveTypeId, ':year' => $currentYear]
                );
                
                if (!$balance) {
                    // ایجاد موجودی جدید
                    db()->insert('hr_leave_balance', [
                        'employee_id' => $currentEmployee['id'],
                        'leave_type_id' => $leaveTypeId,
                        'year' => $currentYear,
                        'total_days' => $leaveType['days_per_year'],
                        'used_days' => 0,
                        'remaining_days' => $leaveType['days_per_year']
                    ]);
                    
                    $balance = db()->selectOne(
                        "SELECT * FROM hr_leave_balance 
                         WHERE employee_id = :emp AND leave_type_id = :type AND year = :year",
                        [':emp' => $currentEmployee['id'], ':type' => $leaveTypeId, ':year' => $currentYear]
                    );
                }
                
                if ($days > $balance['remaining_days']) {
                    $error = 'موجودی مرخصی کافی نیست. موجودی: ' . en2fa($balance['remaining_days']) . ' روز';
                } else {
                    // ثبت درخواست
                    $leaveId = db()->insert('hr_leaves', [
                        'employee_id' => $currentEmployee['id'],
                        'type' => $leaveType['type_code'],
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'days' => $days,
                        'reason' => $reason,
                        'status' => 'pending'
                    ]);
                    
                    if ($leaveId) {
                        $success = 'درخواست مرخصی با موفقیت ثبت شد و در انتظار تایید است';
                        $action = 'list';
                    } else {
                        $error = 'خطا در ثبت درخواست';
                    }
                }
            }
        }
    } elseif ($postAction === 'approve' || $postAction === 'reject') {
        if (check_permission('hr', PERMISSION_WRITE)) {
            $leaveId = (int)$_POST['leave_id'];
            $status = $postAction === 'approve' ? 'approved' : 'rejected';
            $notes = sanitize_input($_POST['notes'] ?? '');
            
            $leave = db()->selectOne("SELECT * FROM hr_leaves WHERE id = :id", [':id' => $leaveId]);
            
            if ($leave) {
                db()->update('hr_leaves', [
                    'status' => $status,
                    'approved_by' => $_SESSION['user_id'],
                    'approved_at' => date('Y-m-d H:i:s'),
                    'notes' => $notes
                ], 'id = :id', [':id' => $leaveId]);
                
                // اگر تایید شد، موجودی را کم کن
                if ($status === 'approved') {
                    $balance = db()->selectOne(
                        "SELECT * FROM hr_leave_balance 
                         WHERE employee_id = :emp AND year = :year",
                        [':emp' => $leave['employee_id'], ':year' => date('Y', strtotime($leave['start_date']))]
                    );
                    
                    if ($balance) {
                        $newUsed = $balance['used_days'] + $leave['days'];
                        $newRemaining = $balance['remaining_days'] - $leave['days'];
                        
                        db()->update('hr_leave_balance', [
                            'used_days' => $newUsed,
                            'remaining_days' => $newRemaining
                        ], 'id = :id', [':id' => $balance['id']]);
                    }
                }
                
                $success = $status === 'approved' ? 'درخواست تایید شد' : 'درخواست رد شد';
            }
        }
    }
}

// دریافت لیست مرخصی‌ها
if ($action === 'list') {
    $filterStatus = $_GET['status'] ?? '';
    
    $sql = "SELECT l.*, e.employee_code, c.name, lt.name as leave_type_name,
            u.fullname as approver_name
            FROM hr_leaves l
            INNER JOIN hr_employees e ON e.id = l.employee_id
            INNER JOIN contacts c ON c.id = e.contact_id
            LEFT JOIN hr_leave_types lt ON lt.type_code = l.type
            LEFT JOIN users u ON u.id = l.approved_by";
    
    $params = [];
    
    if (!check_permission('hr', PERMISSION_WRITE)) {
        // کارمندان عادی فقط مرخصی‌های خود را ببینند
        $sql .= " WHERE l.employee_id = :emp";
        $params[':emp'] = $currentEmployee['id'] ?? 0;
    } else {
        $sql .= " WHERE 1=1";
    }
    
    if ($filterStatus) {
        $sql .= " AND l.status = :status";
        $params[':status'] = $filterStatus;
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    $leaves = db()->select($sql, $params);
}

// دریافت آمار
$stats = [
    'pending' => db()->count('hr_leaves', 'status = "pending"'),
    'approved' => db()->count('hr_leaves', 'status = "approved" AND start_date <= CURDATE() AND end_date >= CURDATE()'),
    'total_this_year' => db()->count('hr_leaves', 'YEAR(start_date) = YEAR(CURDATE())')
];

if ($currentEmployee) {
    $myBalance = db()->select(
        "SELECT lb.*, lt.name as leave_type_name
         FROM hr_leave_balance lb
         INNER JOIN hr_leave_types lt ON lt.id = lb.leave_type_id
         WHERE lb.employee_id = :emp AND lb.year = :year",
        [':emp' => $currentEmployee['id'], ':year' => date('Y')]
    );
}
?>

<style>
    .leave-container {
        padding: 20px;
    }
    
    .balance-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .balance-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-right: 4px solid #667eea;
    }
    
    .balance-header {
        font-size: 14px;
        color: #666;
        margin-bottom: 15px;
    }
    
    .balance-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .balance-days {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .balance-label {
        font-size: 12px;
        color: #999;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .leave-form {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .form-group label {
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .leaves-table {
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
    
    .status-pending { background: #fff3e0; color: #f57c00; }
    .status-approved { background: #e8f5e9; color: #388e3c; }
    .status-rejected { background: #ffebee; color: #d32f2f; }
    
    .action-btns {
        display: flex;
        gap: 5px;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 6px;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .leaves-table {
            overflow-x: auto;
        }
        
        table {
            min-width: 800px;
        }
    }
</style>

<div class="leave-container">
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
    
    <!-- موجودی مرخصی -->
    <?php if ($currentEmployee && !empty($myBalance)): ?>
        <div class="balance-cards">
            <?php foreach ($myBalance as $bal): ?>
                <div class="balance-card">
                    <div class="balance-header"><?php echo h($bal['leave_type_name']); ?></div>
                    <div class="balance-info">
                        <div>
                            <div class="balance-days"><?php echo en2fa($bal['remaining_days']); ?></div>
                            <div class="balance-label">روز باقیمانده</div>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-size: 18px; font-weight: bold; color: #999;">
                                <?php echo en2fa($bal['used_days']); ?>/<?php echo en2fa($bal['total_days']); ?>
                            </div>
                            <div class="balance-label">استفاده شده</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'new'): ?>
        <!-- فرم درخواست مرخصی -->
        <div class="leave-form">
            <h2 style="margin-bottom: 20px;">📝 درخواست مرخصی جدید</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="submit_leave">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>نوع مرخصی *</label>
                        <select name="leave_type_id" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($leaveTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo h($type['name']); ?> 
                                    (<?php echo en2fa($type['days_per_year']); ?> روز در سال)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>از تاریخ *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>تا تاریخ *</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>دلیل مرخصی</label>
                        <textarea name="reason" placeholder="توضیحات..."></textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">✓ ثبت درخواست</button>
                    <a href="leave_request.php" class="btn" style="background: #999; color: white;">انصراف</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- لیست مرخصی‌ها -->
        <div class="action-buttons">
            <?php if ($currentEmployee): ?>
                <a href="?action=new" class="btn btn-primary">➕ درخواست مرخصی جدید</a>
            <?php endif; ?>
            <a href="?status=" class="btn" style="background: #f0f0f0; color: #333;">همه</a>
            <a href="?status=pending" class="btn" style="background: #fff3e0; color: #f57c00;">در انتظار (<?php echo en2fa($stats['pending']); ?>)</a>
            <a href="?status=approved" class="btn" style="background: #e8f5e9; color: #388e3c;">تایید شده</a>
        </div>
        
        <div class="leaves-table">
            <table>
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <?php if (check_permission('hr', PERMISSION_WRITE)): ?>
                            <th>کارمند</th>
                        <?php endif; ?>
                        <th>نوع</th>
                        <th>از تاریخ</th>
                        <th>تا تاریخ</th>
                        <th>روز</th>
                        <th>دلیل</th>
                        <th>وضعیت</th>
                        <?php if (check_permission('hr', PERMISSION_WRITE)): ?>
                            <th>عملیات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row = 0;
                    foreach ($leaves as $leave): 
                        $row++;
                    ?>
                        <tr>
                            <td><?php echo en2fa($row); ?></td>
                            <?php if (check_permission('hr', PERMISSION_WRITE)): ?>
                                <td><?php echo h($leave['name']); ?></td>
                            <?php endif; ?>
                            <td><?php echo h($leave['leave_type_name']); ?></td>
                            <td><?php echo en2fa(date('Y/m/d', strtotime($leave['start_date']))); ?></td>
                            <td><?php echo en2fa(date('Y/m/d', strtotime($leave['end_date']))); ?></td>
                            <td><?php echo en2fa($leave['days']); ?></td>
                            <td><?php echo h(mb_substr($leave['reason'], 0, 30)) . (mb_strlen($leave['reason']) > 30 ? '...' : ''); ?></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'pending' => 'در انتظار',
                                    'approved' => 'تایید شده',
                                    'rejected' => 'رد شده'
                                ];
                                ?>
                                <span class="status-badge status-<?php echo h($leave['status']); ?>">
                                    <?php echo $statusLabels[$leave['status']] ?? $leave['status']; ?>
                                </span>
                            </td>
                            <?php if (check_permission('hr', PERMISSION_WRITE) && $leave['status'] === 'pending'): ?>
                                <td>
                                    <div class="action-btns">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #4caf50; color: white;">
                                                ✓ تایید
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #f44336; color: white;">
                                                ✗ رد
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>