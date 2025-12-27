<?php
/**
 * فرم نقشه فنی - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$drawing = null;
$readonly = '';

if ($action === 'view') {
    if (!check_permission('engineering', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
    $readonly = 'readonly';
} elseif ($action === 'delete') {
    if (!check_permission('engineering', PERMISSION_FULL)) {
        die('شما مجوز حذف نقشه را ندارید.');
    }
} else {
    if (!check_permission('engineering', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش نقشه را ندارید.');
    }
}

if ($action === 'delete' && $id > 0) {
    $drawing = db()->selectOne("SELECT file_path FROM drawings WHERE id = :id", [':id' => $id]);
    
    if (db()->delete('drawings', 'id = :id', [':id' => $id])) {
        // حذف فایل
        if ($drawing && $drawing['file_path'] && file_exists($drawing['file_path'])) {
            unlink($drawing['file_path']);
        }
        
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'delete_drawing',
            'module' => 'engineering',
            'record_id' => $id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        redirect(SITE_URL . '/drawings.php?msg=deleted');
    } else {
        $error = 'خطا در حذف نقشه.';
    }
    $action = 'view';
}

if (($action === 'edit' || $action === 'view') && $id > 0) {
    $drawing = db()->selectOne("SELECT * FROM drawings WHERE id = :id", [':id' => $id]);
    if (!$drawing) {
        die('نقشه یافت نشد.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('توکن امنیتی نامعتبر است.');
    }
    
    $data = [
        'drawing_number' => sanitize_input($_POST['drawing_number']),
        'title' => sanitize_input($_POST['title']),
        'description' => sanitize_input($_POST['description']),
        'product_id' => !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null,
        'part_id' => !empty($_POST['part_id']) ? (int)$_POST['part_id'] : null,
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'drawing_type' => sanitize_input($_POST['drawing_type']),
        'version' => sanitize_input($_POST['version']),
        'revision' => (int)$_POST['revision'],
        'scale' => sanitize_input($_POST['scale']),
        'sheet_size' => sanitize_input($_POST['sheet_size']),
        'checked_by' => !empty($_POST['checked_by']) ? (int)$_POST['checked_by'] : null,
        'approved_by' => !empty($_POST['approved_by']) ? (int)$_POST['approved_by'] : null,
        'issue_date' => !empty($_POST['issue_date']) ? $_POST['issue_date'] : null,
        'revision_date' => !empty($_POST['revision_date']) ? $_POST['revision_date'] : null,
        'status' => sanitize_input($_POST['status']),
        'notes' => sanitize_input($_POST['notes']),
        'tags' => sanitize_input($_POST['tags'])
    ];
    
    if (empty($data['drawing_number'])) {
        $error = 'شماره نقشه الزامی است.';
    } elseif (empty($data['title'])) {
        $error = 'عنوان الزامی است.';
    } else {
        $existing = db()->selectOne(
            "SELECT id FROM drawings WHERE drawing_number = :drawing_number AND id != :id",
            [':drawing_number' => $data['drawing_number'], ':id' => $id]
        );
        
        if ($existing) {
            $error = 'شماره نقشه تکراری است.';
        }
    }
    
    // آپلود فایل
    $filePath = $drawing['file_path'] ?? null;
    if (isset($_FILES['drawing_file']) && $_FILES['drawing_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = UPLOAD_DIR . '/drawings/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['drawing_file']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'dwg', 'dxf', 'png', 'jpg', 'jpeg', 'gif'];
        
        if (!in_array($fileExt, $allowedExts)) {
            $error = 'فرمت فایل مجاز نیست. فرمت‌های مجاز: ' . implode(', ', $allowedExts);
        } elseif ($_FILES['drawing_file']['size'] > MAX_FILE_SIZE) {
            $error = 'حجم فایل بیش از حد مجاز است.';
        } else {
            $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['drawing_file']['name']);
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($_FILES['drawing_file']['tmp_name'], $filePath)) {
                $error = 'خطا در آپلود فایل.';
                $filePath = $drawing['file_path'] ?? null;
            } else {
                $data['file_path'] = $filePath;
                $data['file_size'] = $_FILES['drawing_file']['size'];
                $data['file_type'] = $_FILES['drawing_file']['type'];
            }
        }
    }
    
    if (empty($error)) {
        if ($action === 'edit' && $id > 0) {
            if (db()->update('drawings', $data, 'id = :id', [':id' => $id])) {
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'update_drawing',
                    'module' => 'engineering',
                    'record_id' => $id,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $success = 'نقشه با موفقیت ویرایش شد.';
                $drawing = db()->selectOne("SELECT * FROM drawings WHERE id = :id", [':id' => $id]);
            } else {
                $error = 'خطا در ویرایش نقشه.';
            }
        } else {
            $data['drawn_by'] = $_SESSION['user_id'];
            
            $newId = db()->insert('drawings', $data);
            if ($newId) {
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'create_drawing',
                    'module' => 'engineering',
                    'record_id' => $newId,
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                redirect(SITE_URL . '/drawing.php?action=edit&id=' . $newId . '&msg=created');
            } else {
                $error = 'خطا در افزودن نقشه.';
            }
        }
    }
}

$projects = db()->select("SELECT id, code, title FROM projects ORDER BY code");
$products = db()->select("SELECT id, code, name FROM products ORDER BY code");
$parts = db()->select("SELECT id, part_number, name FROM parts WHERE status = 'active' ORDER BY part_number");
$users = db()->select("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname");

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'نقشه با موفقیت ایجاد شد.';
    elseif ($_GET['msg'] === 'deleted') $success = 'نقشه با موفقیت حذف شد.';
}

$pageTitle = ['add' => 'افزودن نقشه جدید', 'edit' => 'ویرایش نقشه', 'view' => 'مشاهده نقشه'][$action] ?? 'نقشه';
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
        
        .form-group textarea { min-height: 80px; resize: vertical; }
        
        .file-upload {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9ff;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover { background: #e3e7ff; }
        
        .file-upload input[type="file"] { display: none; }
        
        .current-file {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
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
            <h1>📝 <?php echo $pageTitle; ?></h1>
            <a href="drawings.php" class="btn btn-secondary">↶ بازگشت به لیست</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>شماره نقشه <span class="required">*</span></label>
                        <input type="text" name="drawing_number" 
                               value="<?php echo h($drawing['drawing_number'] ?? 'DWG-' . date('Ymd') . '-'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نوع نقشه <span class="required">*</span></label>
                        <select name="drawing_type" required <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="assembly" <?php echo ($drawing['drawing_type'] ?? '') === 'assembly' ? 'selected' : ''; ?>>مونتاژ</option>
                            <option value="detail" <?php echo ($drawing['drawing_type'] ?? '') === 'detail' ? 'selected' : ''; ?>>جزئیات</option>
                            <option value="schematic" <?php echo ($drawing['drawing_type'] ?? '') === 'schematic' ? 'selected' : ''; ?>>شماتیک</option>
                            <option value="layout" <?php echo ($drawing['drawing_type'] ?? '') === 'layout' ? 'selected' : ''; ?>>چیدمان</option>
                            <option value="fabrication" <?php echo ($drawing['drawing_type'] ?? '') === 'fabrication' ? 'selected' : ''; ?>>ساخت</option>
                            <option value="isometric" <?php echo ($drawing['drawing_type'] ?? '') === 'isometric' ? 'selected' : ''; ?>>ایزومتریک</option>
                            <option value="other" <?php echo ($drawing['drawing_type'] ?? '') === 'other' ? 'selected' : ''; ?>>سایر</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>عنوان <span class="required">*</span></label>
                        <input type="text" name="title" 
                               value="<?php echo h($drawing['title'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>محصول</label>
                        <select name="product_id" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"
                                        <?php echo ($drawing['product_id'] ?? 0) == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($product['code'] . ' - ' . $product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>قطعه</label>
                        <select name="part_id" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($parts as $part): ?>
                                <option value="<?php echo $part['id']; ?>"
                                        <?php echo ($drawing['part_id'] ?? 0) == $part['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($part['part_number'] . ' - ' . $part['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>پروژه</label>
                        <select name="project_id" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"
                                        <?php echo ($drawing['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code'] . ' - ' . $project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="draft" <?php echo ($drawing['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="review" <?php echo ($drawing['status'] ?? '') === 'review' ? 'selected' : ''; ?>>بررسی</option>
                            <option value="approved" <?php echo ($drawing['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                            <option value="released" <?php echo ($drawing['status'] ?? '') === 'released' ? 'selected' : ''; ?>>منتشر شده</option>
                            <option value="obsolete" <?php echo ($drawing['status'] ?? '') === 'obsolete' ? 'selected' : ''; ?>>منسوخ</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>نسخه</label>
                        <input type="text" name="version" 
                               value="<?php echo h($drawing['version'] ?? 'A'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>Revision</label>
                        <input type="number" name="revision" 
                               value="<?php echo h($drawing['revision'] ?? '0'); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>مقیاس</label>
                        <input type="text" name="scale" 
                               value="<?php echo h($drawing['scale'] ?? '1:1'); ?>" 
                               placeholder="1:1, 1:2, 1:10..."
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>سایز برگ</label>
                        <select name="sheet_size" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <option value="A0" <?php echo ($drawing['sheet_size'] ?? '') === 'A0' ? 'selected' : ''; ?>>A0</option>
                            <option value="A1" <?php echo ($drawing['sheet_size'] ?? '') === 'A1' ? 'selected' : ''; ?>>A1</option>
                            <option value="A2" <?php echo ($drawing['sheet_size'] ?? '') === 'A2' ? 'selected' : ''; ?>>A2</option>
                            <option value="A3" <?php echo ($drawing['sheet_size'] ?? '') === 'A3' ? 'selected' : ''; ?>>A3</option>
                            <option value="A4" <?php echo ($drawing['sheet_size'] ?? 'A4') === 'A4' ? 'selected' : ''; ?>>A4</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>بررسی‌کننده</label>
                        <select name="checked_by" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"
                                        <?php echo ($drawing['checked_by'] ?? 0) == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($user['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تایید‌کننده</label>
                        <select name="approved_by" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"
                                        <?php echo ($drawing['approved_by'] ?? 0) == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($user['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تاریخ صدور</label>
                        <input type="text" name="issue_date" class="jalali-date"
                               value="<?php echo h($drawing['issue_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ بازبینی</label>
                        <input type="text" name="revision_date" class="jalali-date"
                               value="<?php echo h($drawing['revision_date'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($drawing['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>برچسب‌ها</label>
                        <input type="text" name="tags" 
                               value="<?php echo h($drawing['tags'] ?? ''); ?>" 
                               placeholder="با کاما جدا کنید"
                               <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>فایل نقشه</label>
                            
                            <?php if ($drawing && $drawing['file_path']): ?>
                                <div class="current-file">
                                    <p><strong>فایل فعلی:</strong> <?php echo basename($drawing['file_path']); ?></p>
                                    <p><small>حجم: <?php echo number_format($drawing['file_size'] / 1024, 2); ?> KB</small></p>
                                    <a href="<?php echo h($drawing['file_path']); ?>" target="_blank" class="btn btn-secondary" download>دانلود فایل فعلی</a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="file-upload" onclick="document.getElementById('drawing_file').click()">
                                <p>📎 کلیک کنید یا فایل را اینجا بکشید</p>
                                <p><small>فرمت‌های مجاز: PDF, DWG, DXF, PNG, JPG</small></p>
                                <input type="file" id="drawing_file" name="drawing_file" 
                                       accept=".pdf,.dwg,.dxf,.png,.jpg,.jpeg,.gif"
                                       onchange="updateFileName(this)">
                            </div>
                            <p id="file-name" style="margin-top: 10px; color: #667eea;"></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>یادداشت</label>
                        <textarea name="notes" <?php echo $readonly; ?>><?php echo h($drawing['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <?php if ($action !== 'view'): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? '💾 ذخیره تغییرات' : '➕ افزودن نقشه'; ?>
                        </button>
                        
                        <?php if ($action === 'edit' && check_permission('engineering', PERMISSION_FULL)): ?>
                            <a href="drawing.php?action=delete&id=<?php echo $id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('آیا از حذف این نقشه اطمینان دارید؟')">
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
        
        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            document.getElementById('file-name').textContent = fileName ? 'فایل انتخاب شده: ' + fileName : '';
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>