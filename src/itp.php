<?php
/**
 * فرم ITP - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$itp = null;
$testPoints = [];
$readonly = '';

if ($action === 'view') {
    if (!check_permission('qc', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
    $readonly = 'readonly';
} elseif ($action === 'delete') {
    if (!check_permission('qc', PERMISSION_FULL)) {
        die('شما مجوز حذف ITP را ندارید.');
    }
} else {
    if (!check_permission('qc', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش ITP را ندارید.');
    }
}

if ($action === 'delete' && $id > 0) {
    db()->beginTransaction();
    try {
        db()->delete('itp_test_points', 'itp_id = :id', [':id' => $id]);
        db()->delete('itp', 'id = :id', [':id' => $id]);
        
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'delete_itp',
            'module' => 'qc',
            'record_id' => $id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        db()->commit();
        redirect(SITE_URL . '/itps.php?msg=deleted');
    } catch (Exception $e) {
        db()->rollback();
        $error = 'خطا در حذف ITP.';
    }
    $action = 'view';
}

if (($action === 'edit' || $action === 'view') && $id > 0) {
    $itp = db()->selectOne("SELECT * FROM itp WHERE id = :id", [':id' => $id]);
    if (!$itp) {
        die('ITP یافت نشد.');
    }
    
    $testPoints = db()->select("SELECT * FROM itp_test_points WHERE itp_id = :id ORDER BY point_number", [':id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('توکن امنیتی نامعتبر است.');
    }
    
    $data = [
        'itp_number' => sanitize_input($_POST['itp_number']),
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'product_id' => !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null,
        'title' => sanitize_input($_POST['title']),
        'description' => sanitize_input($_POST['description']),
        'version' => sanitize_input($_POST['version']),
        'status' => sanitize_input($_POST['status']),
        'issue_date' => !empty($_POST['issue_date']) ? $_POST['issue_date'] : null,
        'revision_date' => !empty($_POST['revision_date']) ? $_POST['revision_date'] : null,
        'notes' => sanitize_input($_POST['notes'])
    ];
    
    if (empty($data['itp_number'])) {
        $error = 'شماره ITP الزامی است.';
    } elseif (empty($data['title'])) {
        $error = 'عنوان الزامی است.';
    } else {
        $existing = db()->selectOne(
            "SELECT id FROM itp WHERE itp_number = :itp_number AND id != :id",
            [':itp_number' => $data['itp_number'], ':id' => $id]
        );
        
        if ($existing) {
            $error = 'شماره ITP تکراری است.';
        }
    }
    
    if (empty($error)) {
        db()->beginTransaction();
        try {
            if ($action === 'edit' && $id > 0) {
                db()->update('itp', $data, 'id = :id', [':id' => $id]);
                db()->delete('itp_test_points', 'itp_id = :id', [':id' => $id]);
            } else {
                $data['prepared_by'] = $_SESSION['user_id'];
                $id = db()->insert('itp', $data);
            }
            
            // افزودن نقاط آزمون
            if (isset($_POST['test_points']) && is_array($_POST['test_points'])) {
                foreach ($_POST['test_points'] as $index => $point) {
                    if (!empty($point['test_description'])) {
                        $pointData = [
                            'itp_id' => $id,
                            'point_number' => $index + 1,
                            'test_description' => sanitize_input($point['test_description']),
                            'acceptance_criteria' => sanitize_input($point['acceptance_criteria']),
                            'test_method' => sanitize_input($point['test_method']),
                            'inspection_stage' => sanitize_input($point['inspection_stage']),
                            'hold_point' => isset($point['hold_point']) ? 1 : 0,
                            'witness_point' => isset($point['witness_point']) ? 1 : 0,
                            'applicable_standard' => sanitize_input($point['applicable_standard']),
                            'reference_document' => sanitize_input($point['reference_document']),
                            'notes' => sanitize_input($point['notes'])
                        ];
                        
                        db()->insert('itp_test_points', $pointData);
                    }
                }
            }
            
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => $action === 'edit' ? 'update_itp' : 'create_itp',
                'module' => 'qc',
                'record_id' => $id,
                'new_data' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            db()->commit();
            
            if ($action === 'edit') {
                $success = 'ITP با موفقیت ویرایش شد.';
                $itp = db()->selectOne("SELECT * FROM itp WHERE id = :id", [':id' => $id]);
                $testPoints = db()->select("SELECT * FROM itp_test_points WHERE itp_id = :id ORDER BY point_number", [':id' => $id]);
            } else {
                redirect(SITE_URL . '/itp.php?action=edit&id=' . $id . '&msg=created');
            }
        } catch (Exception $e) {
            db()->rollback();
            $error = 'خطا در ذخیره ITP: ' . $e->getMessage();
        }
    }
}

$projects = db()->select("SELECT id, code, title FROM projects ORDER BY code");
$products = db()->select("SELECT id, code, name FROM products ORDER BY code");

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'ITP با موفقیت ایجاد شد.';
    elseif ($_GET['msg'] === 'deleted') $success = 'ITP با موفقیت حذف شد.';
}

$pageTitle = ['add' => 'افزودن ITP جدید', 'edit' => 'ویرایش ITP', 'view' => 'مشاهده ITP'][$action] ?? 'ITP';
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
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
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
        
        .test-points-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .test-points-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .test-point {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        
        .test-point-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .test-point-header h4 {
            color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }
        
        .btn-add-point {
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-remove-point {
            background: #f44336;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            padding: 8px 16px;
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
            <h1>✅ <?php echo $pageTitle; ?></h1>
            <a href="itps.php" class="btn btn-secondary">↶ بازگشت به لیست</a>
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
                        <label>شماره ITP <span class="required">*</span></label>
                        <input type="text" name="itp_number" 
                               value="<?php echo h($itp['itp_number'] ?? 'ITP-' . date('Ymd') . '-'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نسخه</label>
                        <input type="text" name="version" 
                               value="<?php echo h($itp['version'] ?? '1.0'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="draft" <?php echo ($itp['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="active" <?php echo ($itp['status'] ?? '') === 'active' ? 'selected' : ''; ?>>فعال</option>
                            <option value="completed" <?php echo ($itp['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                            <option value="cancelled" <?php echo ($itp['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>عنوان <span class="required">*</span></label>
                        <input type="text" name="title" 
                               value="<?php echo h($itp['title'] ?? ''); ?>" 
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
                                        <?php echo ($itp['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
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
                                        <?php echo ($itp['product_id'] ?? 0) == $product['id'] ? 'selected' : ''; ?>>
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
                               value="<?php echo h($itp['issue_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ بازبینی</label>
                        <input type="text" name="revision_date" class="jalali-date"
                               value="<?php echo h($itp['revision_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($itp['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>یادداشت</label>
                        <textarea name="notes" <?php echo $readonly; ?>><?php echo h($itp['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <?php if ($action !== 'view'): ?>
                <div class="test-points-section">
                    <h3>نقاط آزمون و بازرسی</h3>
                    
                    <div id="test-points-container">
                        <?php if (count($testPoints) > 0): ?>
                            <?php foreach ($testPoints as $index => $point): ?>
                                <div class="test-point">
                                    <div class="test-point-header">
                                        <h4>نقطه آزمون <?php echo en2fa($index + 1); ?></h4>
                                        <button type="button" class="btn-remove-point" onclick="removeTestPoint(this)">✖ حذف</button>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>شرح آزمون <span class="required">*</span></label>
                                            <textarea name="test_points[<?php echo $index; ?>][test_description]" required><?php echo h($point['test_description']); ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>معیار قبولی</label>
                                            <textarea name="test_points[<?php echo $index; ?>][acceptance_criteria]"><?php echo h($point['acceptance_criteria']); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>روش آزمون</label>
                                            <input type="text" name="test_points[<?php echo $index; ?>][test_method]" value="<?php echo h($point['test_method']); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>مرحله بازرسی <span class="required">*</span></label>
                                            <select name="test_points[<?php echo $index; ?>][inspection_stage]" required>
                                                <option value="raw_material" <?php echo $point['inspection_stage'] === 'raw_material' ? 'selected' : ''; ?>>مواد اولیه</option>
                                                <option value="fabrication" <?php echo $point['inspection_stage'] === 'fabrication' ? 'selected' : ''; ?>>ساخت</option>
                                                <option value="assembly" <?php echo $point['inspection_stage'] === 'assembly' ? 'selected' : ''; ?>>مونتاژ</option>
                                                <option value="final" <?php echo $point['inspection_stage'] === 'final' ? 'selected' : ''; ?>>نهایی</option>
                                                <option value="witness" <?php echo $point['inspection_stage'] === 'witness' ? 'selected' : ''; ?>>شاهد</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>استاندارد قابل اعمال</label>
                                            <input type="text" name="test_points[<?php echo $index; ?>][applicable_standard]" value="<?php echo h($point['applicable_standard']); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>مدرک مرجع</label>
                                            <input type="text" name="test_points[<?php echo $index; ?>][reference_document]" value="<?php echo h($point['reference_document']); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <label>
                                            <input type="checkbox" name="test_points[<?php echo $index; ?>][hold_point]" <?php echo $point['hold_point'] ? 'checked' : ''; ?>>
                                            Hold Point (نقطه توقف)
                                        </label>
                                        
                                        <label>
                                            <input type="checkbox" name="test_points[<?php echo $index; ?>][witness_point]" <?php echo $point['witness_point'] ? 'checked' : ''; ?>>
                                            Witness Point (نقطه شاهد)
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="test-point">
                                <div class="test-point-header">
                                    <h4>نقطه آزمون ۱</h4>
                                    <button type="button" class="btn-remove-point" onclick="removeTestPoint(this)">✖ حذف</button>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>شرح آزمون <span class="required">*</span></label>
                                        <textarea name="test_points[0][test_description]" required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>معیار قبولی</label>
                                        <textarea name="test_points[0][acceptance_criteria]"></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>روش آزمون</label>
                                        <input type="text" name="test_points[0][test_method]">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>مرحله بازرسی <span class="required">*</span></label>
                                        <select name="test_points[0][inspection_stage]" required>
                                            <option value="raw_material">مواد اولیه</option>
                                            <option value="fabrication">ساخت</option>
                                            <option value="assembly">مونتاژ</option>
                                            <option value="final" selected>نهایی</option>
                                            <option value="witness">شاهد</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>استاندارد قابل اعمال</label>
                                        <input type="text" name="test_points[0][applicable_standard]">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>مدرک مرجع</label>
                                        <input type="text" name="test_points[0][reference_document]">
                                    </div>
                                </div>
                                
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="test_points[0][hold_point]">
                                        Hold Point (نقطه توقف)
                                    </label>
                                    
                                    <label>
                                        <input type="checkbox" name="test_points[0][witness_point]">
                                        Witness Point (نقطه شاهد)
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-add-point" onclick="addTestPoint()">➕ افزودن نقطه آزمون</button>
                </div>
                
                <div class="form-container">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? '💾 ذخیره تغییرات' : '➕ افزودن ITP'; ?>
                        </button>
                        
                        <?php if ($action === 'edit' && check_permission('qc', PERMISSION_FULL)): ?>
                            <a href="itp.php?action=delete&id=<?php echo $id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('آیا از حذف این ITP اطمینان دارید؟')">
                                🗑️ حذف
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="test-points-section">
                    <h3>نقاط آزمون و بازرسی</h3>
                    <?php foreach ($testPoints as $point): ?>
                        <div class="test-point">
                            <h4>نقطه آزمون <?php echo en2fa($point['point_number']); ?></h4>
                            <p><strong>شرح:</strong> <?php echo h($point['test_description']); ?></p>
                            <p><strong>معیار قبولی:</strong> <?php echo h($point['acceptance_criteria']); ?></p>
                            <p><strong>روش آزمون:</strong> <?php echo h($point['test_method']); ?></p>
                            <p><strong>مرحله:</strong> <?php echo h($point['inspection_stage']); ?></p>
                            <?php if ($point['hold_point']): ?>
                                <span style="background: #f44336; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Hold Point</span>
                            <?php endif; ?>
                            <?php if ($point['witness_point']): ?>
                                <span style="background: #ff9800; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Witness Point</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <script>
        initJalaliDatePickers();
        
        let pointCounter = <?php echo count($testPoints); ?>;
        
        function addTestPoint() {
            const container = document.getElementById('test-points-container');
            const newPoint = document.createElement('div');
            newPoint.className = 'test-point';
            newPoint.innerHTML = `
                <div class="test-point-header">
                    <h4>نقطه آزمون ${toPersianNumber(pointCounter + 1)}</h4>
                    <button type="button" class="btn-remove-point" onclick="removeTestPoint(this)">✖ حذف</button>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>شرح آزمون <span class="required">*</span></label>
                        <textarea name="test_points[${pointCounter}][test_description]" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>معیار قبولی</label>
                        <textarea name="test_points[${pointCounter}][acceptance_criteria]"></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>روش آزمون</label>
                        <input type="text" name="test_points[${pointCounter}][test_method]">
                    </div>
                    
                    <div class="form-group">
                        <label>مرحله بازرسی <span class="required">*</span></label>
                        <select name="test_points[${pointCounter}][inspection_stage]" required>
                            <option value="raw_material">مواد اولیه</option>
                            <option value="fabrication">ساخت</option>
                            <option value="assembly">مونتاژ</option>
                            <option value="final" selected>نهایی</option>
                            <option value="witness">شاهد</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>استاندارد قابل اعمال</label>
                        <input type="text" name="test_points[${pointCounter}][applicable_standard]">
                    </div>
                    
                    <div class="form-group">
                        <label>مدرک مرجع</label>
                        <input type="text" name="test_points[${pointCounter}][reference_document]">
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="test_points[${pointCounter}][hold_point]">
                        Hold Point (نقطه توقف)
                    </label>
                    
                    <label>
                        <input type="checkbox" name="test_points[${pointCounter}][witness_point]">
                        Witness Point (نقطه شاهد)
                    </label>
                </div>
            `;
            container.appendChild(newPoint);
            pointCounter++;
        }
        
        function removeTestPoint(btn) {
            const points = document.querySelectorAll('.test-point');
            if (points.length > 1) {
                btn.closest('.test-point').remove();
                updatePointNumbers();
            } else {
                alert('حداقل یک نقطه آزمون باید وجود داشته باشد.');
            }
        }
        
        function updatePointNumbers() {
            const points = document.querySelectorAll('.test-point');
            points.forEach((point, index) => {
                point.querySelector('h4').textContent = `نقطه آزمون ${toPersianNumber(index + 1)}`;
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