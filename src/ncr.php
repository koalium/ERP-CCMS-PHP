<?php
/**
 * فرم گزارش عدم انطباق (NCR - Non-Conformance Report)
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$ncrId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$ncr = null;

// چک مجوز
$canWrite = check_permission('qc', PERMISSION_WRITE);
$canApprove = check_permission('qc', PERMISSION_FULL);

if ($action === 'view' || $action === 'edit') {
    if (!$ncrId) {
        redirect(SITE_URL . '/production.php');
    }
    
    $ncr = db()->selectOne(
        "SELECT qf.*, p.title as project_title, pr.name as product_name,
         u.fullname as inspector_name, creator.fullname as created_by_name
         FROM qc_forms qf
         LEFT JOIN projects p ON p.id = qf.project_id
         LEFT JOIN products pr ON pr.id = qf.product_id
         LEFT JOIN users u ON u.id = qf.inspector_user_id
         LEFT JOIN users creator ON creator.id = qf.created_by
         WHERE qf.id = :id AND qf.type = 'ncr'",
        [':id' => $ncrId]
    );
    
    if (!$ncr) {
        redirect(SITE_URL . '/production.php');
    }
}

// ذخیره NCR
if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($action === 'add' || $action === 'edit') && $canWrite) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $data = [
            'type' => 'ncr',
            'title' => sanitize_input($_POST['title']),
            'project_id' => (int)($_POST['project_id'] ?? 0) ?: null,
            'product_id' => (int)($_POST['product_id'] ?? 0) ?: null,
            'inspection_date' => sanitize_input($_POST['inspection_date']),
            'inspector_user_id' => (int)($_POST['inspector_user_id'] ?? 0) ?: $_SESSION['user_id'],
            'status' => sanitize_input($_POST['status']),
            'result' => sanitize_input($_POST['result'] ?? null),
            'findings' => sanitize_input($_POST['findings']),
            'corrective_actions' => sanitize_input($_POST['corrective_actions'] ?? '')
        ];
        
        // تبدیل checklist به JSON
        $checklist = $_POST['checklist'] ?? [];
        $data['checklist'] = json_encode($checklist);
        
        // اعتبارسنجی
        if (empty($data['title']) || empty($data['findings'])) {
            $error = 'عنوان و یافته‌ها الزامی است.';
        } else {
            db()->beginTransaction();
            
            try {
                if ($action === 'add') {
                    // تولید شماره NCR
                    $lastNumber = db()->selectOne(
                        "SELECT form_number FROM qc_forms WHERE type = 'ncr' ORDER BY id DESC LIMIT 1"
                    );
                    
                    if ($lastNumber) {
                        $num = (int)substr($lastNumber['form_number'], 4) + 1;
                    } else {
                        $num = 1;
                    }
                    
                    $data['form_number'] = 'NCR-' . str_pad($num, 5, '0', STR_PAD_LEFT);
                    $data['created_by'] = $_SESSION['user_id'];
                    
                    $ncrId = db()->insert('qc_forms', $data);
                    $logAction = 'add_ncr';
                } else {
                    db()->update('qc_forms', $data, 'id = :id', [':id' => $ncrId]);
                    $logAction = 'edit_ncr';
                }
                
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => $logAction,
                    'module' => 'qc',
                    'record_id' => $ncrId,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                db()->commit();
                
                redirect(SITE_URL . '/ncr.php?action=view&id=' . $ncrId . '&msg=saved');
            } catch (Exception $e) {
                db()->rollback();
                $error = 'خطا در ذخیره اطلاعات: ' . $e->getMessage();
            }
        }
    }
}

// دریافت لیست پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects ORDER BY title");

// دریافت لیست محصولات
$products = db()->select("SELECT id, code, name FROM products WHERE status = 'active' ORDER BY name");

// دریافت لیست بازرسین
$inspectors = db()->select("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname");

// Decode checklist if exists
$checklistData = [];
if ($ncr && $ncr['checklist']) {
    $checklistData = json_decode($ncr['checklist'], true) ?? [];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        echo $action === 'add' ? 'گزارش NCR جدید' : 
             ($action === 'edit' ? 'ویرایش NCR' : 'مشاهده NCR');
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
            max-width: 1000px;
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
        
        .alert-success {
            background: #efe;
            color: #3c3;
        }
        
        .alert-warning {
            background: #fffbea;
            color: #856404;
            border: 1px solid #ffd700;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-section h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-open { background: #fff3cd; color: #856404; }
        .badge-in_progress { background: #cce5ff; color: #004085; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-approved { background: #c3e6cb; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        
        .badge-pass { background: #d4edda; color: #155724; }
        .badge-fail { background: #f8d7da; color: #721c24; }
        .badge-conditional { background: #fff3cd; color: #856404; }
        
        .checklist-items {
            margin-top: 15px;
        }
        
        .checklist-item {
            display: grid;
            grid-template-columns: 1fr 120px 50px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .checklist-item input[type="text"] {
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .checklist-item select {
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .btn-add-checklist {
            padding: 8px 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-remove {
            padding: 6px 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .info-box {
            background: #fff3f3;
            border: 2px solid #ff6b6b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ <?php 
                echo $action === 'add' ? 'گزارش عدم انطباق جدید (NCR)' : 
                     ($action === 'edit' ? 'ویرایش NCR' : 'مشاهده NCR');
                
                if ($ncr) {
                    echo ' - ' . h($ncr['form_number']);
                }
            ?></h1>
            <a href="production.php" class="btn-back">⬅ بازگشت</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
            <div class="alert alert-success">گزارش NCR با موفقیت ذخیره شد.</div>
        <?php endif; ?>
        
        <div class="alert alert-warning">
            <strong>⚠️ توجه:</strong> گزارش عدم انطباق (NCR) برای ثبت مشکلات کیفی و اقدامات اصلاحی استفاده می‌شود.
        </div>
        
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <?php if ($action !== 'add'): ?>
                    <div class="info-box">
                        <div class="info-item">
                            <span>شماره NCR:</span>
                            <strong><?php echo h($ncr['form_number']); ?></strong>
                        </div>
                        <div class="info-item">
                            <span>وضعیت:</span>
                            <span class="badge badge-<?php echo $ncr['status']; ?>">
                                <?php 
                                $statuses = [
                                    'open' => 'باز',
                                    'in_progress' => 'در حال بررسی',
                                    'completed' => 'بسته شده',
                                    'approved' => 'تایید شده',
                                    'rejected' => 'رد شده'
                                ];
                                echo $statuses[$ncr['status']] ?? $ncr['status'];
                                ?>
                            </span>
                        </div>
                        <?php if ($ncr['result']): ?>
                            <div class="info-item">
                                <span>نتیجه:</span>
                                <span class="badge badge-<?php echo $ncr['result']; ?>">
                                    <?php 
                                    $results = ['pass' => 'قبول', 'fail' => 'رد', 'conditional' => 'مشروط'];
                                    echo $results[$ncr['result']] ?? $ncr['result'];
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <h2>🔍 اطلاعات پایه</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>عنوان NCR *</label>
                            <input type="text" name="title" 
                                   value="<?php echo h($ncr['title'] ?? ''); ?>" 
                                   required 
                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ بازرسی *</label>
                            <input type="date" name="inspection_date" 
                                   value="<?php echo $ncr['inspection_date'] ?? date('Y-m-d'); ?>" 
                                   required 
                                   <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>بازرس</label>
                            <select name="inspector_user_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">من (<?php echo h($_SESSION['fullname']); ?>)</option>
                                <?php foreach ($inspectors as $insp): ?>
                                    <option value="<?php echo $insp['id']; ?>" 
                                            <?php echo ($ncr['inspector_user_id'] ?? 0) == $insp['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($insp['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>پروژه</label>
                            <select name="project_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?php echo $proj['id']; ?>" 
                                            <?php echo ($ncr['project_id'] ?? 0) == $proj['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($proj['code']) . ' - ' . h($proj['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>محصول</label>
                            <select name="product_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>" 
                                            <?php echo ($ncr['product_id'] ?? 0) == $prod['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($prod['code']) . ' - ' . h($prod['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>وضعیت *</label>
                            <select name="status" required <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="open" <?php echo ($ncr['status'] ?? 'open') === 'open' ? 'selected' : ''; ?>>باز</option>
                                <option value="in_progress" <?php echo ($ncr['status'] ?? 'open') === 'in_progress' ? 'selected' : ''; ?>>در حال بررسی</option>
                                <option value="completed" <?php echo ($ncr['status'] ?? 'open') === 'completed' ? 'selected' : ''; ?>>بسته شده</option>
                            </select>
                        </div>
                        
                        <?php if ($action !== 'add'): ?>
                            <div class="form-group">
                                <label>نتیجه</label>
                                <select name="result" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                    <option value="">انتخاب کنید</option>
                                    <option value="pass" <?php echo ($ncr['result'] ?? '') === 'pass' ? 'selected' : ''; ?>>قبول</option>
                                    <option value="fail" <?php echo ($ncr['result'] ?? '') === 'fail' ? 'selected' : ''; ?>>رد</option>
                                    <option value="conditional" <?php echo ($ncr['result'] ?? '') === 'conditional' ? 'selected' : ''; ?>>مشروط</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>📋 چک‌لیست بازرسی</h2>
                    <div class="checklist-items" id="checklistItems">
                        <?php if ($action === 'view' && !empty($checklistData)): ?>
                            <?php foreach ($checklistData as $item): ?>
                                <div style="padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <strong><?php echo h($item['item']); ?></strong>
                                        <span class="badge badge-<?php echo $item['result']; ?>">
                                            <?php 
                                            $results = ['pass' => '✓ قبول', 'fail' => '✗ رد', 'na' => '-'];
                                            echo $results[$item['result']] ?? $item['result'];
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($checklistData)): ?>
                            <?php foreach ($checklistData as $idx => $item): ?>
                                <div class="checklist-item">
                                    <input type="text" name="checklist[<?php echo $idx; ?>][item]" 
                                           value="<?php echo h($item['item']); ?>" 
                                           placeholder="مورد بازرسی" required>
                                    <select name="checklist[<?php echo $idx; ?>][result]" required>
                                        <option value="pass" <?php echo $item['result'] === 'pass' ? 'selected' : ''; ?>>✓ قبول</option>
                                        <option value="fail" <?php echo $item['result'] === 'fail' ? 'selected' : ''; ?>>✗ رد</option>
                                        <option value="na" <?php echo $item['result'] === 'na' ? 'selected' : ''; ?>>- نامشخص</option>
                                    </select>
                                    <button type="button" class="btn-remove" onclick="removeChecklist(this)">❌</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="checklist-item">
                                <input type="text" name="checklist[0][item]" placeholder="مورد بازرسی">
                                <select name="checklist[0][result]">
                                    <option value="pass">✓ قبول</option>
                                    <option value="fail">✗ رد</option>
                                    <option value="na">- نامشخص</option>
                                </select>
                                <button type="button" class="btn-remove" onclick="removeChecklist(this)">❌</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($action !== 'view'): ?>
                        <button type="button" class="btn-add-checklist" onclick="addChecklist()">➕ افزودن مورد</button>
                    <?php endif; ?>
                </div>
                
                <div class="form-section">
                    <h2>📝 یافته‌ها و اقدامات</h2>
                    <div class="form-group">
                        <label>یافته‌ها و عدم انطباق‌ها *</label>
                        <textarea name="findings" required 
                                  <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($ncr['findings'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>اقدامات اصلاحی پیشنهادی</label>
                        <textarea name="corrective_actions" 
                                  <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($ncr['corrective_actions'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <?php if ($action === 'view' && $canWrite && $ncr['status'] !== 'completed'): ?>
                        <a href="ncr.php?action=edit&id=<?php echo $ncrId; ?>" class="btn btn-primary">✏️ ویرایش</a>
                    <?php endif; ?>
                    
                    <?php if ($action === 'edit' || $action === 'add'): ?>
                        <button type="submit" class="btn btn-primary">💾 ذخیره</button>
                    <?php endif; ?>
                    
                    <a href="production.php" class="btn btn-secondary">بازگشت</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let checklistIndex = <?php echo !empty($checklistData) ? count($checklistData) : 1; ?>;
        
        function addChecklist() {
            const container = document.getElementById('checklistItems');
            const div = document.createElement('div');
            div.className = 'checklist-item';
            div.innerHTML = `
                <input type="text" name="checklist[${checklistIndex}][item]" placeholder="مورد بازرسی">
                <select name="checklist[${checklistIndex}][result]">
                    <option value="pass">✓ قبول</option>
                    <option value="fail">✗ رد</option>
                    <option value="na">- نامشخص</option>
                </select>
                <button type="button" class="btn-remove" onclick="removeChecklist(this)">❌</button>
            `;
            container.appendChild(div);
            checklistIndex++;
        }
        
        function removeChecklist(btn) {
            if (document.querySelectorAll('.checklist-item').length > 1) {
                btn.closest('.checklist-item').remove();
            } else {
                alert('حداقل یک مورد باید وجود داشته باشد.');
            }
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>