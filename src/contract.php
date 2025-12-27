<?php
/**
 * فرم قرارداد - افزودن/ویرایش/مشاهده
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
    if (!check_permission('contracts', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
} elseif ($action === 'add') {
    if (!check_permission('contracts', PERMISSION_WRITE)) {
        die('شما مجوز افزودن قرارداد را ندارید.');
    }
} elseif ($action === 'edit') {
    if (!check_permission('contracts', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش قرارداد را ندارید.');
    }
} elseif ($action === 'delete') {
    if (!check_permission('contracts', PERMISSION_FULL)) {
        die('شما مجوز حذف قرارداد را ندارید.');
    }
}

// دریافت قرارداد برای ویرایش یا مشاهده
$contract = null;
if ($id > 0 && in_array($action, ['edit', 'view'])) {
    $contract = db()->selectOne(
        "SELECT c.*, 
                cnt.name as party_name,
                p.title as project_title,
                u.fullname as creator_name
         FROM contracts c
         LEFT JOIN contacts cnt ON cnt.id = c.party_contact_id
         LEFT JOIN projects p ON p.id = c.project_id
         LEFT JOIN users u ON u.id = c.created_by
         WHERE c.id = :id",
        [':id' => $id]
    );
    
    if (!$contract) {
        die('قرارداد یافت نشد.');
    }
}

// حذف قرارداد
if ($action === 'delete' && $id > 0) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $deleted = db()->delete('contracts', 'id = :id', [':id' => $id]);
        if ($deleted) {
            // ثبت لاگ
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'delete_contract',
                'module' => 'contracts',
                'record_id' => $id,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            redirect(SITE_URL . '/contracts.php?msg=deleted');
        }
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $contractNumber = sanitize_input($_POST['contract_number'] ?? '');
        $title = sanitize_input($_POST['title'] ?? '');
        $type = sanitize_input($_POST['type'] ?? '');
        $partyContactId = isset($_POST['party_contact_id']) && $_POST['party_contact_id'] ? (int)$_POST['party_contact_id'] : null;
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] ? (int)$_POST['project_id'] : null;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
        $currency = sanitize_input($_POST['currency'] ?? 'IRR');
        $startDate = sanitize_input($_POST['start_date'] ?? '');
        $endDate = sanitize_input($_POST['end_date'] ?? '');
        $status = sanitize_input($_POST['status'] ?? 'draft');
        $description = sanitize_input($_POST['description'] ?? '');
        $terms = sanitize_input($_POST['terms'] ?? '');
        
        // اعتبارسنجی
        if (empty($contractNumber) || empty($title)) {
            $error = 'لطفاً شماره و عنوان قرارداد را وارد کنید.';
        } else {
            // چک تکراری بودن شماره قرارداد
            $existsSql = "SELECT COUNT(*) as count FROM contracts WHERE contract_number = :number";
            $existsParams = [':number' => $contractNumber];
            
            if ($action === 'edit') {
                $existsSql .= " AND id != :id";
                $existsParams[':id'] = $id;
            }
            
            $exists = db()->selectOne($existsSql, $existsParams);
            
            if ($exists && $exists['count'] > 0) {
                $error = 'شماره قرارداد تکراری است.';
            } else {
                $data = [
                    'contract_number' => $contractNumber,
                    'title' => $title,
                    'type' => $type,
                    'party_contact_id' => $partyContactId,
                    'project_id' => $projectId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'start_date' => $startDate ?: null,
                    'end_date' => $endDate ?: null,
                    'status' => $status,
                    'description' => $description,
                    'terms' => $terms
                ];
                
                if ($action === 'add') {
                    $data['created_by'] = $_SESSION['user_id'];
                    $newId = db()->insert('contracts', $data);
                    
                    if ($newId) {
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'create_contract',
                            'module' => 'contracts',
                            'record_id' => $newId,
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        redirect(SITE_URL . '/contracts.php?msg=added');
                    } else {
                        $error = 'خطا در ذخیره قرارداد.';
                    }
                } elseif ($action === 'edit') {
                    $updated = db()->update('contracts', $data, 'id = :id', [':id' => $id]);
                    
                    if ($updated !== false) {
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'update_contract',
                            'module' => 'contracts',
                            'record_id' => $id,
                            'old_data' => json_encode($contract),
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        $success = 'قرارداد با موفقیت به‌روزرسانی شد.';
                        
                        // بارگذاری مجدد
                        $contract = db()->selectOne(
                            "SELECT c.*, cnt.name as party_name, p.title as project_title
                             FROM contracts c
                             LEFT JOIN contacts cnt ON cnt.id = c.party_contact_id
                             LEFT JOIN projects p ON p.id = c.project_id
                             WHERE c.id = :id",
                            [':id' => $id]
                        );
                    } else {
                        $error = 'خطا در به‌روزرسانی قرارداد.';
                    }
                }
            }
        }
    }
}

// دریافت لیست مخاطبین برای انتخاب
$contacts = db()->select(
    "SELECT id, name, type, company_name FROM contacts WHERE is_active = 1 ORDER BY name"
);

// دریافت لیست پروژه‌ها
$projects = db()->select(
    "SELECT id, code, title FROM projects WHERE status NOT IN ('cancelled', 'completed') ORDER BY title"
);

$readonly = ($action === 'view') ? 'readonly disabled' : '';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'افزودن' : ($action === 'edit' ? 'ویرایش' : 'مشاهده'); ?> قرارداد</title>
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
        
        .btn-back:hover {
            background: #5a6268;
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
            display: flex;
            align-items: center;
            gap: 5px;
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
        
        .form-group input[readonly],
        .form-group select:disabled,
        .form-group textarea[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-draft { background: #e0e0e0; color: #666; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-active { background: #d4edda; color: #155724; }
        .status-completed { background: #cce5ff; color: #004085; }
        .status-terminated { background: #f8d7da; color: #721c24; }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            border-right: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-box p {
            color: #555;
            margin: 5px 0;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                📄 
                <?php 
                echo $action === 'add' ? 'افزودن قرارداد جدید' : 
                     ($action === 'edit' ? 'ویرایش قرارداد' : 'مشاهده قرارداد');
                ?>
            </h1>
            <a href="contracts.php" class="btn btn-back">⬅️ بازگشت</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'view' && $contract): ?>
            <div class="info-box">
                <h3>اطلاعات ثبت</h3>
                <p><strong>ایجادکننده:</strong> <?php echo h($contract['creator_name']); ?></p>
                <p><strong>تاریخ ثبت:</strong> <?php echo h($contract['created_at']); ?></p>
                <p><strong>آخرین ویرایش:</strong> <?php echo h($contract['updated_at']); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            شماره قرارداد <span class="required">*</span>
                        </label>
                        <input type="text" name="contract_number" 
                               value="<?php echo h($contract['contract_number'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            وضعیت
                        </label>
                        <select name="status" <?php echo $readonly; ?>>
                            <option value="draft" <?php echo ($contract['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>
                                پیش‌نویس
                            </option>
                            <option value="pending" <?php echo ($contract['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>
                                در انتظار تایید
                            </option>
                            <option value="active" <?php echo ($contract['status'] ?? '') === 'active' ? 'selected' : ''; ?>>
                                فعال
                            </option>
                            <option value="completed" <?php echo ($contract['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>
                                تکمیل شده
                            </option>
                            <option value="terminated" <?php echo ($contract['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>
                                فسخ شده
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>
                            عنوان قرارداد <span class="required">*</span>
                        </label>
                        <input type="text" name="title" 
                               value="<?php echo h($contract['title'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نوع قرارداد</label>
                        <input type="text" name="type" 
                               value="<?php echo h($contract['type'] ?? ''); ?>" 
                               placeholder="خرید، فروش، مشاوره، پیمانکاری..." 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>طرف قرارداد</label>
                        <select name="party_contact_id" <?php echo $readonly; ?>>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?php echo $contact['id']; ?>" 
                                        <?php echo ($contract['party_contact_id'] ?? 0) == $contact['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($contact['name']); ?>
                                    <?php if ($contact['company_name']): ?>
                                        (<?php echo h($contact['company_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>پروژه مرتبط</label>
                        <select name="project_id" <?php echo $readonly; ?>>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" 
                                        <?php echo ($contract['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code']); ?> - <?php echo h($project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>مبلغ قرارداد</label>
                        <input type="number" name="amount" step="0.01" 
                               value="<?php echo h($contract['amount'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد پول</label>
                        <select name="currency" <?php echo $readonly; ?>>
                            <option value="IRR" <?php echo ($contract['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>
                                ریال (IRR)
                            </option>
                            <option value="USD" <?php echo ($contract['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>
                                دلار (USD)
                            </option>
                            <option value="EUR" <?php echo ($contract['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>
                                یورو (EUR)
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ شروع</label>
                        <input type="date" name="start_date" 
                               value="<?php echo h($contract['start_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ پایان</label>
                        <input type="date" name="end_date" 
                               value="<?php echo h($contract['end_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($contract['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>شرایط و ضوابط قرارداد</label>
                        <textarea name="terms" style="min-height: 200px;" <?php echo $readonly; ?>><?php echo h($contract['terms'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            💾 <?php echo $action === 'add' ? 'ذخیره قرارداد' : 'به‌روزرسانی'; ?>
                        </button>
                        <a href="contracts.php" class="btn btn-back">❌ انصراف</a>
                    </div>
                <?php else: ?>
                    <div class="form-actions">
                        <?php if (check_permission('contracts', PERMISSION_WRITE)): ?>
                            <a href="contract.php?action=edit&id=<?php echo $contract['id']; ?>" 
                               class="btn btn-primary">✏️ ویرایش</a>
                        <?php endif; ?>
                        <?php if (check_permission('contracts', PERMISSION_FULL)): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('آیا از حذف این قرارداد اطمینان دارید؟');">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-danger">🗑️ حذف</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>