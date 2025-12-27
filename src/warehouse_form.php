<?php
/**
 * فرم انبار - افزودن/ویرایش
 * Warehouse Form
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('warehouse', PERMISSION_FULL)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$action = $_GET['action'] ?? 'add';
$warehouseId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$warehouse = null;

if ($action === 'edit') {
    if (!$warehouseId) {
        redirect(SITE_URL . '/warehouses.php');
    }
    
    $warehouse = db()->selectOne("SELECT * FROM warehouses WHERE id = :id", [':id' => $warehouseId]);
    
    if (!$warehouse) {
        redirect(SITE_URL . '/warehouses.php');
    }
}

// ذخیره انبار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $data = [
            'code' => strtoupper(sanitize_input($_POST['code'])),
            'name' => sanitize_input($_POST['name']),
            'type' => sanitize_input($_POST['type']),
            'location' => sanitize_input($_POST['location'] ?? ''),
            'manager_user_id' => (int)($_POST['manager_user_id'] ?? 0) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // اعتبارسنجی
        if (empty($data['code']) || empty($data['name']) || empty($data['type'])) {
            $error = 'کد، نام و نوع انبار الزامی است.';
        } else {
            // چک یکتا بودن کد
            $existingCode = db()->selectOne(
                "SELECT id FROM warehouses WHERE code = :code" . ($action === 'edit' ? " AND id != :id" : ""),
                $action === 'edit' ? [':code' => $data['code'], ':id' => $warehouseId] : [':code' => $data['code']]
            );
            
            if ($existingCode) {
                $error = 'این کد قبلاً استفاده شده است.';
            } else {
                try {
                    if ($action === 'add') {
                        $warehouseId = db()->insert('warehouses', $data);
                        $logAction = 'add_warehouse';
                    } else {
                        db()->update('warehouses', $data, 'id = :id', [':id' => $warehouseId]);
                        $logAction = 'edit_warehouse';
                    }
                    
                    // ثبت لاگ
                    db()->insert('logs', [
                        'user_id' => $_SESSION['user_id'],
                        'action' => $logAction,
                        'module' => 'warehouse',
                        'record_id' => $warehouseId,
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    redirect(SITE_URL . '/warehouses.php?msg=saved');
                } catch (Exception $e) {
                    $error = 'خطا در ذخیره اطلاعات: ' . $e->getMessage();
                }
            }
        }
    }
}

// دریافت لیست کاربران برای مدیر انبار
$users = db()->select("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'انبار جدید' : 'ویرایش انبار'; ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 800px;
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
        
        .btn-back {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
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
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .checkbox-group input {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }
        
        .btn {
            padding: 12px 30px;
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
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $action === 'add' ? '🏢 انبار جدید' : '✏️ ویرایش انبار'; ?></h1>
            <a href="warehouses.php" class="btn-back">⬅ بازگشت</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-section">
                    <h2>اطلاعات پایه</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>کد انبار *</label>
                            <input type="text" name="code" 
                                   value="<?php echo h($warehouse['code'] ?? ''); ?>" 
                                   placeholder="مثال: WH-MAIN"
                                   required
                                   pattern="[A-Z0-9\-]+"
                                   title="فقط حروف بزرگ انگلیسی، اعداد و خط تیره">
                        </div>
                        
                        <div class="form-group">
                            <label>نام انبار *</label>
                            <input type="text" name="name" 
                                   value="<?php echo h($warehouse['name'] ?? ''); ?>" 
                                   placeholder="مثال: انبار اصلی"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label>نوع انبار *</label>
                            <select name="type" required>
                                <option value="">انتخاب کنید</option>
                                <option value="main" <?php echo ($warehouse['type'] ?? '') === 'main' ? 'selected' : ''; ?>>
                                    انبار اصلی
                                </option>
                                <option value="site" <?php echo ($warehouse['type'] ?? '') === 'site' ? 'selected' : ''; ?>>
                                    انبار پای کار
                                </option>
                                <option value="waste" <?php echo ($warehouse['type'] ?? '') === 'waste' ? 'selected' : ''; ?>>
                                    انبار زایعات
                                </option>
                                <option value="project" <?php echo ($warehouse['type'] ?? '') === 'project' ? 'selected' : ''; ?>>
                                    انبار پروژه
                                </option>
                                <option value="electronic" <?php echo ($warehouse['type'] ?? '') === 'electronic' ? 'selected' : ''; ?>>
                                    انبار الکترونیک
                                </option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>مدیر انبار</label>
                            <select name="manager_user_id">
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo ($warehouse['manager_user_id'] ?? 0) == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($user['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>موقعیت مکانی</h2>
                    <div class="form-group">
                        <label>آدرس / موقعیت</label>
                        <textarea name="location" rows="3" 
                                  placeholder="آدرس کامل یا توضیحات محل قرار گیری انبار"><?php echo h($warehouse['location'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>وضعیت</h2>
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" 
                               <?php echo ($warehouse['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="is_active">انبار فعال است</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 ذخیره</button>
                    <a href="warehouses.php" class="btn btn-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>