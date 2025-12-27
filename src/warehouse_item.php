<?php
/**
 * فرم تعریف و ویرایش کالا
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$item = null;

// چک مجوز
if ($action === 'view') {
    if (!check_permission('warehouse', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
} else {
    if (!check_permission('warehouse', PERMISSION_WRITE)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
}

// دریافت اطلاعات کالا برای ویرایش یا مشاهده
if (in_array($action, ['edit', 'view']) && $id > 0) {
    $item = db()->selectOne("SELECT * FROM warehouse_items WHERE id = :id", [':id' => $id]);
    if (!$item) {
        die('کالای مورد نظر یافت نشد.');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $code = sanitize_input($_POST['code']);
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        $category = sanitize_input($_POST['category']);
        $subcategory = sanitize_input($_POST['subcategory']);
        $unit = sanitize_input($_POST['unit']);
        $min_stock = (float)($_POST['min_stock'] ?? 0);
        $max_stock = (float)($_POST['max_stock'] ?? 0);
        $unit_price = (float)($_POST['unit_price'] ?? 0);
        $currency = sanitize_input($_POST['currency']);
        $specifications = sanitize_input($_POST['specifications']);
        
        // اعتبارسنجی
        if (empty($code) || empty($name) || empty($unit)) {
            $error = 'لطفاً تمام فیلدهای الزامی را پر کنید.';
        } else {
            // چک تکراری بودن کد
            $existingCode = db()->selectOne(
                "SELECT id FROM warehouse_items WHERE code = :code AND id != :id",
                [':code' => $code, ':id' => $id]
            );
            
            if ($existingCode) {
                $error = 'کد کالا تکراری است. لطفاً کد دیگری انتخاب کنید.';
            } else {
                $data = [
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'unit' => $unit,
                    'min_stock' => $min_stock,
                    'max_stock' => $max_stock,
                    'unit_price' => $unit_price,
                    'currency' => $currency,
                    'specifications' => $specifications
                ];
                
                if ($action === 'add') {
                    $newId = db()->insert('warehouse_items', $data);
                    if ($newId) {
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'create',
                            'module' => 'warehouse',
                            'record_id' => $newId,
                            'new_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        redirect('warehouse_items.php?success=1');
                    } else {
                        $error = 'خطا در ثبت کالا. لطفاً مجدداً تلاش کنید.';
                    }
                } else {
                    $updated = db()->update('warehouse_items', $data, 'id = :id', [':id' => $id]);
                    if ($updated !== false) {
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'update',
                            'module' => 'warehouse',
                            'record_id' => $id,
                            'old_data' => json_encode($item, JSON_UNESCAPED_UNICODE),
                            'new_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        $success = 'اطلاعات کالا با موفقیت به‌روزرسانی شد.';
                        $item = db()->selectOne("SELECT * FROM warehouse_items WHERE id = :id", [':id' => $id]);
                    } else {
                        $error = 'خطا در به‌روزرسانی کالا.';
                    }
                }
            }
        }
    }
}

// دریافت دسته‌بندی‌ها
$categories = db()->select(
    "SELECT DISTINCT category FROM warehouse_items WHERE category IS NOT NULL AND category != '' ORDER BY category"
);

$subcategories = db()->select(
    "SELECT DISTINCT subcategory FROM warehouse_items WHERE subcategory IS NOT NULL AND subcategory != '' ORDER BY subcategory"
);

// دریافت موجودی کالا در انبارها
$stocks = [];
if ($item) {
    $stocks = db()->select("
        SELECT w.name as warehouse_name,
               COALESCE(SUM(CASE 
                   WHEN wt.type = 'in' AND wt.status = 'completed' THEN wt.quantity
                   WHEN wt.type = 'out' AND wt.status = 'completed' THEN -wt.quantity
                   ELSE 0
               END), 0) as stock
        FROM warehouses w
        LEFT JOIN warehouse_transactions wt ON wt.warehouse_id = w.id AND wt.item_id = :item_id
        WHERE w.is_active = 1
        GROUP BY w.id, w.name
        HAVING stock > 0 OR w.id IN (
            SELECT DISTINCT warehouse_id FROM warehouse_transactions WHERE item_id = :item_id
        )
    ", [':item_id' => $id]);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'تعریف کالای جدید' : ($action === 'view' ? 'مشاهده کالا' : 'ویرایش کالا'); ?> - <?php echo SITE_TITLE; ?></title>
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
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
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .stock-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        
        .stock-table th,
        .stock-table td {
            padding: 12px;
            text-align: right;
            border: 1px solid #e0e0e0;
        }
        
        .stock-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .info-box {
            background: #e3f2fd;
            border-right: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
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
            <h1>
                <?php if ($action === 'add'): ?>
                    ➕ تعریف کالای جدید
                <?php elseif ($action === 'view'): ?>
                    👁 مشاهده کالا
                <?php else: ?>
                    ✏️ ویرایش کالا
                <?php endif; ?>
            </h1>
            <a href="warehouse_items.php" class="btn btn-secondary">بازگشت به لیست</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'view' && $item): ?>
            <div class="info-box">
                ℹ️ این صفحه فقط برای مشاهده است. برای ویرایش از دکمه ویرایش استفاده کنید.
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>کد کالا <span class="required">*</span></label>
                        <input type="text" name="code" 
                               value="<?php echo h($item['code'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : 'required'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نام کالا <span class="required">*</span></label>
                        <input type="text" name="name" 
                               value="<?php echo h($item['name'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : 'required'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>دسته‌بندی</label>
                        <input type="text" name="category" list="categories"
                               value="<?php echo h($item['category'] ?? ''); ?>"
                               placeholder="انتخاب یا ایجاد دسته جدید"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo h($cat['category']); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label>زیر دسته</label>
                        <input type="text" name="subcategory" list="subcategories"
                               value="<?php echo h($item['subcategory'] ?? ''); ?>"
                               placeholder="انتخاب یا ایجاد زیر دسته جدید"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        <datalist id="subcategories">
                            <?php foreach ($subcategories as $sub): ?>
                                <option value="<?php echo h($sub['subcategory']); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد شمارش <span class="required">*</span></label>
                        <select name="unit" <?php echo $action === 'view' ? 'disabled' : 'required'; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php
                            $units = ['عدد', 'دستگاه', 'کیلوگرم', 'گرم', 'متر', 'سانتی‌متر', 'لیتر', 'میلی‌لیتر', 'بسته', 'جعبه', 'کارتن'];
                            foreach ($units as $unit):
                            ?>
                                <option value="<?php echo h($unit); ?>" 
                                        <?php echo isset($item['unit']) && $item['unit'] === $unit ? 'selected' : ''; ?>>
                                    <?php echo h($unit); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>حداقل موجودی (برای هشدار)</label>
                        <input type="number" name="min_stock" step="0.01" min="0"
                               value="<?php echo h($item['min_stock'] ?? '0'); ?>"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>حداکثر موجودی (برای نمایش)</label>
                        <input type="number" name="max_stock" step="0.01" min="0"
                               value="<?php echo h($item['max_stock'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>قیمت واحد</label>
                        <input type="number" name="unit_price" step="0.01" min="0"
                               value="<?php echo h($item['unit_price'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد پول</label>
                        <select name="currency" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                            <?php
                            $currencies = ['IRR' => 'ریال', 'USD' => 'دلار', 'EUR' => 'یورو'];
                            foreach ($currencies as $code => $name):
                            ?>
                                <option value="<?php echo h($code); ?>" 
                                        <?php echo isset($item['currency']) && $item['currency'] === $code ? 'selected' : ''; ?>>
                                    <?php echo h($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>توضیحات</label>
                        <textarea name="description" 
                                  <?php echo $action === 'view' ? 'disabled' : ''; ?>><?php echo h($item['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>مشخصات فنی (JSON)</label>
                        <textarea name="specifications" 
                                  placeholder='{"وزن": "10 کیلوگرم", "ابعاد": "50x30x20"}'
                                  <?php echo $action === 'view' ? 'disabled' : ''; ?>><?php echo h($item['specifications'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <?php if ($action === 'view' && !empty($stocks)): ?>
                    <div class="section-title">📊 موجودی در انبارها</div>
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th>انبار</th>
                                <th>موجودی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stocks as $stock): ?>
                                <tr>
                                    <td><?php echo h($stock['warehouse_name']); ?></td>
                                    <td><strong><?php echo en2fa(number_format($stock['stock'], 2)); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? '✓ ثبت کالا' : '✓ ذخیره تغییرات'; ?>
                        </button>
                        <a href="warehouse_items.php" class="btn btn-cancel">انصراف</a>
                    </div>
                <?php else: ?>
                    <div class="form-actions">
                        <a href="warehouse_item.php?action=edit&id=<?php echo $id; ?>" class="btn btn-primary">
                            ✏️ ویرایش
                        </a>
                        <a href="warehouse_items.php" class="btn btn-cancel">بازگشت</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>