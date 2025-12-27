<?php
/**
 * درخواست متریال از انبار
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

if (!check_permission('warehouse', PERMISSION_WRITE)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$request = null;
$requestItems = [];

// دریافت اطلاعات درخواست
if ($id > 0) {
    $request = db()->selectOne("SELECT * FROM material_requests WHERE id = :id", [':id' => $id]);
    if (!$request) {
        die('درخواست مورد نظر یافت نشد.');
    }
    
    $requestItems = db()->select("
        SELECT mri.*, wi.name as item_name, wi.code as item_code, wi.unit,
               (SELECT COALESCE(SUM(CASE 
                   WHEN type = 'in' AND status = 'completed' THEN quantity
                   WHEN type = 'out' AND status = 'completed' THEN -quantity
                   ELSE 0
               END), 0) FROM warehouse_transactions 
                WHERE item_id = mri.item_id AND warehouse_id = :warehouse_id) as available_stock
        FROM material_request_items mri
        JOIN warehouse_items wi ON wi.id = mri.item_id
        WHERE mri.request_id = :request_id
        ORDER BY mri.id
    ", [':request_id' => $id, ':warehouse_id' => $request['warehouse_id']]);
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $warehouse_id = (int)$_POST['warehouse_id'];
        $project_id = (int)($_POST['project_id'] ?? 0) ?: null;
        $request_date = sanitize_input($_POST['request_date']);
        $need_date = sanitize_input($_POST['need_date']);
        $purpose = sanitize_input($_POST['purpose']);
        $notes = sanitize_input($_POST['notes']);
        $items = $_POST['items'] ?? [];
        
        // اعتبارسنجی
        if (empty($warehouse_id) || empty($request_date) || empty($items)) {
            $error = 'لطفاً تمام فیلدهای الزامی را پر کنید و حداقل یک قلم کالا اضافه کنید.';
        } else {
            // تبدیل تاریخ جلالی به میلادی
            $dateParts = explode('/', $request_date);
            if (count($dateParts) === 3) {
                require_once 'jalali-converter.php';
                list($gy, $gm, $gd) = jalaliToGregorian((int)$dateParts[0], (int)$dateParts[1], (int)$dateParts[2]);
                $request_date_g = "$gy-$gm-$gd";
            } else {
                $request_date_g = date('Y-m-d');
            }
            
            $need_date_g = null;
            if ($need_date) {
                $dateParts = explode('/', $need_date);
                if (count($dateParts) === 3) {
                    list($gy, $gm, $gd) = jalaliToGregorian((int)$dateParts[0], (int)$dateParts[1], (int)$dateParts[2]);
                    $need_date_g = "$gy-$gm-$gd";
                }
            }
            
            db()->beginTransaction();
            
            try {
                $requestData = [
                    'warehouse_id' => $warehouse_id,
                    'project_id' => $project_id,
                    'request_number' => 'MR-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'request_date' => $request_date_g,
                    'need_date' => $need_date_g,
                    'purpose' => $purpose,
                    'notes' => $notes,
                    'status' => 'pending',
                    'requested_by' => $_SESSION['user_id']
                ];
                
                if ($action === 'add') {
                    $requestId = db()->insert('material_requests', $requestData);
                } else {
                    db()->update('material_requests', $requestData, 'id = :id', [':id' => $id]);
                    db()->delete('material_request_items', 'request_id = :id', [':id' => $id]);
                    $requestId = $id;
                }
                
                // اضافه کردن آیتم‌ها
                foreach ($items as $item) {
                    if (!empty($item['item_id']) && !empty($item['quantity'])) {
                        db()->insert('material_request_items', [
                            'request_id' => $requestId,
                            'item_id' => (int)$item['item_id'],
                            'quantity_requested' => (float)$item['quantity'],
                            'notes' => $item['notes'] ?? ''
                        ]);
                    }
                }
                
                db()->commit();
                
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => $action === 'add' ? 'create' : 'update',
                    'module' => 'warehouse_request',
                    'record_id' => $requestId,
                    'new_data' => json_encode($requestData, JSON_UNESCAPED_UNICODE),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                redirect('warehouse_requests.php?success=1');
                
            } catch (Exception $e) {
                db()->rollback();
                $error = 'خطا در ثبت درخواست: ' . $e->getMessage();
            }
        }
    }
}

// دریافت انبارها
$warehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");

// دریافت پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects WHERE status IN ('active', 'planning') ORDER BY title");

// دریافت کالاها
$items = db()->select("SELECT id, code, name, unit FROM warehouse_items WHERE is_active = 1 ORDER BY name");

// تاریخ امروز به شمسی
$today = new DateTime();
require_once 'jalali-converter.php';
list($jy, $jm, $jd) = gregorianToJalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
$today_jalali = "$jy/$jm/$jd";
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درخواست متریال - <?php echo SITE_TITLE; ?></title>
    <script src="jalali-datepicker.js"></script>
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
            max-width: 1400px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        
        .form-group label .required {
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
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: right;
            border: 1px solid #e0e0e0;
        }
        
        .items-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .items-table input,
        .items-table select,
        .items-table textarea {
            width: 100%;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .btn-add-item {
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-remove {
            background: #f44336;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #666;
        }
        
        .stock-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .stock-info.warning {
            color: #ff9800;
        }
        
        .stock-info.danger {
            color: #f44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 درخواست متریال از انبار</h1>
            <a href="warehouse_requests.php" class="btn btn-secondary">بازگشت به لیست</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" id="requestForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>انبار <span class="required">*</span></label>
                        <select name="warehouse_id" id="warehouseSelect" required>
                            <option value="">انتخاب انبار</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse['id']; ?>"
                                        <?php echo isset($request['warehouse_id']) && $request['warehouse_id'] == $warehouse['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($warehouse['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>پروژه (اختیاری)</label>
                        <select name="project_id">
                            <option value="">بدون پروژه</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"
                                        <?php echo isset($request['project_id']) && $request['project_id'] == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code'] . ' - ' . $project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ درخواست <span class="required">*</span></label>
                        <input type="text" name="request_date" class="jalali-date-input" 
                               value="<?php echo h($request['request_date'] ?? $today_jalali); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ نیاز (اختیاری)</label>
                        <input type="text" name="need_date" class="jalali-date-input"
                               value="<?php echo h($request['need_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>منظور درخواست</label>
                        <textarea name="purpose" rows="3"><?php echo h($request['purpose'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>یادداشت</label>
                        <textarea name="notes" rows="2"><?php echo h($request['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="section-title">
                    <span>📦 لیست اقلام درخواستی</span>
                    <button type="button" class="btn-add-item" onclick="addItemRow()">➕ افزودن قلم</button>
                </div>
                
                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width: 40%">کالا</th>
                            <th style="width: 15%">تعداد/مقدار</th>
                            <th style="width: 10%">واحد</th>
                            <th style="width: 25%">یادداشت</th>
                            <th style="width: 10%">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <?php if (!empty($requestItems)): ?>
                            <?php foreach ($requestItems as $item): ?>
                                <tr>
                                    <td>
                                        <select name="items[0][item_id]" class="item-select" required>
                                            <option value="">انتخاب کالا</option>
                                            <?php foreach ($items as $i): ?>
                                                <option value="<?php echo $i['id']; ?>" 
                                                        data-unit="<?php echo h($i['unit']); ?>"
                                                        <?php echo $item['item_id'] == $i['id'] ? 'selected' : ''; ?>>
                                                    <?php echo h($i['code'] . ' - ' . $i['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="stock-info">موجودی: <?php echo en2fa(number_format($item['available_stock'], 2)); ?></div>
                                    </td>
                                    <td>
                                        <input type="number" name="items[0][quantity]" step="0.01" min="0.01" 
                                               value="<?php echo $item['quantity_requested']; ?>" required>
                                    </td>
                                    <td class="unit-display"><?php echo h($item['unit']); ?></td>
                                    <td>
                                        <textarea name="items[0][notes]" rows="2"><?php echo h($item['notes']); ?></textarea>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-remove" onclick="removeItemRow(this)">حذف</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">✓ ثبت درخواست</button>
                    <a href="warehouse_requests.php" class="btn btn-cancel">انصراف</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let itemIndex = 0;
        const itemsData = <?php echo json_encode($items); ?>;
        
        function addItemRow() {
            itemIndex++;
            const tbody = document.getElementById('itemsTableBody');
            const row = document.createElement('tr');
            
            let itemsOptions = '<option value="">انتخاب کالا</option>';
            itemsData.forEach(item => {
                itemsOptions += `<option value="${item.id}" data-unit="${item.unit}">${item.code} - ${item.name}</option>`;
            });
            
            row.innerHTML = `
                <td>
                    <select name="items[${itemIndex}][item_id]" class="item-select" required onchange="updateUnit(this)">
                        ${itemsOptions}
                    </select>
                    <div class="stock-info"></div>
                </td>
                <td>
                    <input type="number" name="items[${itemIndex}][quantity]" step="0.01" min="0.01" required>
                </td>
                <td class="unit-display">-</td>
                <td>
                    <textarea name="items[${itemIndex}][notes]" rows="2"></textarea>
                </td>
                <td>
                    <button type="button" class="btn-remove" onclick="removeItemRow(this)">حذف</button>
                </td>
            `;
            
            tbody.appendChild(row);
        }
        
        function removeItemRow(btn) {
            const row = btn.closest('tr');
            row.remove();
        }
        
        function updateUnit(select) {
            const selectedOption = select.options[select.selectedIndex];
            const unit = selectedOption.dataset.unit || '-';
            const row = select.closest('tr');
            row.querySelector('.unit-display').textContent = unit;
            
            // به‌روزرسانی موجودی
            const warehouseId = document.getElementById('warehouseSelect').value;
            const itemId = select.value;
            
            if (warehouseId && itemId) {
                // در واقعیت باید با AJAX موجودی را دریافت کنیم
                // برای سادگی اینجا فقط placeholder می‌گذاریم
                row.querySelector('.stock-info').textContent = 'موجودی: در حال بررسی...';
            }
        }
        
        // مقداردهی اولیه واحدها
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.item-select').forEach(select => {
                if (select.value) {
                    updateUnit(select);
                }
            });
        });
        
        // اضافه کردن یک ردیف اولیه اگر لیست خالی است
        if (document.getElementById('itemsTableBody').children.length === 0) {
            addItemRow();
        }
    </script>
</body>
</html>

<?php
// ایجاد جدول درخواست متریال اگر وجود ندارد
$createTableSql = "CREATE TABLE IF NOT EXISTS material_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    warehouse_id INT NOT NULL,
    project_id INT,
    request_date DATE NOT NULL,
    need_date DATE,
    purpose TEXT,
    notes TEXT,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    requested_by INT NOT NULL,
    approved_by INT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_warehouse (warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSql);

$createItemsTableSql = "CREATE TABLE IF NOT EXISTS material_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_requested DECIMAL(15, 3) NOT NULL,
    quantity_approved DECIMAL(15, 3),
    notes TEXT,
    FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES warehouse_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createItemsTableSql);
?>