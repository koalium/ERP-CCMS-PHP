<?php
/**
 * فرم درخواست متریال
 * Material Request Form
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$requestId = (int)($_GET['id'] ?? 0);
$workOrderId = (int)($_GET['work_order_id'] ?? 0);
$error = '';
$success = '';
$request = null;
$items = [];

// چک مجوز
$canWrite = check_permission('production', PERMISSION_WRITE);
$canApprove = check_permission('warehouse', PERMISSION_WRITE);

if ($action === 'view' || $action === 'edit') {
    if (!$requestId) {
        redirect(SITE_URL . '/production.php');
    }
    
    $request = db()->selectOne(
        "SELECT mr.*, wo.work_order_number, wo.title as work_order_title,
         p.title as project_title, u.fullname as requested_by_name
         FROM material_requests mr
         LEFT JOIN work_orders wo ON wo.id = mr.work_order_id
         LEFT JOIN projects p ON p.id = mr.project_id
         LEFT JOIN users u ON u.id = mr.requested_by
         WHERE mr.id = :id",
        [':id' => $requestId]
    );
    
    if (!$request) {
        redirect(SITE_URL . '/production.php');
    }
    
    // دریافت آیتم‌های درخواست
    $items = db()->select(
        "SELECT mri.*, wi.name as item_name, wi.code as item_code, wi.unit
         FROM material_request_items mri
         LEFT JOIN warehouse_items wi ON wi.id = mri.item_id
         WHERE mri.request_id = :id",
        [':id' => $requestId]
    );
}

// تایید یا رد درخواست
if (($_SERVER['REQUEST_METHOD'] === 'POST') && $action === 'approve' && $canApprove) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $newStatus = sanitize_input($_POST['status']);
        $notes = sanitize_input($_POST['approval_notes'] ?? '');
        
        db()->update('material_requests', [
            'status' => $newStatus,
            'approved_by' => $_SESSION['user_id'],
            'approved_at' => date('Y-m-d H:i:s'),
            'approval_notes' => $notes
        ], 'id = :id', [':id' => $requestId]);
        
        // ثبت لاگ
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'approve_material_request',
            'module' => 'production',
            'record_id' => $requestId,
            'new_data' => json_encode(['status' => $newStatus]),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        redirect(SITE_URL . '/material_request.php?action=view&id=' . $requestId . '&msg=approved');
    }
}

// ذخیره درخواست
if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($action === 'add' || $action === 'edit') && $canWrite) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $data = [
            'work_order_id' => (int)($_POST['work_order_id'] ?? 0) ?: null,
            'project_id' => (int)($_POST['project_id'] ?? 0) ?: null,
            'title' => sanitize_input($_POST['title']),
            'priority' => sanitize_input($_POST['priority']),
            'required_date' => sanitize_input($_POST['required_date'] ?? ''),
            'purpose' => sanitize_input($_POST['purpose'] ?? ''),
            'notes' => sanitize_input($_POST['notes'] ?? '')
        ];
        
        $requestItems = $_POST['items'] ?? [];
        
        // اعتبارسنجی
        if (empty($data['title']) || empty($requestItems)) {
            $error = 'عنوان و حداقل یک آیتم الزامی است.';
        } else {
            db()->beginTransaction();
            
            try {
                if ($action === 'add') {
                    // تولید شماره درخواست
                    $lastNumber = db()->selectOne(
                        "SELECT request_number FROM material_requests ORDER BY id DESC LIMIT 1"
                    );
                    
                    if ($lastNumber) {
                        $num = (int)substr($lastNumber['request_number'], 3) + 1;
                    } else {
                        $num = 1;
                    }
                    
                    $data['request_number'] = 'MR-' . str_pad($num, 5, '0', STR_PAD_LEFT);
                    $data['requested_by'] = $_SESSION['user_id'];
                    $data['status'] = 'pending';
                    
                    $requestId = db()->insert('material_requests', $data);
                    $logAction = 'add_material_request';
                } else {
                    db()->update('material_requests', $data, 'id = :id', [':id' => $requestId]);
                    $logAction = 'edit_material_request';
                    
                    // حذف آیتم‌های قبلی
                    db()->delete('material_request_items', 'request_id = :id', [':id' => $requestId]);
                }
                
                // ذخیره آیتم‌ها
                foreach ($requestItems as $item) {
                    if (!empty($item['item_id']) && !empty($item['quantity'])) {
                        db()->insert('material_request_items', [
                            'request_id' => $requestId,
                            'item_id' => (int)$item['item_id'],
                            'quantity' => (float)$item['quantity'],
                            'notes' => sanitize_input($item['notes'] ?? '')
                        ]);
                    }
                }
                
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => $logAction,
                    'module' => 'production',
                    'record_id' => $requestId,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                db()->commit();
                
                redirect(SITE_URL . '/material_request.php?action=view&id=' . $requestId . '&msg=saved');
            } catch (Exception $e) {
                db()->rollback();
                $error = 'خطا در ذخیره اطلاعات: ' . $e->getMessage();
            }
        }
    }
}

// دریافت لیست دستورات کار فعال
$workOrders = db()->select(
    "SELECT id, work_order_number, title FROM work_orders 
     WHERE status IN ('pending', 'in_progress') ORDER BY created_at DESC"
);

// دریافت لیست پروژه‌های فعال
$projects = db()->select("SELECT id, code, title FROM projects WHERE status = 'active' ORDER BY title");

// دریافت لیست اقلام انبار
$warehouseItems = db()->select("SELECT id, code, name, unit FROM warehouse_items WHERE is_active = 1 ORDER BY name");

// اگر از دستور کار آمده، اطلاعات آن را بگیریم
$selectedWorkOrder = null;
if ($workOrderId && $action === 'add') {
    $selectedWorkOrder = db()->selectOne(
        "SELECT id, work_order_number, title, project_id FROM work_orders WHERE id = :id",
        [':id' => $workOrderId]
    );
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        echo $action === 'add' ? 'درخواست متریال جدید' : 
             ($action === 'edit' ? 'ویرایش درخواست متریال' : 'مشاهده درخواست متریال');
    ?> - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: #f5f7fa;
            direction: rtl;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .btn-back {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-section h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: right;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .items-table td {
            padding: 10px;
            border: 1px solid #dee2e6;
        }
        
        .items-table input,
        .items-table select {
            width: 100%;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .btn-add-item {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-remove-item {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-completed { background: #cce5ff; color: #004085; }
        
        @media (max-width: 768px) {
            .items-table {
                font-size: 12px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 <?php 
                echo $action === 'add' ? 'درخواست متریال جدید' : 
                     ($action === 'edit' ? 'ویرایش درخواست' : 'مشاهده درخواست');
                
                if ($request) {
                    echo ' - ' . h($request['request_number']);
                }
            ?></h1>
            <a href="production.php" class="btn-back">⬅ بازگشت</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
            <div class="alert alert-success">درخواست با موفقیت ذخیره شد.</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'approved'): ?>
            <div class="alert alert-success">عملیات با موفقیت انجام شد.</div>
        <?php endif; ?>
        
        <div class="form-container">
            <?php if ($action === 'approve' && $canApprove): ?>
                <!-- فرم تایید/رد -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-section">
                        <h2>تصمیم‌گیری درباره درخواست</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>وضعیت جدید</label>
                                <select name="status" required>
                                    <option value="approved">تایید</option>
                                    <option value="rejected">رد</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>یادداشت</label>
                                <textarea name="approval_notes"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">✅ ثبت تصمیم</button>
                        <a href="material_request.php?action=view&id=<?php echo $requestId; ?>" class="btn btn-secondary">انصراف</a>
                    </div>
                </form>
            <?php else: ?>
                <!-- فرم افزودن/ویرایش -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-section">
                        <h2>اطلاعات درخواست</h2>
                        <div class="form-grid">
                            <?php if ($action !== 'add'): ?>
                                <div class="form-group">
                                    <label>شماره درخواست</label>
                                    <input type="text" value="<?php echo h($request['request_number']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>وضعیت</label>
                                    <span class="badge badge-<?php echo $request['status']; ?>">
                                        <?php 
                                        $statuses = ['pending' => 'در انتظار', 'approved' => 'تایید شده', 
                                                    'rejected' => 'رد شده', 'completed' => 'تکمیل شده'];
                                        echo $statuses[$request['status']] ?? $request['status'];
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>عنوان *</label>
                                <input type="text" name="title" 
                                       value="<?php echo h($request['title'] ?? ''); ?>" 
                                       required 
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>دستور کار</label>
                                <select name="work_order_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($workOrders as $wo): ?>
                                        <option value="<?php echo $wo['id']; ?>" 
                                                <?php echo ($request['work_order_id'] ?? $workOrderId) == $wo['id'] ? 'selected' : ''; ?>>
                                            <?php echo h($wo['work_order_number']) . ' - ' . h($wo['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>پروژه</label>
                                <select name="project_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($projects as $proj): ?>
                                        <option value="<?php echo $proj['id']; ?>" 
                                                <?php echo ($request['project_id'] ?? ($selectedWorkOrder['project_id'] ?? 0)) == $proj['id'] ? 'selected' : ''; ?>>
                                            <?php echo h($proj['code']) . ' - ' . h($proj['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>اولویت *</label>
                                <select name="priority" required <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                    <option value="low" <?php echo ($request['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>پایین</option>
                                    <option value="medium" <?php echo ($request['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                                    <option value="high" <?php echo ($request['priority'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>بالا</option>
                                    <option value="urgent" <?php echo ($request['priority'] ?? 'medium') === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>تاریخ مورد نیاز</label>
                                <input type="date" name="required_date" 
                                       value="<?php echo $request['required_date'] ?? ''; ?>"
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>هدف از درخواست</label>
                                <textarea name="purpose" <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($request['purpose'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>اقلام درخواستی</h2>
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">قلم</th>
                                    <th style="width: 15%;">تعداد</th>
                                    <th style="width: 10%;">واحد</th>
                                    <th style="width: 30%;">یادداشت</th>
                                    <?php if ($action !== 'view'): ?>
                                        <th style="width: 5%;"></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php if ($action === 'view' && count($items) > 0): ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo h($item['item_code']) . ' - ' . h($item['item_name']); ?></td>
                                            <td><?php echo en2fa($item['quantity']); ?></td>
                                            <td><?php echo h($item['unit']); ?></td>
                                            <td><?php echo h($item['notes']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php elseif ($action === 'edit' && count($items) > 0): ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr class="item-row">
                                            <td>
                                                <select name="items[0][item_id]" required class="item-select">
                                                    <option value="">انتخاب کنید</option>
                                                    <?php foreach ($warehouseItems as $wi): ?>
                                                        <option value="<?php echo $wi['id']; ?>" 
                                                                data-unit="<?php echo h($wi['unit']); ?>"
                                                                <?php echo $item['item_id'] == $wi['id'] ? 'selected' : ''; ?>>
                                                            <?php echo h($wi['code']) . ' - ' . h($wi['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="number" name="items[0][quantity]" step="0.01" min="0.01" value="<?php echo $item['quantity']; ?>" required></td>
                                            <td><input type="text" class="unit-display" readonly value="<?php echo h($item['unit']); ?>"></td>
                                            <td><input type="text" name="items[0][notes]" value="<?php echo h($item['notes']); ?>"></td>
                                            <td><button type="button" class="btn-remove-item" onclick="removeItem(this)">❌</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="item-row">
                                        <td>
                                            <select name="items[0][item_id]" required class="item-select">
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($warehouseItems as $item): ?>
                                                    <option value="<?php echo $item['id']; ?>" data-unit="<?php echo h($item['unit']); ?>">
                                                        <?php echo h($item['code']) . ' - ' . h($item['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="items[0][quantity]" step="0.01" min="0.01" required></td>
                                        <td><input type="text" class="unit-display" readonly></td>
                                        <td><input type="text" name="items[0][notes]"></td>
                                        <td><button type="button" class="btn-remove-item" onclick="removeItem(this)">❌</button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <?php if ($action !== 'view'): ?>
                            <button type="button" class="btn-add-item" onclick="addItem()">➕ افزودن قلم</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-section">
                        <h2>یادداشت‌ها</h2>
                        <textarea name="notes" style="width: 100%; padding: 12px;" 
                                  <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($request['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <?php if ($action === 'view'): ?>
                            <?php if ($canWrite && $request['status'] === 'pending'): ?>
                                <a href="material_request.php?action=edit&id=<?php echo $requestId; ?>" class="btn btn-primary">✏️ ویرایش</a>
                            <?php endif; ?>
                            <?php if ($canApprove && $request['status'] === 'pending'): ?>
                                <a href="material_request.php?action=approve&id=<?php echo $requestId; ?>" class="btn btn-success">✅ بررسی و تایید</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">💾 ذخیره</button>
                        <?php endif; ?>
                        <a href="production.php" class="btn btn-secondary">بازگشت</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let itemIndex = 1;
        
        function addItem() {
            const tbody = document.getElementById('itemsBody');
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.innerHTML = `
                <td>
                    <select name="items[${itemIndex}][item_id]" required class="item-select" onchange="updateUnit(this)">
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($warehouseItems as $item): ?>
                            <option value="<?php echo $item['id']; ?>" data-unit="<?php echo h($item['unit']); ?>">
                                <?php echo h($item['code']) . ' - ' . h($item['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="items[${itemIndex}][quantity]" step="0.01" min="0.01" required></td>
                <td><input type="text" class="unit-display" readonly></td>
                <td><input type="text" name="items[${itemIndex}][notes]"></td>
                <td><button type="button" class="btn-remove-item" onclick="removeItem(this)">❌</button></td>
            `;
            tbody.appendChild(row);
            itemIndex++;
        }
        
        function removeItem(btn) {
            if (document.querySelectorAll('.item-row').length > 1) {
                btn.closest('tr').remove();
            } else {
                alert('حداقل یک قلم باید وجود داشته باشد.');
            }
        }
        
        function updateUnit(select) {
            const selectedOption = select.options[select.selectedIndex];
            const unit = selectedOption.dataset.unit || '';
            const row = select.closest('tr');
            row.querySelector('.unit-display').value = unit;
        }
        
        // به‌روزرسانی واحدها برای آیتم‌های موجود
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.item-select').forEach(function(select) {
                updateUnit(select);
            });
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>