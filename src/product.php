<?php
/**
 * فرم محصول - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$product = null;
$readonly = '';

// بررسی مجوز
if ($action === 'view') {
    if (!check_permission('engineering', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
    $readonly = 'readonly';
} elseif ($action === 'delete') {
    if (!check_permission('engineering', PERMISSION_FULL)) {
        die('شما مجوز حذف محصول را ندارید.');
    }
} else {
    if (!check_permission('engineering', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش محصول را ندارید.');
    }
}

// حذف محصول
if ($action === 'delete' && $id > 0) {
    // بررسی استفاده در BOM
    $bomCount = db()->count('bom', 'product_id = :id', [':id' => $id]);
    if ($bomCount > 0) {
        $error = 'این محصول در BOM استفاده شده و قابل حذف نیست.';
    } else {
        // حذف محصول
        if (db()->delete('products', 'id = :id', [':id' => $id])) {
            // ثبت لاگ
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'delete_product',
                'module' => 'engineering',
                'record_id' => $id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            redirect(SITE_URL . '/products.php?msg=deleted');
        } else {
            $error = 'خطا در حذف محصول.';
        }
    }
    $action = 'view';
}

// دریافت اطلاعات محصول برای ویرایش/مشاهده
if (($action === 'edit' || $action === 'view') && $id > 0) {
    $product = db()->selectOne("SELECT * FROM products WHERE id = :id", [':id' => $id]);
    if (!$product) {
        die('محصول یافت نشد.');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view' && $action !== 'delete') {
    // بررسی CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('توکن امنیتی نامعتبر است.');
    }
    
    // دریافت داده‌ها
    $data = [
        'code' => sanitize_input($_POST['code']),
        'name' => sanitize_input($_POST['name']),
        'type' => sanitize_input($_POST['type']),
        'description' => sanitize_input($_POST['description']),
        'parent_product_id' => !empty($_POST['parent_product_id']) ? (int)$_POST['parent_product_id'] : null,
        'version' => sanitize_input($_POST['version']),
        'status' => sanitize_input($_POST['status']),
        'specifications' => sanitize_input($_POST['specifications'])
    ];
    
    // اعتبارسنجی
    if (empty($data['code'])) {
        $error = 'کد محصول الزامی است.';
    } elseif (empty($data['name'])) {
        $error = 'نام محصول الزامی است.';
    } else {
        // بررسی تکراری نبودن کد
        $existing = db()->selectOne(
            "SELECT id FROM products WHERE code = :code AND id != :id",
            [':code' => $data['code'], ':id' => $id]
        );
        
        if ($existing) {
            $error = 'کد محصول تکراری است.';
        }
    }
    
    if (empty($error)) {
        if ($action === 'edit' && $id > 0) {
            // ویرایش
            if (db()->update('products', $data, 'id = :id', [':id' => $id])) {
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'update_product',
                    'module' => 'engineering',
                    'record_id' => $id,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $success = 'محصول با موفقیت ویرایش شد.';
                $product = db()->selectOne("SELECT * FROM products WHERE id = :id", [':id' => $id]);
            } else {
                $error = 'خطا در ویرایش محصول.';
            }
        } else {
            // افزودن
            $data['created_by'] = $_SESSION['user_id'];
            
            $newId = db()->insert('products', $data);
            if ($newId) {
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'create_product',
                    'module' => 'engineering',
                    'record_id' => $newId,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                redirect(SITE_URL . '/product.php?action=edit&id=' . $newId . '&msg=created');
            } else {
                $error = 'خطا در افزودن محصول.';
            }
        }
    }
}

// دریافت لیست محصولات برای انتخاب والد
$parentProducts = db()->select(
    "SELECT id, code, name FROM products WHERE id != :id ORDER BY name",
    [':id' => $id]
);

// بررسی پیام موفقیت
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') {
        $success = 'محصول با موفقیت ایجاد شد.';
    } elseif ($_GET['msg'] === 'deleted') {
        $success = 'محصول با موفقیت حذف شد.';
    }
}

$pageTitle = [
    'add' => 'افزودن محصول جدید',
    'edit' => 'ویرایش محصول',
    'view' => 'مشاهده محصول'
][$action] ?? 'محصول';
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input[readonly],
        .form-group select[disabled],
        .form-group textarea[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
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
            <h1>⚙️ <?php echo $pageTitle; ?></h1>
            <a href="products.php" class="btn btn-secondary">↶ بازگشت به لیست</a>
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
                        <label>کد محصول <span class="required">*</span></label>
                        <input type="text" name="code" 
                               value="<?php echo h($product['code'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نسخه</label>
                        <input type="text" name="version" 
                               value="<?php echo h($product['version'] ?? '1.0'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>نام محصول <span class="required">*</span></label>
                        <input type="text" name="name" 
                               value="<?php echo h($product['name'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نوع محصول</label>
                        <input type="text" name="type" 
                               value="<?php echo h($product['type'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>محصول والد</label>
                        <select name="parent_product_id" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">بدون والد (محصول اصلی)</option>
                            <?php foreach ($parentProducts as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>"
                                        <?php echo ($product['parent_product_id'] ?? 0) == $parent['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($parent['code'] . ' - ' . $parent['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت <span class="required">*</span></label>
                        <select name="status" required <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="development" <?php echo ($product['status'] ?? 'development') === 'development' ? 'selected' : ''; ?>>در حال توسعه</option>
                            <option value="active" <?php echo ($product['status'] ?? '') === 'active' ? 'selected' : ''; ?>>فعال</option>
                            <option value="obsolete" <?php echo ($product['status'] ?? '') === 'obsolete' ? 'selected' : ''; ?>>منسوخ</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($product['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>مشخصات فنی (JSON)</label>
                        <textarea name="specifications" <?php echo $readonly; ?>><?php echo h($product['specifications'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? '💾 ذخیره تغییرات' : '➕ افزودن محصول'; ?>
                        </button>
                        
                        <?php if ($action === 'edit' && check_permission('engineering', PERMISSION_FULL)): ?>
                            <a href="product.php?action=delete&id=<?php echo $id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('آیا از حذف این محصول اطمینان دارید؟')">
                                🗑️ حذف محصول
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        // فعال‌سازی datepicker برای تمام فیلدهای تاریخ
        initJalaliDatePickers();
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>