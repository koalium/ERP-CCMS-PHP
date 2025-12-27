<?php
/**
 * فرم افزودن/ویرایش مناقصه
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('marketing', PERMISSION_READ)) {
    die('شما مجوز دسترسی ندارید.');
}

$action = $_GET['action'] ?? 'add';
$tenderId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$error = '';
$tender = null;

// بارگذاری مناقصه
if (in_array($action, ['edit', 'view', 'delete']) && $tenderId) {
    $tender = db()->selectOne("SELECT * FROM tenders WHERE id = :id", [':id' => $tenderId]);
    if (!$tender) {
        die('مناقصه یافت نشد.');
    }
}

// حذف مناقصه
if ($action === 'delete' && $tender && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_permission('marketing', PERMISSION_FULL)) {
        die('شما مجوز حذف ندارید.');
    }
    
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        db()->delete('tenders', 'id = :id', [':id' => $tenderId]);
        redirect(SITE_URL . '/tenders.php?msg=deleted');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!check_permission('marketing', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش ندارید.');
    }
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی';
    } else {
        // آپلود فایل‌ها
        $attachments = [];
        if (isset($_FILES['attachments']) && $_FILES['attachments']['error'][0] != UPLOAD_ERR_NO_FILE) {
            $uploadDir = UPLOAD_DIR . '/tenders/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['attachments']['name'] as $key => $filename) {
                if ($_FILES['attachments']['error'][$key] == UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (in_array($ext, ALLOWED_EXTENSIONS)) {
                        $newName = uniqid() . '_' . $filename;
                        $destination = $uploadDir . $newName;
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $destination)) {
                            $attachments[] = [
                                'name' => $filename,
                                'path' => 'tenders/' . $newName,
                                'size' => $_FILES['attachments']['size'][$key]
                            ];
                        }
                    }
                }
            }
        }
        
        $data = [
            'tender_number' => sanitize_input($_POST['tender_number'] ?? ''),
            'title' => sanitize_input($_POST['title'] ?? ''),
            'client' => sanitize_input($_POST['client'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'status' => sanitize_input($_POST['status'] ?? 'identified'),
            'deadline_date' => sanitize_input($_POST['deadline_date'] ?? ''),
            'submission_date' => sanitize_input($_POST['submission_date'] ?? ''),
            'opening_date' => sanitize_input($_POST['opening_date'] ?? ''),
            'estimated_value' => (float)($_POST['estimated_value'] ?? 0),
            'currency' => sanitize_input($_POST['currency'] ?? 'IRR'),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'location' => sanitize_input($_POST['location'] ?? ''),
            'notes' => sanitize_input($_POST['notes'] ?? '')
        ];
        
        if ($attachments) {
            $existingAttachments = $action === 'edit' && $tender['attachments'] 
                ? json_decode($tender['attachments'], true) : [];
            $allAttachments = array_merge($existingAttachments, $attachments);
            $data['attachments'] = json_encode($allAttachments);
        }
        
        if (empty($data['tender_number']) || empty($data['title'])) {
            $error = 'شماره و عنوان مناقصه الزامی است';
        } else {
            if ($action === 'add') {
                $data['created_by'] = $userId;
                $newId = db()->insert('tenders', $data);
                
                if ($newId) {
                    db()->insert('logs', [
                        'user_id' => $userId,
                        'action' => 'create_tender',
                        'module' => 'marketing',
                        'record_id' => $newId,
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    redirect(SITE_URL . '/tenders.php?msg=added');
                } else {
                    $error = 'خطا در ذخیره';
                }
            } elseif ($action === 'edit') {
                db()->update('tenders', $data, 'id = :id', [':id' => $tenderId]);
                
                db()->insert('logs', [
                    'user_id' => $userId,
                    'action' => 'update_tender',
                    'module' => 'marketing',
                    'record_id' => $tenderId,
                    'old_data' => json_encode($tender),
                    'new_data' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                redirect(SITE_URL . '/tenders.php?msg=updated');
            }
        }
    }
}

$pageTitle = $action === 'add' ? 'ثبت مناقصه جدید' : 
             ($action === 'edit' ? 'ویرایش مناقصه' : 
             ($action === 'delete' ? 'حذف مناقصه' : 'مشاهده مناقصه'));
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 52%, #2BFF88 90%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 4px solid #f0f0f0;
        }
        
        .form-header h1 {
            color: #2c3e50;
            font-size: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-back {
            padding: 12px 24px;
            background: #f5f5f5;
            color: #333;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .btn-back:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 18px 24px;
            border-radius: 15px;
            margin-bottom: 30px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 2px solid #fcc;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 10px;
            color: #333;
            font-weight: bold;
            font-size: 15px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FA8BFF;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .file-upload-label:hover {
            border-color: #FA8BFF;
            background: #fff;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 30px;
            border-top: 4px solid #f0f0f0;
        }
        
        .btn {
            padding: 16px 35px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(250, 139, 255, 0.5);
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .view-mode {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 18px;
        }
        
        .view-mode strong {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .view-mode p {
            color: #333;
            font-size: 16px;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 30px 20px;
            }
            
            .form-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>
                    <?php 
                    if ($action === 'add') echo '📋 ثبت مناقصه جدید';
                    elseif ($action === 'edit') echo '✏️ ویرایش مناقصه';
                    elseif ($action === 'delete') echo '🗑️ حذف مناقصه';
                    else echo '👁 مشاهده مناقصه';
                    ?>
                </h1>
                <a href="tenders.php" class="btn-back">↩️ بازگشت</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($action === 'delete'): ?>
                <div class="alert alert-error">
                    ⚠️ آیا از حذف این مناقصه اطمینان دارید؟ این عمل قابل بازگشت نیست!
                </div>
                
                <div class="view-mode">
                    <strong>شماره مناقصه</strong>
                    <p><?php echo h($tender['tender_number']); ?></p>
                </div>
                
                <div class="view-mode">
                    <strong>عنوان</strong>
                    <p><?php echo h($tender['title']); ?></p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">🗑️ تایید حذف</button>
                        <a href="tenders.php" class="btn btn-secondary">انصراف</a>
                    </div>
                </form>
                
            <?php elseif ($action === 'view'): ?>
                <div class="form-section">
                    <div class="section-title">📋 اطلاعات اصلی</div>
                    
                    <div class="form-grid">
                        <div class="view-mode">
                            <strong>شماره مناقصه</strong>
                            <p><?php echo h($tender['tender_number']); ?></p>
                        </div>
                        
                        <div class="view-mode">
                            <strong>وضعیت</strong>
                            <p><?php 
                                $statuses = [
                                    'identified' => '🔍 شناسایی شده',
                                    'reviewing' => '📖 در حال بررسی',
                                    'proposal_sent' => '📤 ارسال پیشنهاد',
                                    'won' => '✅ برنده شده',
                                    'lost' => '❌ از دست رفته',
                                    'cancelled' => '⛔ لغو شده'
                                ];
                                echo $statuses[$tender['status']] ?? $tender['status'];
                            ?></p>
                        </div>
                        
                        <div class="view-mode full-width">
                            <strong>عنوان</strong>
                            <p><?php echo h($tender['title']); ?></p>
                        </div>
                        
                        <div class="view-mode">
                            <strong>کارفرما</strong>
                            <p><?php echo h($tender['client'] ?: '-'); ?></p>
                        </div>
                        
                        <div class="view-mode">
                            <strong>دسته‌بندی</strong>
                            <p><?php echo h($tender['category'] ?: '-'); ?></p>
                        </div>
                    </div>
                </div>
                
                <?php if ($tender['description']): ?>
                    <div class="form-section">
                        <div class="section-title">📝 شرح مناقصه</div>
                        <div class="view-mode">
                            <p><?php echo nl2br(h($tender['description'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <div class="section-title">📅 تاریخ‌های مهم</div>
                    
                    <div class="form-grid">
                        <div class="view-mode">
                            <strong>مهلت ارسال</strong>
                            <p><?php echo h($tender['deadline_date'] ?: '-'); ?></p>
                        </div>
                        
                        <div class="view-mode">
                            <strong>تاریخ تحویل</strong>
                            <p><?php echo h($tender['submission_date'] ?: '-'); ?></p>
                        </div>
                        
                        <div class="view-mode">
                            <strong>تاریخ بازگشایی</strong>
                            <p><?php echo h($tender['opening_date'] ?: '-'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">💰 اطلاعات مالی</div>
                    
                    <div class="form-grid">
                        <div class="view-mode">
                            <strong>ارزش تخمینی</strong>
                            <p><?php echo $tender['estimated_value'] ? number_format($tender['estimated_value']) : '-'; ?></p>
                        </div>
                        
                        <div class="view-mode">
                            <strong>واحد پول</strong>
                            <p><?php echo h($tender['currency']); ?></p>
                        </div>
                        
                        <div class="view-mode full-width">
                            <strong>محل اجرا</strong>
                            <p><?php echo h($tender['location'] ?: '-'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="tender.php?action=edit&id=<?php echo $tender['id']; ?>" class="btn btn-primary">
                        ✏️ ویرایش
                    </a>
                    <a href="proposals.php?tender_id=<?php echo $tender['id']; ?>" class="btn btn-primary">
                        📝 مشاهده پیشنهادات
                    </a>
                    <a href="tenders.php" class="btn btn-secondary">بازگشت</a>
                </div>
                
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-section">
                        <div class="section-title">📋 اطلاعات اصلی</div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>شماره مناقصه *</label>
                                <input type="text" name="tender_number" required 
                                       value="<?php echo h($tender['tender_number'] ?? ''); ?>"
                                       placeholder="TND-001">
                            </div>
                            
                            <div class="form-group">
                                <label>وضعیت</label>
                                <select name="status">
                                    <option value="identified" <?php echo ($tender['status'] ?? 'identified') === 'identified' ? 'selected' : ''; ?>>🔍 شناسایی شده</option>
                                    <option value="reviewing" <?php echo ($tender['status'] ?? '') === 'reviewing' ? 'selected' : ''; ?>>📖 در حال بررسی</option>
                                    <option value="proposal_sent" <?php echo ($tender['status'] ?? '') === 'proposal_sent' ? 'selected' : ''; ?>>📤 ارسال پیشنهاد</option>
                                    <option value="won" <?php echo ($tender['status'] ?? '') === 'won' ? 'selected' : ''; ?>>✅ برنده شده</option>
                                    <option value="lost" <?php echo ($tender['status'] ?? '') === 'lost' ? 'selected' : ''; ?>>❌ از دست رفته</option>
                                    <option value="cancelled" <?php echo ($tender['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>⛔ لغو شده</option>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>عنوان مناقصه *</label>
                                <input type="text" name="title" required 
                                       value="<?php echo h($tender['title'] ?? ''); ?>"
                                       placeholder="عنوان کامل مناقصه">
                            </div>
                            
                            <div class="form-group">
                                <label>کارفرما</label>
                                <input type="text" name="client" 
                                       value="<?php echo h($tender['client'] ?? ''); ?>"
                                       placeholder="نام کارفرما">
                            </div>
                            
                            <div class="form-group">
                                <label>دسته‌بندی</label>
                                <input type="text" name="category" 
                                       value="<?php echo h($tender['category'] ?? ''); ?>"
                                       placeholder="ساختمانی، صنعتی، ...">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>شرح مناقصه</label>
                                <textarea name="description" rows="4" 
                                          placeholder="توضیحات کامل مناقصه..."><?php echo h($tender['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">📅 تاریخ‌های مهم</div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>مهلت ارسال پیشنهاد</label>
                                <input type="text" name="deadline_date" class="jalali-date"
                                       value="<?php echo h($tender['deadline_date'] ?? ''); ?>"
                                       placeholder="۱۴۰۴/۰۱/۰۱">
                            </div>
                            
                            <div class="form-group">
                                <label>تاریخ تحویل اسناد</label>
                                <input type="text" name="submission_date" class="jalali-date"
                                       value="<?php echo h($tender['submission_date'] ?? ''); ?>"
                                       placeholder="۱۴۰۴/۰۱/۰۱">
                            </div>
                            
                            <div class="form-group">
                                <label>تاریخ بازگشایی</label>
                                <input type="text" name="opening_date" class="jalali-date"
                                       value="<?php echo h($tender['opening_date'] ?? ''); ?>"
                                       placeholder="۱۴۰۴/۰۱/۰۱">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">💰 اطلاعات مالی</div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>ارزش تخمینی</label>
                                <input type="number" name="estimated_value" step="0.01"
                                       value="<?php echo h($tender['estimated_value'] ?? ''); ?>"
                                       placeholder="0">
                            </div>
                            
                            <div class="form-group">
                                <label>واحد پول</label>
                                <select name="currency">
                                    <option value="IRR" <?php echo ($tender['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال</option>
                                    <option value="USD" <?php echo ($tender['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار</option>
                                    <option value="EUR" <?php echo ($tender['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو</option>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>محل اجرا</label>
                                <input type="text" name="location" 
                                       value="<?php echo h($tender['location'] ?? ''); ?>"
                                       placeholder="آدرس یا محل اجرای پروژه">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">📎 پیوست‌ها و یادداشت‌ها</div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>آپلود فایل‌ها</label>
                                <div class="file-upload">
                                    <input type="file" name="attachments[]" id="attachments" multiple>
                                    <label for="attachments" class="file-upload-label">
                                        📎 انتخاب فایل‌ها
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>یادداشت‌ها</label>
                                <textarea name="notes" rows="3" 
                                          placeholder="یادداشت‌های داخلی..."><?php echo h($tender['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? '➕ ثبت مناقصه' : '💾 به‌روزرسانی'; ?>
                        </button>
                        <a href="tenders.php" class="btn btn-secondary">انصراف</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initJalaliDatePicker('.jalali-date');
            
            // نمایش نام فایل‌های انتخاب شده
            const fileInput = document.getElementById('attachments');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const label = document.querySelector('.file-upload-label');
                    if (this.files.length > 0) {
                        label.textContent = `📎 ${this.files.length} فایل انتخاب شد`;
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>