<?php
/**
 * مدیریت بودجه پروژه
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('projects', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$projectId = (int)($_GET['project_id'] ?? 0);
$action = sanitize_input($_GET['action'] ?? 'view');
$itemId = (int)($_GET['id'] ?? 0);

if (!$projectId) {
    die('پروژه مشخص نشده است.');
}

// بارگذاری پروژه
$project = db()->selectOne("SELECT * FROM projects WHERE id = :id", [':id' => $projectId]);
if (!$project) {
    die('پروژه یافت نشد.');
}

$error = '';
$success = '';

// ایجاد جدول بودجه اگر وجود ندارد
$createTableSql = "CREATE TABLE IF NOT EXISTS project_budget (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100),
    description TEXT,
    budgeted_amount DECIMAL(15, 2) NOT NULL,
    actual_amount DECIMAL(15, 2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'IRR',
    notes TEXT,
    sort_order INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_project_category (project_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSql);

// حذف ردیف بودجه
if ($action === 'delete' && $itemId && check_permission('projects', PERMISSION_FULL)) {
    if (db()->delete('project_budget', 'id = :id AND project_id = :pid', [':id' => $itemId, ':pid' => $projectId])) {
        redirect(SITE_URL . '/project_budget.php?project_id=' . $projectId . '&deleted=1');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی.';
    } else {
        $data = [
            'project_id' => $projectId,
            'category' => sanitize_input($_POST['category'] ?? ''),
            'subcategory' => sanitize_input($_POST['subcategory'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'budgeted_amount' => (float)str_replace(',', '', $_POST['budgeted_amount'] ?? 0),
            'currency' => sanitize_input($_POST['currency'] ?? 'IRR'),
            'notes' => sanitize_input($_POST['notes'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ];
        
        // اعتبارسنجی
        if (empty($data['category'])) {
            $error = 'دسته‌بندی الزامی است.';
        } elseif ($data['budgeted_amount'] <= 0) {
            $error = 'مبلغ بودجه باید بیشتر از صفر باشد.';
        } else {
            if ($action === 'add') {
                $data['created_by'] = $_SESSION['user_id'];
                $newId = db()->insert('project_budget', $data);
                
                if ($newId) {
                    $success = 'ردیف بودجه با موفقیت اضافه شد.';
                    $action = 'view';
                } else {
                    $error = 'خطا در افزودن ردیف بودجه.';
                }
            } else {
                unset($data['project_id']);
                $updated = db()->update('project_budget', $data, 'id = :id', [':id' => $itemId]);
                
                if ($updated !== false) {
                    $success = 'ردیف بودجه به‌روزرسانی شد.';
                    $action = 'view';
                } else {
                    $error = 'خطا در به‌روزرسانی.';
                }
            }
        }
    }
}

if (isset($_GET['deleted'])) {
    $success = 'ردیف بودجه حذف شد.';
}

// بارگذاری آیتم برای ویرایش
$budgetItem = null;
if ($action === 'edit' && $itemId) {
    $budgetItem = db()->selectOne(
        "SELECT * FROM project_budget WHERE id = :id AND project_id = :pid",
        [':id' => $itemId, ':pid' => $projectId]
    );
    if (!$budgetItem) {
        $action = 'view';
    }
}

// بارگذاری ردیف‌های بودجه
$budgetItems = db()->select(
    "SELECT * FROM project_budget 
     WHERE project_id = :pid 
     ORDER BY sort_order, category, subcategory",
    [':pid' => $projectId]
);

// گروه‌بندی بر اساس دسته
$groupedBudget = [];
$totalBudgeted = 0;
$totalActual = 0;

foreach ($budgetItems as $item) {
    $category = $item['category'];
    if (!isset($groupedBudget[$category])) {
        $groupedBudget[$category] = [
            'items' => [],
            'total_budgeted' => 0,
            'total_actual' => 0
        ];
    }
    
    $groupedBudget[$category]['items'][] = $item;
    $groupedBudget[$category]['total_budgeted'] += $item['budgeted_amount'];
    $groupedBudget[$category]['total_actual'] += $item['actual_amount'];
    
    $totalBudgeted += $item['budgeted_amount'];
    $totalActual += $item['actual_amount'];
}

// محاسبه هزینه‌های واقعی از تراکنش‌ها
$actualExpenses = db()->selectOne(
    "SELECT SUM(amount) as total FROM transactions 
     WHERE project_id = :pid AND type = 'expense' AND status = 'confirmed'",
    [':pid' => $projectId]
);

$actualFromTransactions = $actualExpenses['total'] ?? 0;

// دسته‌بندی‌های پیش‌فرض
$defaultCategories = [
    'مواد و تجهیزات',
    'نیروی انسانی',
    'حمل و نقل',
    'نصب و راه‌اندازی',
    'خدمات مهندسی',
    'کنترل کیفیت',
    'مدیریت پروژه',
    'هزینه‌های عمومی',
    'پیش‌بینی نشده'
];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بودجه پروژه - <?php echo h($project['title']); ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .header-content {
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
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-right: 4px solid #667eea;
        }
        
        .summary-card.warning {
            border-right-color: #ff9800;
        }
        
        .summary-card.danger {
            border-right-color: #f44336;
        }
        
        .summary-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .summary-amount {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .summary-progress {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .summary-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4caf50 0%, #45a049 100%);
            transition: width 0.3s;
        }
        
        .summary-progress-bar.warning {
            background: linear-gradient(90deg, #ff9800 0%, #f57c00 100%);
        }
        
        .summary-progress-bar.danger {
            background: linear-gradient(90deg, #f44336 0%, #d32f2f 100%);
        }
        
        .summary-label {
            font-size: 12px;
            color: #666;
        }
        
        .alert {
            padding: 12px 15px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .budget-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .category-section {
            margin-bottom: 30px;
        }
        
        .category-header {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-right: 4px solid #667eea;
        }
        
        .category-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .category-total {
            font-size: 16px;
            color: #667eea;
            font-weight: bold;
        }
        
        .budget-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .budget-table thead {
            background: #f8f9fa;
        }
        
        .budget-table th {
            padding: 12px;
            text-align: right;
            font-size: 13px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .budget-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .budget-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .amount {
            font-weight: bold;
            text-align: left;
            direction: ltr;
        }
        
        .variance {
            font-weight: bold;
        }
        
        .variance.positive {
            color: #4caf50;
        }
        
        .variance.negative {
            color: #f44336;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit { background: #2196f3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            color: #555;
            font-size: 14px;
            font-weight: bold;
        }
        
        .form-group label span {
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
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .total-row {
            background: #f0f4ff;
            font-weight: bold;
            font-size: 16px;
        }
        
        .total-row td {
            padding: 15px 12px;
            border-top: 3px solid #667eea;
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .budget-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="projects.php">پروژه‌ها</a>
                <span>›</span>
                <a href="project.php?action=view&id=<?php echo $projectId; ?>"><?php echo h($project['title']); ?></a>
                <span>›</span>
                <span>بودجه</span>
            </div>
            <div class="header-content">
                <h1>💰 بودجه پروژه</h1>
                <?php if ($action === 'view' && check_permission('projects', PERMISSION_WRITE)): ?>
                    <a href="?project_id=<?php echo $projectId; ?>&action=add" class="btn btn-primary">
                        ➕ افزودن ردیف بودجه
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'view'): ?>
            <!-- خلاصه بودجه -->
            <?php 
            $remaining = $totalBudgeted - $totalActual;
            $percentage = $totalBudgeted > 0 ? ($totalActual / $totalBudgeted) * 100 : 0;
            $cardClass = $percentage > 100 ? 'danger' : ($percentage > 90 ? 'warning' : '');
            $progressClass = $percentage > 100 ? 'danger' : ($percentage > 90 ? 'warning' : '');
            ?>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>📊 بودجه مصوب</h3>
                    <div class="summary-amount"><?php echo en2fa(number_format($totalBudgeted)); ?></div>
                    <div class="summary-label">ریال</div>
                </div>
                
                <div class="summary-card <?php echo $cardClass; ?>">
                    <h3>💸 هزینه شده</h3>
                    <div class="summary-amount"><?php echo en2fa(number_format($totalActual)); ?></div>
                    <div class="summary-progress">
                        <div class="summary-progress-bar <?php echo $progressClass; ?>" 
                             style="width: <?php echo min(100, $percentage); ?>%"></div>
                    </div>
                    <div class="summary-label"><?php echo en2fa(number_format($percentage, 1)); ?>% از بودجه</div>
                </div>
                
                <div class="summary-card">
                    <h3>💵 باقیمانده</h3>
                    <div class="summary-amount" style="color: <?php echo $remaining < 0 ? '#f44336' : '#4caf50'; ?>">
                        <?php echo en2fa(number_format(abs($remaining))); ?>
                    </div>
                    <div class="summary-label">
                        <?php echo $remaining < 0 ? '❌ اضافه برداشت' : '✅ مانده بودجه'; ?>
                    </div>
                </div>
            </div>
            
            <!-- جدول بودجه -->
            <div class="budget-container">
                <?php if (count($budgetItems) > 0): ?>
                    <?php foreach ($groupedBudget as $category => $data): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <div class="category-name"><?php echo h($category); ?></div>
                                <div class="category-total">
                                    <?php echo en2fa(number_format($data['total_budgeted'])); ?> ریال
                                </div>
                            </div>
                            
                            <table class="budget-table">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">شرح</th>
                                        <th style="width: 15%;">زیردسته</th>
                                        <th style="width: 15%;">بودجه مصوب</th>
                                        <th style="width: 15%;">هزینه واقعی</th>
                                        <th style="width: 15%;">انحراف</th>
                                        <th style="width: 15%;">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['items'] as $item): 
                                        $variance = $item['budgeted_amount'] - $item['actual_amount'];
                                        $varianceClass = $variance < 0 ? 'negative' : 'positive';
                                    ?>
                                        <tr>
                                            <td><?php echo h($item['description'] ?: '-'); ?></td>
                                            <td><?php echo h($item['subcategory'] ?: '-'); ?></td>
                                            <td class="amount"><?php echo en2fa(number_format($item['budgeted_amount'])); ?></td>
                                            <td class="amount"><?php echo en2fa(number_format($item['actual_amount'])); ?></td>
                                            <td class="amount variance <?php echo $varianceClass; ?>">
                                                <?php echo en2fa(number_format(abs($variance))); ?>
                                                <?php echo $variance < 0 ? '▲' : '▼'; ?>
                                            </td>
                                            <td>
                                                <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                                                    <div class="actions">
                                                        <a href="?project_id=<?php echo $projectId; ?>&action=edit&id=<?php echo $item['id']; ?>" 
                                                           class="btn-sm btn-edit">✏️</a>
                                                        <a href="?project_id=<?php echo $projectId; ?>&action=delete&id=<?php echo $item['id']; ?>" 
                                                           class="btn-sm btn-delete"
                                                           onclick="return confirm('آیا از حذف این ردیف اطمینان دارید؟')">🗑️</a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="2">جمع <?php echo h($category); ?></td>
                                        <td class="amount"><?php echo en2fa(number_format($data['total_budgeted'])); ?></td>
                                        <td class="amount"><?php echo en2fa(number_format($data['total_actual'])); ?></td>
                                        <td class="amount variance <?php echo ($data['total_budgeted'] - $data['total_actual']) < 0 ? 'negative' : 'positive'; ?>">
                                            <?php echo en2fa(number_format(abs($data['total_budgeted'] - $data['total_actual']))); ?>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- جمع کل -->
                    <table class="budget-table">
                        <tbody>
                            <tr class="total-row" style="background: #667eea; color: white;">
                                <td colspan="2" style="border-top: 3px solid #667eea; font-size: 18px;">جمع کل پروژه</td>
                                <td class="amount" style="border-top: 3px solid #667eea;"><?php echo en2fa(number_format($totalBudgeted)); ?></td>
                                <td class="amount" style="border-top: 3px solid #667eea;"><?php echo en2fa(number_format($totalActual)); ?></td>
                                <td class="amount" style="border-top: 3px solid #667eea;">
                                    <?php echo en2fa(number_format(abs($totalBudgeted - $totalActual))); ?>
                                    <?php echo ($totalBudgeted - $totalActual) < 0 ? '▲' : '▼'; ?>
                                </td>
                                <td style="border-top: 3px solid #667eea;"></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px; color: #999;">
                        <h2>بودجه پروژه تعریف نشده است</h2>
                        <p>برای شروع، اولین ردیف بودجه را اضافه کنید</p>
                        <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                            <a href="?project_id=<?php echo $projectId; ?>&action=add" class="btn btn-primary" style="margin-top: 20px;">
                                ➕ افزودن اولین ردیف
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- فرم افزودن/ویرایش -->
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">
                    <?php echo $action === 'add' ? '➕ افزودن ردیف بودجه' : '✏️ ویرایش ردیف بودجه'; ?>
                </h2>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>دسته‌بندی <span>*</span></label>
                            <input type="text" name="category" required list="categoriesList"
                                   value="<?php echo h($budgetItem['category'] ?? ''); ?>"
                                   placeholder="مثلاً: مواد و تجهیزات">
                            <datalist id="categoriesList">
                                <?php foreach ($defaultCategories as $cat): ?>
                                    <option value="<?php echo h($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label>زیردسته</label>
                            <input type="text" name="subcategory" 
                                   value="<?php echo h($budgetItem['subcategory'] ?? ''); ?>"
                                   placeholder="مثلاً: فن و پمپ">
                        </div>
                        
                        <div class="form-group">
                            <label>مبلغ بودجه (ریال) <span>*</span></label>
                            <input type="text" name="budgeted_amount" required 
                                   value="<?php echo h($budgetItem['budgeted_amount'] ?? ''); ?>"
                                   placeholder="مثلاً: 50000000">
                        </div>
                        
                        <div class="form-group">
                            <label>واحد پول</label>
                            <select name="currency">
                                <option value="IRR" <?php echo ($budgetItem['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال (IRR)</option>
                                <option value="USD" <?php echo ($budgetItem['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار (USD)</option>
                                <option value="EUR" <?php echo ($budgetItem['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو (EUR)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>اولویت نمایش</label>
                            <input type="number" name="sort_order" 
                                   value="<?php echo h($budgetItem['sort_order'] ?? 0); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>شرح</label>
                            <input type="text" name="description" 
                                   value="<?php echo h($budgetItem['description'] ?? ''); ?>"
                                   placeholder="شرح مختصری از این هزینه">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>یادداشت</label>
                            <textarea name="notes"><?php echo h($budgetItem['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="?project_id=<?php echo $projectId; ?>" class="btn btn-secondary">↩ بازگشت</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? '➕ افزودن ردیف' : '💾 ذخیره تغییرات'; ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>