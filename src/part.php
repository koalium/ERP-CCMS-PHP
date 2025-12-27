<?php
/**
 * فرم قطعه - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$part = null;
$readonly = '';

if ($action === 'view') {
    if (!check_permission('engineering', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
    $readonly = 'readonly';
} elseif ($action === 'delete') {
    if (!check_permission('engineering', PERMISSION_FULL)) {
        die('شما مجوز حذف قطعه را ندارید.');
    }
} else {
    if (!check_permission('engineering', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش قطعه را ندارید.');
    }
}

if ($action === 'delete' && $id > 0) {
    $bomCount = db()->count('bom', 'part_id = :id', [':id' => $id]);
    if ($bomCount > 0) {
        $error = 'این قطعه در BOM استفاده شده و قابل حذف نیست.';
    } else {
        if (db()->delete('parts', 'id = :id', [':id' => $id])) {
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'delete_part',
                'module' => 'engineering',
                'record_id' => $id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            redirect(SITE_URL . '/parts.php?msg=deleted');
        } else {
            $error = 'خطا در حذف قطعه.';
        }
    }
    $action = 'view';
}

if (($action === 'edit' || $action === 'view') && $id > 0) {
    $part = db()->selectOne("SELECT * FROM parts WHERE id = :id", [':id' => $id]);
    if (!$part) {
        die('قطعه یافت نشد.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('توکن امنیتی نامعتبر است.');
    }
    
    $data = [
        'part_number' => sanitize_input($_POST['part_number']),
        'name' => sanitize_input($_POST['name']),
        'description' => sanitize_input($_POST['description']),
        'category' => sanitize_input($_POST['category']),
        'unit' => sanitize_input($_POST['unit']),
        'weight' => !empty($_POST['weight']) ? (float)$_POST['weight'] : null,
        'material' => sanitize_input($_POST['material']),
        'supplier_contact_id' => !empty($_POST['supplier_contact_id']) ? (int)$_POST['supplier_contact_id'] : null,
        'unit_price' => !empty($_POST['unit_price']) ? (float)$_POST['unit_price'] : null,
        'currency' => sanitize_input($_POST['currency']),
        'lead_time_days' => !empty($_POST['lead_time_days']) ? (int)$_POST['lead_time_days'] : null,
        'min_order_qty' => !empty($_POST['min_order_qty']) ? (float)$_POST['min_order_qty'] : null,
        'status' => sanitize_input($_POST['status']),
        'specifications' => sanitize_input($_POST['specifications'])
    ];
    
    if (empty($data['part_number'])) {
        $error = 'شماره قطعه الزامی است.';
    } elseif (empty($data['name'])) {
        $error = 'نام قطعه الزامی است.';
    } else {
        $existing = db()->selectOne(
            "SELECT id FROM parts WHERE part_number = :part_number AND id != :id",
            [':part_number' => $data['part_number'], ':id' => $id]
        );
        
        if ($existing) {
            $error = 'شماره قطعه تکراری است.';
        }
    }
    
    if (empty($error)) {
        if ($action === 'edit' && $id > 0) {
            if (db()->update('parts', $data, 'id = :id', [':id' => $id])) {
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'update_part',
                    'module' => 'engineering',
                    'record_id' => $id,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $success = 'قطعه با موفقیت ویرایش شد.';
                $part = db()->selectOne("SELECT * FROM parts WHERE id = :id", [':id' => $id]);
            } else {
                $error = 'خطا در ویرایش قطعه.';
            }
        } else {
            $newId = db()->insert('parts', $data);
            if ($newId) {
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'create_part',
                    'module' => 'engineering',
                    'record_id' => $newId,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                redirect(SITE_URL . '/part.php?action=edit&id=' . $newId . '&msg=created');
            } else {
                $error = 'خطا در افزودن قطعه.';
            }
        }
    }
}

$suppliers = db()->select("SELECT id, name FROM contacts WHERE is_vendor = 1 ORDER BY name");

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'قطعه با موفقیت ایجاد شد.';
    elseif ($_GET['msg'] === 'deleted') $success = 'قطعه با موفقیت حذف شد.';
}

$pageTitle = ['add' => 'افزودن قطعه جدید', 'edit' => 'ویرایش قطعه', 'view' => 'مشاهده قطعه'][$action] ?? 'قطعه';
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
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
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
        
        .form-group textarea { min-height: 100px; resize: vertical; }
        
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
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔩 <?php echo $pageTitle; ?></h1>
            <a href="parts.php" class="btn btn-secondary">↶ بازگشت به لیست</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>شماره قطعه (P/N) <span class="required">*</span></label>
                        <input type="text" name="part_number" 
                               value="<?php echo h($part['part_number'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نام قطعه <span class="required">*</span></label>
                        <input type="text" name="name" 
                               value="<?php echo h($part['name'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>دسته‌بندی</label>
                        <input type="text" name="category" 
                               value="<?php echo h($part['category'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد اندازه‌گیری</label>
                        <input type="text" name="unit" 
                               value="<?php echo h($part['unit'] ?? 'EA'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>وزن (kg)</label>
                        <input type="number" step="0.001" name="weight" 
                               value="<?php echo h($part['weight'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>جنس</label>
                        <input type="text" name="material" 
                               value="<?php echo h($part['material'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تامین‌کننده</label>
                        <select name="supplier_contact_id" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>"
                                        <?php echo ($part['supplier_contact_id'] ?? 0) == $supplier['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="active" <?php echo ($part['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>فعال</option>
                            <option value="obsolete" <?php echo ($part['status'] ?? '') === 'obsolete' ? 'selected' : ''; ?>>منسوخ</option>
                            <option value="discontinued" <?php echo ($part['status'] ?? '') === 'discontinued' ? 'selected' : ''; ?>>متوقف شده</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>قیمت واحد</label>
                        <input type="number" step="0.01" name="unit_price" 
                               value="<?php echo h($part['unit_price'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد پول</label>
                        <select name="currency" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="IRR" <?php echo ($part['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال</option>
                            <option value="USD" <?php echo ($part['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار</option>
                            <option value="EUR" <?php echo ($part['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>زمان تحویل (روز)</label>
                        <input type="number" name="lead_time_days" 
                               value="<?php echo h($part['lead_time_days'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>حداقل سفارش</label>
                        <input type="number" step="0.01" name="min_order_qty" 
                               value="<?php echo h($part['min_order_qty'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($part['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>مشخصات فنی (JSON)</label>
                        <textarea name="specifications" <?php echo $readonly; ?>><?php echo h($part['specifications'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? '💾 ذخیره تغییرات' : '➕ افزودن قطعه'; ?>
                        </button>
                        
                        <?php if ($action === 'edit' && check_permission('engineering', PERMISSION_FULL)): ?>
                            <a href="part.php?action=delete&id=<?php echo $id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('آیا از حذف این قطعه اطمینان دارید؟')">
                                🗑️ حذف قطعه
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        initJalaliDatePickers();
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>