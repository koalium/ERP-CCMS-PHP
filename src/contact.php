<?php
/**
 * فرم مخاطب - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$contactId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$contact = null;

// چک مجوز
$canWrite = check_permission('contacts', PERMISSION_WRITE);
$canDelete = check_permission('contacts', PERMISSION_FULL);

if ($action === 'view' || $action === 'edit' || $action === 'delete') {
    if (!$contactId) {
        redirect(SITE_URL . '/contacts.php');
    }
    
    $contact = db()->selectOne("SELECT * FROM contacts WHERE id = :id", [':id' => $contactId]);
    
    if (!$contact) {
        redirect(SITE_URL . '/contacts.php');
    }
    
    // دریافت جزئیات تماس
    $details = db()->select(
        "SELECT * FROM contact_details WHERE contact_id = :id ORDER BY type, id",
        [':id' => $contactId]
    );
    
    $contact['details'] = [
        'email' => [],
        'phone' => [],
        'mobile' => [],
        'fax' => [],
        'address' => [],
        'website' => [],
        'social' => []
    ];
    
    foreach ($details as $detail) {
        $contact['details'][$detail['type']][] = $detail;
    }
}

// حذف مخاطب
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $canDelete) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        // نرم‌افزاری حذف می‌کنیم (غیرفعال)
        $result = db()->update('contacts', ['is_active' => 0], 'id = :id', [':id' => $contactId]);
        
        if ($result !== false) {
            // ثبت لاگ
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'delete_contact',
                'module' => 'contacts',
                'record_id' => $contactId,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            redirect(SITE_URL . '/contacts.php?msg=deleted');
        } else {
            $error = 'خطا در حذف مخاطب';
        }
    }
}

// ذخیره مخاطب
if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($action === 'add' || $action === 'edit') && $canWrite) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $data = [
            'type' => sanitize_input($_POST['type']),
            'name' => sanitize_input($_POST['name']),
            'company_name' => sanitize_input($_POST['company_name'] ?? ''),
            'national_id' => sanitize_input($_POST['national_id'] ?? ''),
            'registration_number' => sanitize_input($_POST['registration_number'] ?? ''),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'is_customer' => isset($_POST['is_customer']) ? 1 : 0,
            'is_vendor' => isset($_POST['is_vendor']) ? 1 : 0,
            'is_employee' => isset($_POST['is_employee']) ? 1 : 0,
            'notes' => sanitize_input($_POST['notes'] ?? ''),
            'tags' => sanitize_input($_POST['tags'] ?? '')
        ];
        
        // اعتبارسنجی
        if (empty($data['type']) || empty($data['name'])) {
            $error = 'نوع و نام مخاطب الزامی است.';
        } else {
            db()->beginTransaction();
            
            try {
                if ($action === 'add') {
                    $data['created_by'] = $_SESSION['user_id'];
                    $contactId = db()->insert('contacts', $data);
                    $logAction = 'add_contact';
                } else {
                    db()->update('contacts', $data, 'id = :id', [':id' => $contactId]);
                    $logAction = 'edit_contact';
                }
                
                // حذف جزئیات قبلی
                if ($action === 'edit') {
                    db()->delete('contact_details', 'contact_id = :id', [':id' => $contactId]);
                }
                
                // ذخیره جزئیات تماس
                $detailTypes = ['email', 'phone', 'mobile', 'fax', 'address', 'website', 'social'];
                
                foreach ($detailTypes as $type) {
                    if (!empty($_POST['details'][$type])) {
                        foreach ($_POST['details'][$type] as $idx => $value) {
                            $value = sanitize_input($value);
                            if (!empty($value)) {
                                db()->insert('contact_details', [
                                    'contact_id' => $contactId,
                                    'type' => $type,
                                    'label' => sanitize_input($_POST['details_label'][$type][$idx] ?? ''),
                                    'value' => $value,
                                    'is_primary' => isset($_POST['details_primary'][$type]) && $_POST['details_primary'][$type] == $idx ? 1 : 0
                                ]);
                            }
                        }
                    }
                }
                
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => $logAction,
                    'module' => 'contacts',
                    'record_id' => $contactId,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                db()->commit();
                
                redirect(SITE_URL . '/contacts.php?msg=saved');
            } catch (Exception $e) {
                db()->rollback();
                $error = 'خطا در ذخیره اطلاعات: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        echo $action === 'add' ? 'افزودن مخاطب' : 
             ($action === 'edit' ? 'ویرایش مخاطب' : 'مشاهده مخاطب');
    ?> - <?php echo SITE_TITLE; ?></title>
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
            flex-wrap: wrap;
            gap: 15px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
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
            padding: 12px 15px;
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
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input {
            width: auto;
            margin: 0;
        }
        
        .detail-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .detail-item {
            display: grid;
            grid-template-columns: 150px 1fr 100px 50px;
            gap: 10px;
            align-items: center;
        }
        
        .btn-add-detail {
            padding: 8px 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            width: fit-content;
            margin-top: 10px;
        }
        
        .btn-remove-detail {
            padding: 6px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
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
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .view-mode .form-group input,
        .view-mode .form-group select,
        .view-mode .form-group textarea {
            background: #f8f9fa;
            border-color: #dee2e6;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .detail-item {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <?php 
                    echo $action === 'add' ? '➕ افزودن مخاطب جدید' : 
                         ($action === 'edit' ? '✏️ ویرایش مخاطب' : '👁️ مشاهده مخاطب');
                ?>
            </h1>
            <a href="contacts.php" class="btn-back">⬅ بازگشت به لیست</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <div class="form-container <?php echo $action === 'view' ? 'view-mode' : ''; ?>">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <!-- اطلاعات پایه -->
                <div class="form-section">
                    <h2>اطلاعات پایه</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>نوع مخاطب *</label>
                            <select name="type" required <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">انتخاب کنید</option>
                                <option value="person" <?php echo ($contact['type'] ?? '') === 'person' ? 'selected' : ''; ?>>شخص</option>
                                <option value="company" <?php echo ($contact['type'] ?? '') === 'company' ? 'selected' : ''; ?>>شرکت</option>
                                <option value="organization" <?php echo ($contact['type'] ?? '') === 'organization' ? 'selected' : ''; ?>>سازمان</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>نام *</label>
                            <input type="text" name="name" value="<?php echo h($contact['name'] ?? ''); ?>" 
                                   required <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>نام شرکت</label>
                            <input type="text" name="company_name" value="<?php echo h($contact['company_name'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>کد ملی / شناسه ملی</label>
                            <input type="text" name="national_id" value="<?php echo h($contact['national_id'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>شماره ثبت</label>
                            <input type="text" name="registration_number" value="<?php echo h($contact['registration_number'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>دسته‌بندی</label>
                            <input type="text" name="category" value="<?php echo h($contact['category'] ?? ''); ?>"
                                   placeholder="مثال: تامین‌کننده فلزات"
                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>نقش‌ها</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="is_customer" name="is_customer" value="1"
                                       <?php echo ($contact['is_customer'] ?? 0) ? 'checked' : ''; ?>
                                       <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <label for="is_customer" style="font-weight: normal; margin: 0;">مشتری</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="is_vendor" name="is_vendor" value="1"
                                       <?php echo ($contact['is_vendor'] ?? 0) ? 'checked' : ''; ?>
                                       <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <label for="is_vendor" style="font-weight: normal; margin: 0;">تامین‌کننده</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="is_employee" name="is_employee" value="1"
                                       <?php echo ($contact['is_employee'] ?? 0) ? 'checked' : ''; ?>
                                       <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <label for="is_employee" style="font-weight: normal; margin: 0;">کارمند</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- اطلاعات تماس -->
                <div class="form-section">
                    <h2>اطلاعات تماس</h2>
                    
                    <?php 
                    $detailTypes = [
                        'mobile' => ['label' => '📱 موبایل', 'placeholder' => '09123456789'],
                        'phone' => ['label' => '☎️ تلفن', 'placeholder' => '02112345678'],
                        'email' => ['label' => '📧 ایمیل', 'placeholder' => 'info@example.com'],
                        'fax' => ['label' => '📠 فکس', 'placeholder' => '02112345678'],
                        'address' => ['label' => '📍 آدرس', 'placeholder' => 'تهران، ...'],
                        'website' => ['label' => '🌐 وب‌سایت', 'placeholder' => 'https://example.com'],
                        'social' => ['label' => '💬 شبکه اجتماعی', 'placeholder' => '@username']
                    ];
                    
                    foreach ($detailTypes as $type => $info): 
                        $existingDetails = $contact['details'][$type] ?? [];
                    ?>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label><?php echo $info['label']; ?></label>
                            <div class="detail-items" id="<?php echo $type; ?>-items">
                                <?php if (empty($existingDetails) && $action !== 'view'): ?>
                                    <div class="detail-item">
                                        <input type="text" name="details_label[<?php echo $type; ?>][]" 
                                               placeholder="برچسب (مثلاً: محل کار)">
                                        <input type="text" name="details[<?php echo $type; ?>][]" 
                                               placeholder="<?php echo $info['placeholder']; ?>">
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <input type="radio" name="details_primary[<?php echo $type; ?>]" value="0">
                                            <span style="font-size: 12px;">اصلی</span>
                                        </div>
                                        <button type="button" class="btn-remove-detail" onclick="removeDetail(this)">❌</button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($existingDetails as $idx => $detail): ?>
                                        <div class="detail-item">
                                            <input type="text" name="details_label[<?php echo $type; ?>][]" 
                                                   value="<?php echo h($detail['label']); ?>"
                                                   placeholder="برچسب"
                                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                                            <input type="text" name="details[<?php echo $type; ?>][]" 
                                                   value="<?php echo h($detail['value']); ?>"
                                                   placeholder="<?php echo $info['placeholder']; ?>"
                                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <input type="radio" name="details_primary[<?php echo $type; ?>]" 
                                                       value="<?php echo $idx; ?>"
                                                       <?php echo $detail['is_primary'] ? 'checked' : ''; ?>
                                                       <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                                <span style="font-size: 12px;">اصلی</span>
                                            </div>
                                            <?php if ($action !== 'view'): ?>
                                                <button type="button" class="btn-remove-detail" onclick="removeDetail(this)">❌</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($action !== 'view'): ?>
                                <button type="button" class="btn-add-detail" 
                                        onclick="addDetail('<?php echo $type; ?>', '<?php echo $info['placeholder']; ?>')">
                                    ➕ افزودن <?php echo $info['label']; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- یادداشت‌ها -->
                <div class="form-section">
                    <h2>یادداشت‌ها و برچسب‌ها</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>یادداشت‌ها</label>
                            <textarea name="notes" <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($contact['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>برچسب‌ها</label>
                            <input type="text" name="tags" value="<?php echo h($contact['tags'] ?? ''); ?>"
                                   placeholder="برچسب‌ها را با کاما جدا کنید"
                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>
                
                <!-- دکمه‌های عملیات -->
                <div class="form-actions">
                    <?php if ($action === 'view' && $canWrite): ?>
                        <a href="contact.php?action=edit&id=<?php echo $contactId; ?>" class="btn btn-primary">
                            ✏️ ویرایش
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($action === 'edit' || $action === 'add'): ?>
                        <button type="submit" class="btn btn-primary">💾 ذخیره</button>
                    <?php endif; ?>
                    
                    <?php if ($action === 'view' && $canDelete): ?>
                        <form method="POST" action="contact.php?action=delete&id=<?php echo $contactId; ?>" 
                              style="display: inline;"
                              onsubmit="return confirm('آیا از حذف این مخاطب اطمینان دارید؟');">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="btn btn-danger">🗑️ حذف</button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="contacts.php" class="btn btn-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function addDetail(type, placeholder) {
            const container = document.getElementById(type + '-items');
            const index = container.children.length;
            
            const div = document.createElement('div');
            div.className = 'detail-item';
            div.innerHTML = `
                <input type="text" name="details_label[${type}][]" placeholder="برچسب">
                <input type="text" name="details[${type}][]" placeholder="${placeholder}">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <input type="radio" name="details_primary[${type}]" value="${index}">
                    <span style="font-size: 12px;">اصلی</span>
                </div>
                <button type="button" class="btn-remove-detail" onclick="removeDetail(this)">❌</button>
            `;
            
            container.appendChild(div);
        }
        
        function removeDetail(btn) {
            btn.closest('.detail-item').remove();
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>