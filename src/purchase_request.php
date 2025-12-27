<?php
/**
 * فرم درخواست خرید
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = sanitize_input($_GET['action'] ?? 'add');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// چک مجوز
if ($action === 'view') {
    if (!check_permission('procurement', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
} else {
    if (!check_permission('procurement', PERMISSION_WRITE)) {
        die('شما مجوز ثبت/ویرایش درخواست خرید را ندارید.');
    }
}

// دریافت درخواست برای ویرایش یا مشاهده
$pr = null;
if ($id > 0 && in_array($action, ['edit', 'view', 'approve'])) {
    $pr = db()->selectOne(
        "SELECT pr.*,
                u1.fullname as requester_name,
                u2.fullname as approver_name,
                p.title as project_title,
                p.code as project_code
         FROM purchase_requests pr
         LEFT JOIN users u1 ON u1.id = pr.requested_by
         LEFT JOIN users u2 ON u2.id = pr.approved_by
         LEFT JOIN projects p ON p.id = pr.project_id
         WHERE pr.id = :id",
        [':id' => $id]
    );
    
    if (!$pr) {
        die('درخواست خرید یافت نشد.');
    }
    
    $pr['items'] = json_decode($pr['items'] ?? '[]', true);
}

// تایید درخواست
if ($action === 'approve' && $id > 0) {
    if (check_permission('procurement', PERMISSION_FULL)) {
        if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $updated = db()->update('purchase_requests', [
                'status' => 'approved',
                'approved_by' => $_SESSION['user_id']
            ], 'id = :id', [':id' => $id]);
            
            if ($updated !== false) {
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'approve_purchase_request',
                    'module' => 'procurement',
                    'record_id' => $id,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                redirect(SITE_URL . '/procurement.php?msg=pr_approved');
            }
        }
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['approve'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $requestNumber = sanitize_input($_POST['request_number'] ?? '');
        $title = sanitize_input($_POST['title'] ?? '');
        $type = sanitize_input($_POST['type'] ?? 'material');
        $priority = sanitize_input($_POST['priority'] ?? 'normal');
        $status = sanitize_input($_POST['status'] ?? 'draft');
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] ? (int)$_POST['project_id'] : null;
        $requiredDate = sanitize_input($_POST['required_date'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $notes = sanitize_input($_POST['notes'] ?? '');
        
        // پردازش آیتم‌ها
        $items = [];
        $totalAmount = 0;
        
        if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
            foreach ($_POST['item_name'] as $index => $itemName) {
                if (!empty($itemName)) {
                    $qty = floatval($_POST['item_qty'][$index] ?? 0);
                    $unit = sanitize_input($_POST['item_unit'][$index] ?? '');
                    $price = floatval($_POST['item_price'][$index] ?? 0);
                    $itemTotal = $qty * $price;
                    $totalAmount += $itemTotal;
                    
                    $items[] = [
                        'name' => sanitize_input($itemName),
                        'description' => sanitize_input($_POST['item_desc'][$index] ?? ''),
                        'qty' => $qty,
                        'unit' => $unit,
                        'estimated_price' => $price,
                        'total' => $itemTotal
                    ];
                }
            }
        }
        
        // اعتبارسنجی
        if (empty($requestNumber) || empty($title) || count($items) === 0) {
            $error = 'لطفاً شماره درخواست، عنوان و حداقل یک آیتم را وارد کنید.';
        } else {
            // چک تکراری بودن شماره
            $existsSql = "SELECT COUNT(*) as count FROM purchase_requests WHERE request_number = :number";
            $existsParams = [':number' => $requestNumber];
            
            if ($action === 'edit') {
                $existsSql .= " AND id != :id";
                $existsParams[':id'] = $id;
            }
            
            $exists = db()->selectOne($existsSql, $existsParams);
            
            if ($exists && $exists['count'] > 0) {
                $error = 'شماره درخواست تکراری است.';
            } else {
                $data = [
                    'request_number' => $requestNumber,
                    'title' => $title,
                    'type' => $type,
                    'priority' => $priority,
                    'status' => $status,
                    'project_id' => $projectId,
                    'required_date' => $requiredDate ?: null,
                    'description' => $description,
                    'notes' => $notes,
                    'items' => json_encode($items),
                    'total_amount' => $totalAmount
                ];
                
                if ($action === 'add') {
                    $data['requested_by'] = $_SESSION['user_id'];
                    $newId = db()->insert('purchase_requests', $data);
                    
                    if ($newId) {
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'create_purchase_request',
                            'module' => 'procurement',
                            'record_id' => $newId,
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        redirect(SITE_URL . '/procurement.php?msg=pr_added');
                    } else {
                        $error = 'خطا در ذخیره درخواست خرید.';
                    }
                } elseif ($action === 'edit') {
                    $updated = db()->update('purchase_requests', $data, 'id = :id', [':id' => $id]);
                    
                    if ($updated !== false) {
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'update_purchase_request',
                            'module' => 'procurement',
                            'record_id' => $id,
                            'old_data' => json_encode($pr),
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        $success = 'درخواست خرید با موفقیت به‌روزرسانی شد.';
                        
                        // بارگذاری مجدد
                        $pr = db()->selectOne("SELECT * FROM purchase_requests WHERE id = :id", [':id' => $id]);
                        $pr['items'] = json_decode($pr['items'] ?? '[]', true);
                    } else {
                        $error = 'خطا در به‌روزرسانی درخواست.';
                    }
                }
            }
        }
    }
}

// دریافت پروژه‌ها
$projects = db()->select(
    "SELECT id, code, title FROM projects WHERE status NOT IN ('cancelled', 'completed') ORDER BY title"
);

$readonly = ($action === 'view') ? 'readonly disabled' : '';
$canEdit = !$readonly && ($pr['status'] ?? 'draft') !== 'approved';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'افزودن' : ($action === 'edit' ? 'ویرایش' : 'مشاهده'); ?> درخواست خرید</title>
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
            margin: 20px auto;
            padding: 0 20px;
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
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .required {
            color: #f44336;
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
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: right;
            border: 1px solid #e0e0e0;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .items-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .btn-remove {
            background: #f44336;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-add {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .total-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            text-align: left;
            margin-top: 20px;
        }
        
        .total-box h3 {
            color: #1976d2;
            font-size: 24px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            border-right: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .items-table {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                📋 
                <?php 
                echo $action === 'add' ? 'درخواست خرید جدید' : 
                     ($action === 'edit' ? 'ویرایش درخواست خرید' : 'مشاهده درخواست خرید');
                ?>
            </h1>
            <a href="procurement.php" class="btn btn-back">⬅️ بازگشت</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'view' && $pr): ?>
            <div class="info-box">
                <p><strong>درخواست‌کننده:</strong> <?php echo h($pr['requester_name']); ?></p>
                <p><strong>تاریخ ثبت:</strong> <?php echo en2fa(date('Y/m/d H:i', strtotime($pr['created_at']))); ?></p>
                <?php if ($pr['approver_name']): ?>
                    <p><strong>تایید کننده:</strong> <?php echo h($pr['approver_name']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" id="prForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>شماره درخواست <span class="required">*</span></label>
                        <input type="text" name="request_number" 
                               value="<?php echo h($pr['request_number'] ?? 'PR-' . date('Ymd') . '-'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نوع درخواست</label>
                        <select name="type" <?php echo $readonly; ?>>
                            <option value="material" <?php echo ($pr['type'] ?? 'material') === 'material' ? 'selected' : ''; ?>>مواد و مصالح</option>
                            <option value="service" <?php echo ($pr['type'] ?? '') === 'service' ? 'selected' : ''; ?>>خدمات</option>
                            <option value="equipment" <?php echo ($pr['type'] ?? '') === 'equipment' ? 'selected' : ''; ?>>تجهیزات</option>
                            <option value="other" <?php echo ($pr['type'] ?? '') === 'other' ? 'selected' : ''; ?>>سایر</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>اولویت</label>
                        <select name="priority" <?php echo $readonly; ?>>
                            <option value="low" <?php echo ($pr['priority'] ?? 'normal') === 'low' ? 'selected' : ''; ?>>پایین</option>
                            <option value="normal" <?php echo ($pr['priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>عادی</option>
                            <option value="high" <?php echo ($pr['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>بالا</option>
                            <option value="urgent" <?php echo ($pr['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $readonly; ?>>
                            <option value="draft" <?php echo ($pr['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="pending" <?php echo ($pr['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>در انتظار تایید</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>عنوان درخواست <span class="required">*</span></label>
                        <input type="text" name="title" 
                               value="<?php echo h($pr['title'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>پروژه مرتبط</label>
                        <select name="project_id" <?php echo $readonly; ?>>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" 
                                        <?php echo ($pr['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code']); ?> - <?php echo h($project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ مورد نیاز</label>
                        <input type="date" name="required_date" 
                               value="<?php echo h($pr['required_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($pr['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="section-title">📦 آیتم‌های درخواست</div>
                
                <?php if ($canEdit): ?>
                    <button type="button" class="btn-add" onclick="addItem()">➕ افزودن آیتم</button>
                    
                    <table class="items-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 25%;">نام آیتم</th>
                                <th style="width: 30%;">توضیحات</th>
                                <th style="width: 10%;">تعداد</th>
                                <th style="width: 10%;">واحد</th>
                                <th style="width: 15%;">قیمت تخمینی</th>
                                <th style="width: 10%;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php 
                            $items = $pr['items'] ?? [['name' => '', 'description' => '', 'qty' => 1, 'unit' => 'عدد', 'estimated_price' => 0]];
                            foreach ($items as $item): 
                            ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" value="<?php echo h($item['name']); ?>" required></td>
                                    <td><input type="text" name="item_desc[]" value="<?php echo h($item['description']); ?>"></td>
                                    <td><input type="number" name="item_qty[]" value="<?php echo $item['qty']; ?>" step="0.01" required></td>
                                    <td><input type="text" name="item_unit[]" value="<?php echo h($item['unit']); ?>"></td>
                                    <td><input type="number" name="item_price[]" value="<?php echo $item['estimated_price']; ?>" step="0.01"></td>
                                    <td><button type="button" class="btn-remove" onclick="removeItem(this)">حذف</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>ردیف</th>
                                <th>نام آیتم</th>
                                <th>توضیحات</th>
                                <th>تعداد</th>
                                <th>واحد</th>
                                <th>قیمت تخمینی</th>
                                <th>جمع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pr['items'] as $index => $item): ?>
                                <tr>
                                    <td><?php echo en2fa($index + 1); ?></td>
                                    <td><?php echo h($item['name']); ?></td>
                                    <td><?php echo h($item['description']); ?></td>
                                    <td><?php echo en2fa($item['qty']); ?></td>
                                    <td><?php echo h($item['unit']); ?></td>
                                    <td><?php echo en2fa(number_format($item['estimated_price'], 0)); ?></td>
                                    <td><?php echo en2fa(number_format($item['total'], 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="total-box">
                        <h3>جمع کل: <?php echo en2fa(number_format($pr['total_amount'], 0)); ?> <?php echo h($pr['currency']); ?></h3>
                    </div>
                <?php endif; ?>
                
                <div class="form-group full-width">
                    <label>یادداشت‌ها</label>
                    <textarea name="notes" <?php echo $readonly; ?>><?php echo h($pr['notes'] ?? ''); ?></textarea>
                </div>
                
                <?php if ($canEdit): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            💾 <?php echo $action === 'add' ? 'ثبت درخواست' : 'به‌روزرسانی'; ?>
                        </button>
                        <a href="procurement.php" class="btn btn-back">❌ انصراف</a>
                    </div>
                <?php else: ?>
                    <div class="form-actions">
                        <?php if (check_permission('procurement', PERMISSION_WRITE) && $pr['status'] !== 'approved'): ?>
                            <a href="purchase_request.php?action=edit&id=<?php echo $pr['id']; ?>" 
                               class="btn btn-primary">✏️ ویرایش</a>
                        <?php endif; ?>
                        
                        <?php if (check_permission('procurement', PERMISSION_FULL) && $pr['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-success"
                                        onclick="return confirm('آیا این درخواست را تایید می‌کنید؟')">
                                    ✅ تایید درخواست
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        function addItem() {
            const tbody = document.getElementById('itemsBody');
            const newRow = tbody.insertRow();
            newRow.innerHTML = `
                <td><input type="text" name="item_name[]" required></td>
                <td><input type="text" name="item_desc[]"></td>
                <td><input type="number" name="item_qty[]" value="1" step="0.01" required></td>
                <td><input type="text" name="item_unit[]" value="عدد"></td>
                <td><input type="number" name="item_price[]" value="0" step="0.01"></td>
                <td><button type="button" class="btn-remove" onclick="removeItem(this)">حذف</button></td>
            `;
        }
        
        function removeItem(btn) {
            const row = btn.closest('tr');
            const tbody = document.getElementById('itemsBody');
            
            if (tbody.rows.length > 1) {
                row.remove();
            } else {
                alert('حداقل یک آیتم باید وجود داشته باشد.');
            }
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>