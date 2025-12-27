<?php
/**
 * فرم BOM - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);
$error = '';
$success = '';
$bom = null;
$readonly = '';

if ($action === 'view') {
    if (!check_permission('engineering', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
    $readonly = 'readonly';
} elseif ($action === 'delete') {
    if (!check_permission('engineering', PERMISSION_FULL)) {
        die('شما مجوز حذف BOM را ندارید.');
    }
} else {
    if (!check_permission('engineering', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش BOM را ندارید.');
    }
}

if ($action === 'delete' && $id > 0) {
    if (db()->delete('bom', 'id = :id', [':id' => $id])) {
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'delete_bom',
            'module' => 'engineering',
            'record_id' => $id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        redirect(SITE_URL . '/boms.php?msg=deleted');
    } else {
        $error = 'خطا در حذف BOM.';
    }
    $action = 'view';
}

if (($action === 'edit' || $action === 'view') && $id > 0) {
    $bom = db()->selectOne("SELECT * FROM bom WHERE id = :id", [':id' => $id]);
    if (!$bom) {
        die('BOM یافت نشد.');
    }
    $product_id = $bom['product_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('توکن امنیتی نامعتبر است.');
    }
    
    $data = [
        'product_id' => (int)$_POST['product_id'],
        'part_id' => (int)$_POST['part_id'],
        'quantity' => (float)$_POST['quantity'],
        'unit' => sanitize_input($_POST['unit']),
        'reference_designator' => sanitize_input($_POST['reference_designator']),
        'notes' => sanitize_input($_POST['notes']),
        'version' => sanitize_input($_POST['version']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    if (empty($data['product_id']) || $data['product_id'] <= 0) {
        $error = 'محصول الزامی است.';
    } elseif (empty($data['part_id']) || $data['part_id'] <= 0) {
        $error = 'قطعه الزامی است.';
    } elseif (empty($data['quantity']) || $data['quantity'] <= 0) {
        $error = 'تعداد باید بزرگتر از صفر باشد.';
    } else {
        // بررسی تکراری نبودن
        $existing = db()->selectOne(
            "SELECT id FROM bom WHERE product_id = :product_id AND part_id = :part_id AND id != :id",
            [':product_id' => $data['product_id'], ':part_id' => $data['part_id'], ':id' => $id]
        );
        
        if ($existing) {
            $error = 'این قطعه قبلاً به BOM این محصول اضافه شده است.';
        }
    }
    
    if (empty($error)) {
        if ($action === 'edit' && $id > 0) {
            if (db()->update('bom', $data, 'id = :id', [':id' => $id])) {
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'update_bom',
                    'module' => 'engineering',
                    'record_id' => $id,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $success = 'BOM با موفقیت ویرایش شد.';
                $bom = db()->selectOne("SELECT * FROM bom WHERE id = :id", [':id' => $id]);
            } else {
                $error = 'خطا در ویرایش BOM.';
            }
        } else {
            $newId = db()->insert('bom', $data);
            if ($newId) {
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'create_bom',
                    'module' => 'engineering',
                    'record_id' => $newId,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                redirect(SITE_URL . '/bom.php?action=edit&id=' . $newId . '&msg=created');
            } else {
                $error = 'خطا در افزودن BOM.';
            }
        }
    }
}

$products = db()->select("SELECT id, code, name FROM products ORDER BY code");
$parts = db()->select("SELECT id, part_number, name, unit FROM parts WHERE status = 'active' ORDER BY part_number");

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'BOM با موفقیت ایجاد شد.';
    elseif ($_GET['msg'] === 'deleted') $success = 'BOM با موفقیت حذف شد.';
}

$pageTitle = ['add' => 'افزودن BOM جدید', 'edit' => 'ویرایش BOM', 'view' => 'مشاهده BOM'][$action] ?? 'BOM';
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
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        
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
        
        .form-group textarea { min-height: 80px; resize: vertical; }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
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
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 <?php echo $pageTitle; ?></h1>
            <a href="boms.php<?php echo $product_id > 0 ? '?product_id=' . $product_id : ''; ?>" class="btn btn-secondary">↶ بازگشت به لیست</a>
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
                        <label>محصول <span class="required">*</span></label>
                        <select name="product_id" required <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"
                                        <?php echo ($bom['product_id'] ?? $product_id) == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($product['code'] . ' - ' . $product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>قطعه <span class="required">*</span></label>
                        <select name="part_id" required <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($parts as $part): ?>
                                <option value="<?php echo $part['id']; ?>"
                                        data-unit="<?php echo h($part['unit']); ?>"
                                        <?php echo ($bom['part_id'] ?? 0) == $part['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($part['part_number'] . ' - ' . $part['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تعداد <span class="required">*</span></label>
                        <input type="number" step="0.001" name="quantity" 
                               value="<?php echo h($bom['quantity'] ?? '1'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد</label>
                        <input type="text" name="unit" id="unit"
                               value="<?php echo h($bom['unit'] ?? 'EA'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Reference Designator</label>
                        <input type="text" name="reference_designator" 
                               value="<?php echo h($bom['reference_designator'] ?? ''); ?>" 
                               placeholder="R1, C2, U3, ..."
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نسخه</label>
                        <input type="text" name="version" 
                               value="<?php echo h($bom['version'] ?? '1.0'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>یادداشت</label>
                        <textarea name="notes" <?php echo $readonly; ?>><?php echo h($bom['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" 
                               <?php echo ($bom['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="is_active" style="margin: 0; font-weight: normal;">فعال</label>
                    </div>
                <?php endif; ?>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? '💾 ذخیره تغییرات' : '➕ افزودن BOM'; ?>
                        </button>
                        
                        <?php if ($action === 'edit' && check_permission('engineering', PERMISSION_FULL)): ?>
                            <a href="bom.php?action=delete&id=<?php echo $id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('آیا از حذف این آیتم BOM اطمینان دارید؟')">
                                🗑️ حذف
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        initJalaliDatePickers();
        
        // تنظیم خودکار واحد بعد از انتخاب قطعه
        document.querySelector('select[name="part_id"]').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const unit = selectedOption.getAttribute('data-unit');
            if (unit) {
                document.getElementById('unit').value = unit;
            }
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>