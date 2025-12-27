<?php
/**
 * فرم کنترل کیفیت - QC Form
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
    if (!check_permission('qc', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
} else {
    if (!check_permission('qc', PERMISSION_WRITE)) {
        die('شما مجوز ثبت/ویرایش فرم کیفیت را ندارید.');
    }
}

// دریافت فرم برای ویرایش یا مشاهده
$qcForm = null;
if ($id > 0 && in_array($action, ['edit', 'view'])) {
    $qcForm = db()->selectOne(
        "SELECT q.*, 
                p.title as project_title,
                p.code as project_code,
                pr.name as product_name,
                u1.fullname as inspector_name,
                u2.fullname as creator_name
         FROM qc_forms q
         LEFT JOIN projects p ON p.id = q.project_id
         LEFT JOIN products pr ON pr.id = q.product_id
         LEFT JOIN users u1 ON u1.id = q.inspector_user_id
         LEFT JOIN users u2 ON u2.id = q.created_by
         WHERE q.id = :id",
        [':id' => $id]
    );
    
    if (!$qcForm) {
        die('فرم کنترل کیفیت یافت نشد.');
    }
    
    // Parse JSON fields
    $qcForm['checklist'] = json_decode($qcForm['checklist'] ?? '[]', true);
    $qcForm['attachments'] = json_decode($qcForm['attachments'] ?? '[]', true);
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $formNumber = sanitize_input($_POST['form_number'] ?? '');
        $type = sanitize_input($_POST['type'] ?? '');
        $title = sanitize_input($_POST['title'] ?? '');
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] ? (int)$_POST['project_id'] : null;
        $productId = isset($_POST['product_id']) && $_POST['product_id'] ? (int)$_POST['product_id'] : null;
        $status = sanitize_input($_POST['status'] ?? 'open');
        $inspectionDate = sanitize_input($_POST['inspection_date'] ?? '');
        $inspectorUserId = isset($_POST['inspector_user_id']) && $_POST['inspector_user_id'] ? (int)$_POST['inspector_user_id'] : null;
        $result = sanitize_input($_POST['result'] ?? '');
        $findings = sanitize_input($_POST['findings'] ?? '');
        $correctiveActions = sanitize_input($_POST['corrective_actions'] ?? '');
        
        // Checklist processing
        $checklist = [];
        if (isset($_POST['checklist_items']) && is_array($_POST['checklist_items'])) {
            foreach ($_POST['checklist_items'] as $index => $item) {
                if (!empty($item)) {
                    $checklist[] = [
                        'item' => sanitize_input($item),
                        'status' => sanitize_input($_POST['checklist_status'][$index] ?? 'pending'),
                        'note' => sanitize_input($_POST['checklist_notes'][$index] ?? '')
                    ];
                }
            }
        }
        
        // اعتبارسنجی
        if (empty($formNumber) || empty($title) || empty($type)) {
            $error = 'لطفاً شماره فرم، عنوان و نوع را وارد کنید.';
        } else {
            // چک تکراری بودن شماره فرم
            $existsSql = "SELECT COUNT(*) as count FROM qc_forms WHERE form_number = :number";
            $existsParams = [':number' => $formNumber];
            
            if ($action === 'edit') {
                $existsSql .= " AND id != :id";
                $existsParams[':id'] = $id;
            }
            
            $exists = db()->selectOne($existsSql, $existsParams);
            
            if ($exists && $exists['count'] > 0) {
                $error = 'شماره فرم تکراری است.';
            } else {
                $data = [
                    'form_number' => $formNumber,
                    'type' => $type,
                    'project_id' => $projectId,
                    'product_id' => $productId,
                    'title' => $title,
                    'status' => $status,
                    'inspection_date' => $inspectionDate ?: null,
                    'inspector_user_id' => $inspectorUserId,
                    'result' => $result ?: null,
                    'checklist' => json_encode($checklist),
                    'findings' => $findings,
                    'corrective_actions' => $correctiveActions
                ];
                
                if ($action === 'add') {
                    $data['created_by'] = $_SESSION['user_id'];
                    $newId = db()->insert('qc_forms', $data);
                    
                    if ($newId) {
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'create_qc_form',
                            'module' => 'qc',
                            'record_id' => $newId,
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        redirect(SITE_URL . '/qc.php?msg=added');
                    } else {
                        $error = 'خطا در ذخیره فرم کنترل کیفیت.';
                    }
                } elseif ($action === 'edit') {
                    $updated = db()->update('qc_forms', $data, 'id = :id', [':id' => $id]);
                    
                    if ($updated !== false) {
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'update_qc_form',
                            'module' => 'qc',
                            'record_id' => $id,
                            'old_data' => json_encode($qcForm),
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        redirect(SITE_URL . '/qc.php?msg=updated');
                    } else {
                        $error = 'خطا در به‌روزرسانی فرم.';
                    }
                }
            }
        }
    }
}

// دریافت لیست‌ها برای انتخاب
$projects = db()->select(
    "SELECT id, code, title FROM projects WHERE status NOT IN ('cancelled', 'completed') ORDER BY title"
);

$products = db()->select(
    "SELECT id, code, name FROM products WHERE status = 'active' ORDER BY name"
);

$users = db()->select(
    "SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname"
);

$readonly = ($action === 'view') ? 'readonly disabled' : '';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'افزودن' : ($action === 'edit' ? 'ویرایش' : 'مشاهده'); ?> فرم کنترل کیفیت</title>
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
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checklist-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .checklist-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
        }
        
        .checklist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checklist-input {
            flex: 1;
            margin-left: 10px;
        }
        
        .checklist-status {
            width: 150px;
            margin-left: 10px;
        }
        
        .btn-remove {
            background: #f44336;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-add {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
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
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
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
        
        .helper-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
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
            
            .checklist-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .checklist-input,
            .checklist-status {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                ✅ 
                <?php 
                echo $action === 'add' ? 'فرم بازرسی جدید' : 
                     ($action === 'edit' ? 'ویرایش فرم بازرسی' : 'مشاهده فرم بازرسی');
                ?>
            </h1>
            <a href="qc.php" class="btn btn-back">⬅️ بازگشت</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'view' && $qcForm): ?>
            <div class="info-box">
                <h3>اطلاعات ثبت</h3>
                <p><strong>ایجادکننده:</strong> <?php echo h($qcForm['creator_name']); ?></p>
                <p><strong>تاریخ ثبت:</strong> <?php echo en2fa(date('Y/m/d H:i', strtotime($qcForm['created_at']))); ?></p>
                <p><strong>آخرین ویرایش:</strong> <?php echo en2fa(date('Y/m/d H:i', strtotime($qcForm['updated_at']))); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            شماره فرم <span class="required">*</span>
                        </label>
                        <input type="text" name="form_number" 
                               value="<?php echo h($qcForm['form_number'] ?? 'QC-' . date('Ymd') . '-'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            نوع بازرسی <span class="required">*</span>
                        </label>
                        <select name="type" required <?php echo $readonly; ?>>
                            <option value="">-- انتخاب کنید --</option>
                            <option value="visual" <?php echo ($qcForm['type'] ?? '') === 'visual' ? 'selected' : ''; ?>>
                                بازرسی چشمی
                            </option>
                            <option value="dimensional" <?php echo ($qcForm['type'] ?? '') === 'dimensional' ? 'selected' : ''; ?>>
                                بازرسی ابعادی
                            </option>
                            <option value="material" <?php echo ($qcForm['type'] ?? '') === 'material' ? 'selected' : ''; ?>>
                                آزمایش مواد
                            </option>
                            <option value="functional" <?php echo ($qcForm['type'] ?? '') === 'functional' ? 'selected' : ''; ?>>
                                تست عملکرد
                            </option>
                            <option value="pressure" <?php echo ($qcForm['type'] ?? '') === 'pressure' ? 'selected' : ''; ?>>
                                تست فشار
                            </option>
                            <option value="welding" <?php echo ($qcForm['type'] ?? '') === 'welding' ? 'selected' : ''; ?>>
                                بازرسی جوش
                            </option>
                            <option value="ndt" <?php echo ($qcForm['type'] ?? '') === 'ndt' ? 'selected' : ''; ?>>
                                آزمایش غیرمخرب (NDT)
                            </option>
                            <option value="final" <?php echo ($qcForm['type'] ?? '') === 'final' ? 'selected' : ''; ?>>
                                بازرسی نهایی
                            </option>
                            <option value="other" <?php echo ($qcForm['type'] ?? '') === 'other' ? 'selected' : ''; ?>>
                                سایر
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>
                            عنوان بازرسی <span class="required">*</span>
                        </label>
                        <input type="text" name="title" 
                               value="<?php echo h($qcForm['title'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>پروژه مرتبط</label>
                        <select name="project_id" <?php echo $readonly; ?>>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" 
                                        <?php echo ($qcForm['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code']); ?> - <?php echo h($project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>محصول/قطعه</label>
                        <select name="product_id" <?php echo $readonly; ?>>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" 
                                        <?php echo ($qcForm['product_id'] ?? 0) == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($product['code']); ?> - <?php echo h($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ بازرسی</label>
                        <input type="date" name="inspection_date" 
                               value="<?php echo h($qcForm['inspection_date'] ?? date('Y-m-d')); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>بازرس</label>
                        <select name="inspector_user_id" <?php echo $readonly; ?>>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo ($qcForm['inspector_user_id'] ?? $_SESSION['user_id']) == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($user['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $readonly; ?>>
                            <option value="open" <?php echo ($qcForm['status'] ?? 'open') === 'open' ? 'selected' : ''; ?>>
                                باز
                            </option>
                            <option value="in_progress" <?php echo ($qcForm['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>
                                در حال انجام
                            </option>
                            <option value="completed" <?php echo ($qcForm['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>
                                تکمیل شده
                            </option>
                            <option value="approved" <?php echo ($qcForm['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>
                                تایید شده
                            </option>
                            <option value="rejected" <?php echo ($qcForm['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>
                                رد شده
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>نتیجه بازرسی</label>
                        <select name="result" <?php echo $readonly; ?>>
                            <option value="">-- هنوز مشخص نشده --</option>
                            <option value="pass" <?php echo ($qcForm['result'] ?? '') === 'pass' ? 'selected' : ''; ?>>
                                ✅ قبول
                            </option>
                            <option value="fail" <?php echo ($qcForm['result'] ?? '') === 'fail' ? 'selected' : ''; ?>>
                                ❌ رد
                            </option>
                            <option value="conditional" <?php echo ($qcForm['result'] ?? '') === 'conditional' ? 'selected' : ''; ?>>
                                ⚠️ مشروط
                            </option>
                        </select>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="section-title">
                        📋 چک‌لیست بازرسی
                    </div>
                    
                    <div class="checklist-container" id="checklistContainer">
                        <?php 
                        $checklist = $qcForm['checklist'] ?? [['item' => '', 'status' => 'pending', 'note' => '']];
                        foreach ($checklist as $index => $item): 
                        ?>
                            <div class="checklist-item">
                                <div class="checklist-header">
                                    <input type="text" name="checklist_items[]" 
                                           class="checklist-input" 
                                           placeholder="آیتم بازرسی (مثلاً: بررسی ابعاد قطعه)"
                                           value="<?php echo h($item['item']); ?>">
                                    
                                    <select name="checklist_status[]" class="checklist-status">
                                        <option value="pending" <?php echo $item['status'] === 'pending' ? 'selected' : ''; ?>>
                                            در انتظار
                                        </option>
                                        <option value="pass" <?php echo $item['status'] === 'pass' ? 'selected' : ''; ?>>
                                            ✅ قبول
                                        </option>
                                        <option value="fail" <?php echo $item['status'] === 'fail' ? 'selected' : ''; ?>>
                                            ❌ رد
                                        </option>
                                        <option value="na" <?php echo $item['status'] === 'na' ? 'selected' : ''; ?>>
                                            قابل اجرا نیست
                                        </option>
                                    </select>
                                    
                                    <button type="button" class="btn-remove" onclick="removeChecklistItem(this)">
                                        🗑️ حذف
                                    </button>
                                </div>
                                <input type="text" name="checklist_notes[]" 
                                       placeholder="یادداشت (اختیاری)"
                                       style="width: 100%; padding: 8px; border: 1px solid #e0e0e0; border-radius: 6px; margin-top: 10px;"
                                       value="<?php echo h($item['note']); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn-add" onclick="addChecklistItem()">
                        ➕ افزودن آیتم
                    </button>
                <?php else: ?>
                    <?php if (!empty($qcForm['checklist'])): ?>
                        <div class="section-title">
                            📋 چک‌لیست بازرسی
                        </div>
                        <div class="checklist-container">
                            <?php foreach ($qcForm['checklist'] as $item): ?>
                                <div class="checklist-item">
                                    <strong><?php echo h($item['item']); ?></strong>
                                    <div style="margin-top: 10px;">
                                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;
                                              background: <?php echo $item['status'] === 'pass' ? '#d4edda' : ($item['status'] === 'fail' ? '#f8d7da' : '#e0e0e0'); ?>;
                                              color: <?php echo $item['status'] === 'pass' ? '#155724' : ($item['status'] === 'fail' ? '#721c24' : '#666'); ?>;">
                                            <?php 
                                            $statuses = ['pending' => 'در انتظار', 'pass' => 'قبول', 'fail' => 'رد', 'na' => 'قابل اجرا نیست'];
                                            echo $statuses[$item['status']] ?? $item['status'];
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($item['note']): ?>
                                        <div style="margin-top: 10px; color: #666; font-size: 13px;">
                                            💬 <?php echo h($item['note']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="section-title">
                    📝 یافته‌ها و اقدامات اصلاحی
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>یافته‌های بازرسی</label>
                        <textarea name="findings" rows="5" <?php echo $readonly; ?>><?php echo h($qcForm['findings'] ?? ''); ?></textarea>
                        <div class="helper-text">💡 مشکلات و موارد غیرمنطبق یافت شده را شرح دهید</div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>اقدامات اصلاحی</label>
                        <textarea name="corrective_actions" rows="5" <?php echo $readonly; ?>><?php echo h($qcForm['corrective_actions'] ?? ''); ?></textarea>
                        <div class="helper-text">💡 اقدامات لازم برای رفع مشکلات را شرح دهید</div>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            💾 <?php echo $action === 'add' ? 'ثبت فرم' : 'به‌روزرسانی'; ?>
                        </button>
                        <a href="qc.php" class="btn btn-back">❌ انصراف</a>
                    </div>
                <?php else: ?>
                    <div class="form-actions">
                        <?php if (check_permission('qc', PERMISSION_WRITE)): ?>
                            <a href="qcform.php?action=edit&id=<?php echo $qcForm['id']; ?>" 
                               class="btn btn-primary">✏️ ویرایش</a>
                        <?php endif; ?>
                        
                        <?php if (check_permission('qc', PERMISSION_FULL) && $qcForm['status'] === 'completed'): ?>
                            <a href="qcform.php?action=approve&id=<?php echo $qcForm['id']; ?>" 
                               class="btn btn-success"
                               onclick="return confirm('آیا این فرم را تایید می‌کنید؟')">
                               ✅ تایید فرم
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        function addChecklistItem() {
            const container = document.getElementById('checklistContainer');
            const newItem = document.createElement('div');
            newItem.className = 'checklist-item';
            newItem.innerHTML = `
                <div class="checklist-header">
                    <input type="text" name="checklist_items[]" 
                           class="checklist-input" 
                           placeholder="آیتم بازرسی">
                    
                    <select name="checklist_status[]" class="checklist-status">
                        <option value="pending">در انتظار</option>
                        <option value="pass">✅ قبول</option>
                        <option value="fail">❌ رد</option>
                        <option value="na">قابل اجرا نیست</option>
                    </select>
                    
                    <button type="button" class="btn-remove" onclick="removeChecklistItem(this)">
                        🗑️ حذف
                    </button>
                </div>
                <input type="text" name="checklist_notes[]" 
                       placeholder="یادداشت (اختیاری)"
                       style="width: 100%; padding: 8px; border: 1px solid #e0e0e0; border-radius: 6px; margin-top: 10px;">
            `;
            container.appendChild(newItem);
        }
        
        function removeChecklistItem(btn) {
            const item = btn.closest('.checklist-item');
            const container = document.getElementById('checklistContainer');
            
            // حداقل یک آیتم باید باقی بماند
            if (container.children.length > 1) {
                item.remove();
            } else {
                alert('حداقل یک آیتم باید در چک‌لیست وجود داشته باشد.');
            }
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>