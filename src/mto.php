<?php
/**
 * فرم MTO - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$mto = null;
$mtoItems = [];
$readonly = '';

if ($action === 'view') {
    if (!check_permission('engineering', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
    $readonly = 'readonly';
} elseif ($action === 'delete') {
    if (!check_permission('engineering', PERMISSION_FULL)) {
        die('شما مجوز حذف MTO را ندارید.');
    }
} else {
    if (!check_permission('engineering', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش MTO را ندارید.');
    }
}

if ($action === 'delete' && $id > 0) {
    db()->beginTransaction();
    try {
        db()->delete('mto_items', 'mto_id = :id', [':id' => $id]);
        db()->delete('mto', 'id = :id', [':id' => $id]);
        
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'delete_mto',
            'module' => 'engineering',
            'record_id' => $id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        db()->commit();
        redirect(SITE_URL . '/mtos.php?msg=deleted');
    } catch (Exception $e) {
        db()->rollback();
        $error = 'خطا در حذف MTO.';
    }
    $action = 'view';
}

if (($action === 'edit' || $action === 'view') && $id > 0) {
    $mto = db()->selectOne("SELECT * FROM mto WHERE id = :id", [':id' => $id]);
    if (!$mto) {
        die('MTO یافت نشد.');
    }
    
    $mtoItems = db()->select("SELECT * FROM mto_items WHERE mto_id = :id ORDER BY item_number", [':id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('توکن امنیتی نامعتبر است.');
    }
    
    // تبدیل تاریخ جلالی به میلادی
    $issue_date = null;
    if (!empty($_POST['issue_date'])) {
        $issue_date = $_POST['issue_date']; // باید تبدیل شود
    }
    
    $revision_date = null;
    if (!empty($_POST['revision_date'])) {
        $revision_date = $_POST['revision_date']; // باید تبدیل شود
    }
    
    $data = [
        'mto_number' => sanitize_input($_POST['mto_number']),
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'product_id' => !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null,
        'title' => sanitize_input($_POST['title']),
        'description' => sanitize_input($_POST['description']),
        'version' => sanitize_input($_POST['version']),
        'status' => sanitize_input($_POST['status']),
        'issue_date' => $issue_date,
        'revision_date' => $revision_date,
        'notes' => sanitize_input($_POST['notes'])
    ];
    
    if (empty($data['mto_number'])) {
        $error = 'شماره MTO الزامی است.';
    } elseif (empty($data['title'])) {
        $error = 'عنوان الزامی است.';
    } else {
        $existing = db()->selectOne(
            "SELECT id FROM mto WHERE mto_number = :mto_number AND id != :id",
            [':mto_number' => $data['mto_number'], ':id' => $id]
        );
        
        if ($existing) {
            $error = 'شماره MTO تکراری است.';
        }
    }
    
    if (empty($error)) {
        db()->beginTransaction();
        try {
            if ($action === 'edit' && $id > 0) {
                db()->update('mto', $data, 'id = :id', [':id' => $id]);
                
                // حذف آیتم‌های قبلی
                db()->delete('mto_items', 'mto_id = :id', [':id' => $id]);
            } else {
                $data['prepared_by'] = $_SESSION['user_id'];
                $id = db()->insert('mto', $data);
            }
            
            // افزودن آیتم‌ها
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $index => $item) {
                    if (!empty($item['description']) && !empty($item['quantity'])) {
                        $itemData = [
                            'mto_id' => $id,
                            'item_number' => $index + 1,
                            'description' => sanitize_input($item['description']),
                            'specification' => sanitize_input($item['specification']),
                            'quantity' => (float)$item['quantity'],
                            'unit' => sanitize_input($item['unit']),
                            'material' => sanitize_input($item['material']),
                            'notes' => sanitize_input($item['notes'])
                        ];
                        
                        db()->insert('mto_items', $itemData);
                    }
                }
            }
            
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => $action === 'edit' ? 'update_mto' : 'create_mto',
                'module' => 'engineering',
                'record_id' => $id,
                'new_data' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            db()->commit();
            
            if ($action === 'edit') {
                $success = 'MTO با موفقیت ویرایش شد.';
                $mto = db()->selectOne("SELECT * FROM mto WHERE id = :id", [':id' => $id]);
                $mtoItems = db()->select("SELECT * FROM mto_items WHERE mto_id = :id ORDER BY item_number", [':id' => $id]);
            } else {
                redirect(SITE_URL . '/mto.php?action=edit&id=' . $id . '&msg=created');
            }
        } catch (Exception $e) {
            db()->rollback();
            $error = 'خطا در ذخیره MTO: ' . $e->getMessage();
        }
    }
}

$projects = db()->select("SELECT id, code, title FROM projects ORDER BY code");
$products = db()->select("SELECT id, code, name FROM products ORDER BY code");

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'MTO با موفقیت ایجاد شد.';
    elseif ($_GET['msg'] === 'deleted') $success = 'MTO با موفقیت حذف شد.';
}

$pageTitle = ['add' => 'افزودن MTO جدید', 'edit' => 'ویرایش MTO', 'view' => 'مشاهده MTO'][$action] ?? 'MTO';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_TITLE; ?></title>
    <script src="jalali-datepicker.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, 'Iranian Sans', Arial, sans-serif; background: #f5f7fa; direction: rtl; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
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
        
        .header h1 { color: #2c3e50; font-size: 24px; }
        
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
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group { display: flex; flex-direction: column; }
        
        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group label .required { color: #f44336; }
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input[readonly], .form-group select[disabled], .form-group textarea[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-group textarea { min-height: 80px; resize: vertical; }
        
        .items-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .items-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 40px 2fr 2fr 1fr 1fr 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: start;
        }
        
        .item-row input, .item-row textarea {
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .item-row textarea { min-height: 40px; resize: vertical; }
        
        .btn-add-item {
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-remove-item {
            background: #f44336;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            padding: 8px;
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
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger { background: #f44336; color: white; }
        .btn-danger:hover { background: #d32f2f; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .item-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📐 <?php echo $pageTitle; ?></h1>
            <a href="mtos.php" class="btn btn-secondary">↶ بازگشت به لیست</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-container">
                <div class="form-row">
                    <div class="form-group">
                        <label>شماره MTO <span class="required">*</span></label>
                        <input type="text" name="mto_number" 
                               value="<?php echo h($mto['mto_number'] ?? 'MTO-' . date('Ymd') . '-'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نسخه</label>
                        <input type="text" name="version" 
                               value="<?php echo h($mto['version'] ?? '1.0'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="draft" <?php echo ($mto['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="in_progress" <?php echo ($mto['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                            <option value="review" <?php echo ($mto['status'] ?? '') === 'review' ? 'selected' : ''; ?>>بررسی</option>
                            <option value="approved" <?php echo ($mto['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                            <option value="cancelled" <?php echo ($mto['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>عنوان <span class="required">*</span></label>
                        <input type="text" name="title" 
                               value="<?php echo h($mto['title'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>پروژه</label>
                        <select name="project_id" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"
                                        <?php echo ($mto['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code'] . ' - ' . $project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>محصول</label>
                        <select name="product_id" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"
                                        <?php echo ($mto['product_id'] ?? 0) == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($product['code'] . ' - ' . $product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تاریخ صدور</label>
                        <input type="text" name="issue_date" class="jalali-date"
                               value="<?php echo h($mto['issue_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ بازبینی</label>
                        <input type="text" name="revision_date" class="jalali-date"
                               value="<?php echo h($mto['revision_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($mto['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>یادداشت</label>
                        <textarea name="notes" <?php echo $readonly; ?>><?php echo h($mto['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <?php if ($action !== 'view'): ?>
                <div class="items-section">
                    <h3>آیتم‌های MTO</h3>
                    
                    <div class="item-row" style="font-weight: bold; margin-bottom: 15px;">
                        <div>#</div>
                        <div>شرح</div>
                        <div>مشخصات</div>
                        <div>تعداد</div>
                        <div>واحد</div>
                        <div>جنس</div>
                        <div></div>
                    </div>
                    
                    <div id="items-container">
                        <?php if (count($mtoItems) > 0): ?>
                            <?php foreach ($mtoItems as $index => $item): ?>
                                <div class="item-row">
                                    <div><?php echo en2fa($index + 1); ?></div>
                                    <textarea name="items[<?php echo $index; ?>][description]" required><?php echo h($item['description']); ?></textarea>
                                    <textarea name="items[<?php echo $index; ?>][specification]"><?php echo h($item['specification']); ?></textarea>
                                    <input type="number" step="0.001" name="items[<?php echo $index; ?>][quantity]" value="<?php echo h($item['quantity']); ?>" required>
                                    <input type="text" name="items[<?php echo $index; ?>][unit]" value="<?php echo h($item['unit']); ?>">
                                    <input type="text" name="items[<?php echo $index; ?>][material]" value="<?php echo h($item['material']); ?>">
                                    <button type="button" class="btn-remove-item" onclick="removeItem(this)">✖</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="item-row">
                                <div>۱</div>
                                <textarea name="items[0][description]" required></textarea>
                                <textarea name="items[0][specification]"></textarea>
                                <input type="number" step="0.001" name="items[0][quantity]" required>
                                <input type="text" name="items[0][unit]" value="EA">
                                <input type="text" name="items[0][material]">
                                <button type="button" class="btn-remove-item" onclick="removeItem(this)">✖</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-add-item" onclick="addItem()">➕ افزودن آیتم</button>
                </div>
            <?php else: ?>
                <div class="items-section">
                    <h3>آیتم‌های MTO</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: #f5f5f5;">
                            <tr>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">#</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">شرح</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">مشخصات</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">تعداد</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">واحد</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">جنس</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mtoItems as $item): ?>
                                <tr>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo en2fa($item['item_number']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo h($item['description']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo h($item['specification']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo en2fa($item['quantity']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo h($item['unit']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo h($item['material']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if ($action !== 'view'): ?>
                <div class="form-container">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? '💾 ذخیره تغییرات' : '➕ افزودن MTO'; ?>
                        </button>
                        
                        <?php if ($action === 'edit' && check_permission('engineering', PERMISSION_FULL)): ?>
                            <a href="mto.php?action=delete&id=<?php echo $id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('آیا از حذف این MTO اطمینان دارید؟')">
                                🗑️ حذف
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <script>
        initJalaliDatePickers();
        
        let itemCounter = <?php echo count($mtoItems); ?>;
        
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <div>${toPersianNumber(itemCounter + 1)}</div>
                <textarea name="items[${itemCounter}][description]" required></textarea>
                <textarea name="items[${itemCounter}][specification]"></textarea>
                <input type="number" step="0.001" name="items[${itemCounter}][quantity]" required>
                <input type="text" name="items[${itemCounter}][unit]" value="EA">
                <input type="text" name="items[${itemCounter}][material]">
                <button type="button" class="btn-remove-item" onclick="removeItem(this)">✖</button>
            `;
            container.appendChild(newRow);
            itemCounter++;
            updateItemNumbers();
        }
        
        function removeItem(btn) {
            const rows = document.querySelectorAll('#items-container .item-row');
            if (rows.length > 1) {
                btn.closest('.item-row').remove();
                updateItemNumbers();
            } else {
                alert('حداقل یک آیتم باید وجود داشته باشد.');
            }
        }
        
        function updateItemNumbers() {
            const rows = document.querySelectorAll('#items-container .item-row');
            rows.forEach((row, index) => {
                row.querySelector('div').textContent = toPersianNumber(index + 1);
            });
        }
        
        function toPersianNumber(num) {
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return num.toString().replace(/\d/g, d => persianDigits[d]);
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>