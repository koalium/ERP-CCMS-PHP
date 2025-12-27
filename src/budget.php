<?php
/**
 * فرم بودجه - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$budget = null;
$budgetItems = [];
$readonly = '';

if ($action === 'view') {
    if (!check_permission('financial', PERMISSION_READ)) {
        die('شما مجوز دسترسی به این بخش را ندارید.');
    }
    $readonly = 'readonly';
} elseif ($action === 'delete') {
    if (!check_permission('financial', PERMISSION_FULL)) {
        die('شما مجوز حذف بودجه را ندارید.');
    }
} else {
    if (!check_permission('financial', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش بودجه را ندارید.');
    }
}

if ($action === 'delete' && $id > 0) {
    db()->beginTransaction();
    try {
        db()->delete('budget_items', 'budget_id = :id', [':id' => $id]);
        db()->delete('budgets', 'id = :id', [':id' => $id]);
        
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'delete_budget',
            'module' => 'financial',
            'record_id' => $id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        db()->commit();
        redirect(SITE_URL . '/budgets.php?msg=deleted');
    } catch (Exception $e) {
        db()->rollback();
        $error = 'خطا در حذف بودجه.';
    }
    $action = 'view';
}

if (($action === 'edit' || $action === 'view') && $id > 0) {
    $budget = db()->selectOne("SELECT * FROM budgets WHERE id = :id", [':id' => $id]);
    if (!$budget) {
        die('بودجه یافت نشد.');
    }
    
    $budgetItems = db()->select("SELECT * FROM budget_items WHERE budget_id = :id ORDER BY category, subcategory", [':id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('توکن امنیتی نامعتبر است.');
    }
    
    $data = [
        'budget_code' => sanitize_input($_POST['budget_code']),
        'title' => sanitize_input($_POST['title']),
        'description' => sanitize_input($_POST['description']),
        'fiscal_year' => sanitize_input($_POST['fiscal_year']),
        'period' => sanitize_input($_POST['period']),
        'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
        'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        'total_amount' => (float)$_POST['total_amount'],
        'currency' => sanitize_input($_POST['currency']),
        'status' => sanitize_input($_POST['status']),
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'department' => sanitize_input($_POST['department']),
        'notes' => sanitize_input($_POST['notes'])
    ];
    
    if (empty($data['budget_code'])) {
        $error = 'کد بودجه الزامی است.';
    } elseif (empty($data['title'])) {
        $error = 'عنوان الزامی است.';
    } elseif (empty($data['fiscal_year'])) {
        $error = 'سال مالی الزامی است.';
    } else {
        $existing = db()->selectOne(
            "SELECT id FROM budgets WHERE budget_code = :budget_code AND id != :id",
            [':budget_code' => $data['budget_code'], ':id' => $id]
        );
        
        if ($existing) {
            $error = 'کد بودجه تکراری است.';
        }
    }
    
    if (empty($error)) {
        db()->beginTransaction();
        try {
            if ($action === 'edit' && $id > 0) {
                db()->update('budgets', $data, 'id = :id', [':id' => $id]);
                db()->delete('budget_items', 'budget_id = :id', [':id' => $id]);
            } else {
                $data['created_by'] = $_SESSION['user_id'];
                $id = db()->insert('budgets', $data);
            }
            
            // افزودن آیتم‌های بودجه
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['category']) && !empty($item['budgeted_amount'])) {
                        $itemData = [
                            'budget_id' => $id,
                            'category' => sanitize_input($item['category']),
                            'subcategory' => sanitize_input($item['subcategory']),
                            'description' => sanitize_input($item['description']),
                            'budgeted_amount' => (float)$item['budgeted_amount'],
                            'actual_amount' => !empty($item['actual_amount']) ? (float)$item['actual_amount'] : 0,
                            'notes' => sanitize_input($item['notes'])
                        ];
                        
                        db()->insert('budget_items', $itemData);
                    }
                }
            }
            
            db()->insert('logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => $action === 'edit' ? 'update_budget' : 'create_budget',
                'module' => 'financial',
                'record_id' => $id,
                'new_data' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            db()->commit();
            
            if ($action === 'edit') {
                $success = 'بودجه با موفقیت ویرایش شد.';
                $budget = db()->selectOne("SELECT * FROM budgets WHERE id = :id", [':id' => $id]);
                $budgetItems = db()->select("SELECT * FROM budget_items WHERE budget_id = :id ORDER BY category, subcategory", [':id' => $id]);
            } else {
                redirect(SITE_URL . '/budget.php?action=edit&id=' . $id . '&msg=created');
            }
        } catch (Exception $e) {
            db()->rollback();
            $error = 'خطا در ذخیره بودجه: ' . $e->getMessage();
        }
    }
}

$projects = db()->select("SELECT id, code, title FROM projects ORDER BY code");

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $success = 'بودجه با موفقیت ایجاد شد.';
    elseif ($_GET['msg'] === 'deleted') $success = 'بودجه با موفقیت حذف شد.';
}

$pageTitle = ['add' => 'افزودن بودجه جدید', 'edit' => 'ویرایش بودجه', 'view' => 'مشاهده بودجه'][$action] ?? 'بودجه';
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
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
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
        
        .items-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .items-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 40px 2fr 1.5fr 2fr 1fr 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: start;
        }
        
        .item-row input, .item-row textarea {
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .item-row textarea { min-height: 40px; resize: vertical; }
        
        .btn-add-item {
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-remove-item {
            background: #f44336;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            padding: 8px;
        }
        
        .summary {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .summary h4 { color: #1976d2; margin-bottom: 15px; }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #90caf9;
        }
        
        .summary-row:last-child { border-bottom: none; font-weight: bold; font-size: 16px; }
        
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
            .item-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 <?php echo $pageTitle; ?></h1>
            <a href="budgets.php" class="btn btn-secondary">↶ بازگشت به لیست</a>
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
                        <label>کد بودجه <span class="required">*</span></label>
                        <input type="text" name="budget_code" 
                               value="<?php echo h($budget['budget_code'] ?? 'BDG-' . date('Y') . '-'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>سال مالی <span class="required">*</span></label>
                        <input type="text" name="fiscal_year" 
                               value="<?php echo h($budget['fiscal_year'] ?? date('Y')); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>دوره</label>
                        <select name="period" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="monthly" <?php echo ($budget['period'] ?? '') === 'monthly' ? 'selected' : ''; ?>>ماهانه</option>
                            <option value="quarterly" <?php echo ($budget['period'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>فصلی</option>
                            <option value="semi_annual" <?php echo ($budget['period'] ?? '') === 'semi_annual' ? 'selected' : ''; ?>>نیم‌سال</option>
                            <option value="annual" <?php echo ($budget['period'] ?? 'annual') === 'annual' ? 'selected' : ''; ?>>سالانه</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>عنوان <span class="required">*</span></label>
                        <input type="text" name="title" 
                               value="<?php echo h($budget['title'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تاریخ شروع <span class="required">*</span></label>
                        <input type="text" name="start_date" class="jalali-date"
                               value="<?php echo h($budget['start_date'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ پایان <span class="required">*</span></label>
                        <input type="text" name="end_date" class="jalali-date"
                               value="<?php echo h($budget['end_date'] ?? ''); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>مبلغ کل <span class="required">*</span></label>
                        <input type="number" step="0.01" name="total_amount" 
                               value="<?php echo h($budget['total_amount'] ?? '0'); ?>" 
                               required <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>واحد پول</label>
                        <select name="currency" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="IRR" <?php echo ($budget['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال</option>
                            <option value="USD" <?php echo ($budget['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار</option>
                            <option value="EUR" <?php echo ($budget['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو</option>
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
                                        <?php echo ($budget['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code'] . ' - ' . $project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>دپارتمان</label>
                        <input type="text" name="department" 
                               value="<?php echo h($budget['department'] ?? ''); ?>" 
                               <?php echo $readonly; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <option value="draft" <?php echo ($budget['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="approved" <?php echo ($budget['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                            <option value="active" <?php echo ($budget['status'] ?? '') === 'active' ? 'selected' : ''; ?>>فعال</option>
                            <option value="closed" <?php echo ($budget['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>بسته شده</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" <?php echo $readonly; ?>><?php echo h($budget['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>یادداشت</label>
                        <textarea name="notes" <?php echo $readonly; ?>><?php echo h($budget['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <?php if ($action !== 'view'): ?>
                <div class="items-section">
                    <h3>آیتم‌های بودجه</h3>
                    
                    <div class="item-row" style="font-weight: bold; margin-bottom: 15px;">
                        <div>#</div>
                        <div>دسته‌بندی</div>
                        <div>زیردسته</div>
                        <div>شرح</div>
                        <div>بودجه</div>
                        <div>واقعی</div>
                        <div></div>
                    </div>
                    
                    <div id="items-container">
                        <?php if (count($budgetItems) > 0): ?>
                            <?php foreach ($budgetItems as $index => $item): ?>
                                <div class="item-row">
                                    <div><?php echo en2fa($index + 1); ?></div>
                                    <input type="text" name="items[<?php echo $index; ?>][category]" value="<?php echo h($item['category']); ?>" required>
                                    <input type="text" name="items[<?php echo $index; ?>][subcategory]" value="<?php echo h($item['subcategory']); ?>">
                                    <textarea name="items[<?php echo $index; ?>][description]"><?php echo h($item['description']); ?></textarea>
                                    <input type="number" step="0.01" name="items[<?php echo $index; ?>][budgeted_amount]" value="<?php echo h($item['budgeted_amount']); ?>" required>
                                    <input type="number" step="0.01" name="items[<?php echo $index; ?>][actual_amount]" value="<?php echo h($item['actual_amount']); ?>">
                                    <button type="button" class="btn-remove-item" onclick="removeItem(this)">✖</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="item-row">
                                <div>۱</div>
                                <input type="text" name="items[0][category]" required>
                                <input type="text" name="items[0][subcategory]">
                                <textarea name="items[0][description]"></textarea>
                                <input type="number" step="0.01" name="items[0][budgeted_amount]" required>
                                <input type="number" step="0.01" name="items[0][actual_amount]" value="0">
                                <button type="button" class="btn-remove-item" onclick="removeItem(this)">✖</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-add-item" onclick="addItem()">➕ افزودن آیتم</button>
                    
                    <div class="summary">
                        <h4>خلاصه بودجه</h4>
                        <div class="summary-row">
                            <span>جمع بودجه تخصیصی:</span>
                            <span id="total-budgeted">۰</span>
                        </div>
                        <div class="summary-row">
                            <span>جمع واقعی:</span>
                            <span id="total-actual">۰</span>
                        </div>
                        <div class="summary-row">
                            <span>انحراف:</span>
                            <span id="total-variance">۰</span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="items-section">
                    <h3>آیتم‌های بودجه</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: #f5f5f5;">
                            <tr>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">دسته</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">زیردسته</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">شرح</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">بودجه</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">واقعی</th>
                                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">انحراف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgetItems as $item): ?>
                                <tr>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo h($item['category']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo h($item['subcategory']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo h($item['description']); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo en2fa(number_format($item['budgeted_amount'])); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo en2fa(number_format($item['actual_amount'])); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo en2fa(number_format($item['variance'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if ($action !== 'view'): ?>
                <div class="form-container">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? '💾 ذخیره تغییرات' : '➕ افزودن بودجه'; ?>
                        </button>
                        
                        <?php if ($action === 'edit' && check_permission('financial', PERMISSION_FULL)): ?>
                            <a href="budget.php?action=delete&id=<?php echo $id; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('آیا از حذف این بودجه اطمینان دارید؟')">
                                🗑️ حذف
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <script>
        initJalaliDatePickers();
        
        let itemCounter = <?php echo count($budgetItems); ?>;
        
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <div>${toPersianNumber(itemCounter + 1)}</div>
                <input type="text" name="items[${itemCounter}][category]" required>
                <input type="text" name="items[${itemCounter}][subcategory]">
                <textarea name="items[${itemCounter}][description]"></textarea>
                <input type="number" step="0.01" name="items[${itemCounter}][budgeted_amount]" required onchange="calculateTotals()">
                <input type="number" step="0.01" name="items[${itemCounter}][actual_amount]" value="0" onchange="calculateTotals()">
                <button type="button" class="btn-remove-item" onclick="removeItem(this)">✖</button>
            `;
            container.appendChild(newRow);
            itemCounter++;
            updateItemNumbers();
            calculateTotals();
        }
        
        function removeItem(btn) {
            const rows = document.querySelectorAll('#items-container .item-row');
            if (rows.length > 1) {
                btn.closest('.item-row').remove();
                updateItemNumbers();
                calculateTotals();
            } else {
                alert('حداقل یک آیتم باید وجود داشته باشد.');
            }
        }
        
        function updateItemNumbers() {
            const rows = document.querySelectorAll('#items-container .item-row');
            rows.forEach((row, index) => {
                row.querySelector('div').textContent = toPersianNumber(index + 1);
            });
        }
        
        function calculateTotals() {
            const budgetedInputs = document.querySelectorAll('input[name*="[budgeted_amount]"]');
            const actualInputs = document.querySelectorAll('input[name*="[actual_amount]"]');
            
            let totalBudgeted = 0;
            let totalActual = 0;
            
            budgetedInputs.forEach(input => {
                totalBudgeted += parseFloat(input.value) || 0;
            });
            
            actualInputs.forEach(input => {
                totalActual += parseFloat(input.value) || 0;
            });
            
            const variance = totalActual - totalBudgeted;
            
            document.getElementById('total-budgeted').textContent = toPersianNumber(totalBudgeted.toLocaleString());
            document.getElementById('total-actual').textContent = toPersianNumber(totalActual.toLocaleString());
            document.getElementById('total-variance').textContent = toPersianNumber(variance.toLocaleString());
        }
        
        function toPersianNumber(num) {
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return num.toString().replace(/\d/g, d => persianDigits[d]);
        }
        
        // محاسبه اولیه
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotals();
            
            // اضافه کردن event listener به input‌های موجود
            document.querySelectorAll('input[name*="[budgeted_amount]"], input[name*="[actual_amount]"]').forEach(input => {
                input.addEventListener('change', calculateTotals);
            });
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>