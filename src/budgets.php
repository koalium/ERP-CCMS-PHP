<?php
/**
 * ماژول بودجه - لیست بودجه‌ها
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('financial', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// ایجاد جدول بودجه
$createTableSQL = "CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_code VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    fiscal_year VARCHAR(10) NOT NULL,
    period ENUM('monthly', 'quarterly', 'semi_annual', 'annual') DEFAULT 'annual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_amount DECIMAL(20, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'IRR',
    status ENUM('draft', 'approved', 'active', 'closed') DEFAULT 'draft',
    project_id INT,
    department VARCHAR(100),
    notes TEXT,
    created_by INT NOT NULL,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (budget_code),
    INDEX idx_year (fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSQL);

// ایجاد جدول آیتم‌های بودجه
$createItemsSQL = "CREATE TABLE IF NOT EXISTS budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100),
    description TEXT,
    budgeted_amount DECIMAL(20, 2) NOT NULL,
    actual_amount DECIMAL(20, 2) DEFAULT 0,
    variance DECIMAL(20, 2) AS (actual_amount - budgeted_amount) STORED,
    variance_percent DECIMAL(10, 2) AS (CASE WHEN budgeted_amount > 0 THEN ((actual_amount - budgeted_amount) / budgeted_amount * 100) ELSE 0 END) STORED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    INDEX idx_budget (budget_id),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createItemsSQL);

$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$fiscal_year = sanitize_input($_GET['fiscal_year'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT b.*, 
        proj.code as project_code, proj.title as project_title,
        u.fullname as creator_name,
        (SELECT COUNT(*) FROM budget_items WHERE budget_id = b.id) as items_count,
        (SELECT SUM(budgeted_amount) FROM budget_items WHERE budget_id = b.id) as total_budgeted,
        (SELECT SUM(actual_amount) FROM budget_items WHERE budget_id = b.id) as total_actual
        FROM budgets b
        LEFT JOIN projects proj ON proj.id = b.project_id
        LEFT JOIN users u ON u.id = b.created_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (b.budget_code LIKE :search OR b.title LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $status;
}

if ($fiscal_year) {
    $sql .= " AND b.fiscal_year = :fiscal_year";
    $params[':fiscal_year'] = $fiscal_year;
}

$sql .= " ORDER BY b.fiscal_year DESC, b.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$budgets = $result['data'];
$totalPages = $result['total_pages'];

// دریافت سال‌های مالی
$years = db()->select("SELECT DISTINCT fiscal_year FROM budgets ORDER BY fiscal_year DESC");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بودجه - <?php echo SITE_TITLE; ?></title>
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
            flex-wrap: wrap;
            gap: 15px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; color: #555; font-size: 14px; }
        .form-group input, .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        th { padding: 15px; text-align: right; font-weight: bold; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        tbody tr { transition: background 0.2s; }
        tbody tr:hover { background: #f8f9fa; }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-draft { background: #e0e0e0; color: #616161; }
        .badge-approved { background: #cce5ff; color: #004085; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-closed { background: #f8d7da; color: #721c24; }
        
        .variance-positive { color: #d32f2f; font-weight: bold; }
        .variance-negative { color: #388e3c; font-weight: bold; }
        
        .actions { display: flex; gap: 8px; }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-view { background: #4caf50; color: white; }
        .btn-edit { background: #2196f3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
        }
        
        .page-link {
            padding: 8px 15px;
            border: 2px solid #667eea;
            border-radius: 6px;
            color: #667eea;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .page-link:hover, .page-link.active {
            background: #667eea;
            color: white;
        }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .filters form { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
            table { min-width: 1200px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 بودجه</h1>
            <?php if (check_permission('financial', PERMISSION_WRITE)): ?>
                <a href="budget.php?action=add" class="btn btn-primary">
                    ➕ افزودن بودجه جدید
                </a>
            <?php endif; ?>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="کد، عنوان..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>سال مالی</label>
                    <select name="fiscal_year">
                        <option value="">همه</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo h($year['fiscal_year']); ?>" 
                                    <?php echo $fiscal_year === $year['fiscal_year'] ? 'selected' : ''; ?>>
                                <?php echo en2fa($year['fiscal_year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                        <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>بسته شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($budgets) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>کد بودجه</th>
                            <th>عنوان</th>
                            <th>سال مالی</th>
                            <th>دوره</th>
                            <th>پروژه</th>
                            <th>بودجه تخصیص</th>
                            <th>واقعی</th>
                            <th>انحراف</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($budgets as $budget): ?>
                            <?php
                            $budgeted = $budget['total_budgeted'] ?? 0;
                            $actual = $budget['total_actual'] ?? 0;
                            $variance = $actual - $budgeted;
                            $variancePercent = $budgeted > 0 ? ($variance / $budgeted * 100) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo h($budget['budget_code']); ?></strong></td>
                                <td><?php echo h($budget['title']); ?></td>
                                <td><?php echo en2fa($budget['fiscal_year']); ?></td>
                                <td>
                                    <?php
                                    $periods = [
                                        'monthly' => 'ماهانه',
                                        'quarterly' => 'فصلی',
                                        'semi_annual' => 'نیم‌سال',
                                        'annual' => 'سالانه'
                                    ];
                                    echo $periods[$budget['period']] ?? $budget['period'];
                                    ?>
                                </td>
                                <td>
                                    <?php if ($budget['project_code']): ?>
                                        <?php echo h($budget['project_code']); ?>
                                    <?php elseif ($budget['department']): ?>
                                        <?php echo h($budget['department']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo en2fa(number_format($budgeted)); ?>
                                    <small><?php echo h($budget['currency']); ?></small>
                                </td>
                                <td>
                                    <?php echo en2fa(number_format($actual)); ?>
                                    <small><?php echo h($budget['currency']); ?></small>
                                </td>
                                <td>
                                    <span class="<?php echo $variance > 0 ? 'variance-positive' : 'variance-negative'; ?>">
                                        <?php echo en2fa(number_format($variance)); ?>
                                        (<?php echo en2fa(number_format($variancePercent, 1)); ?>%)
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'draft' => 'پیش‌نویس',
                                        'approved' => 'تایید شده',
                                        'active' => 'فعال',
                                        'closed' => 'بسته شده'
                                    ];
                                    $statusClass = 'badge-' . $budget['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$budget['status']] ?? $budget['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="budget.php?action=view&id=<?php echo $budget['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('financial', PERMISSION_WRITE)): ?>
                                            <a href="budget.php?action=edit&id=<?php echo $budget['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('financial', PERMISSION_FULL)): ?>
                                            <a href="budget.php?action=delete&id=<?php echo $budget['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این بودجه اطمینان دارید؟')">🗑️</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&fiscal_year=<?php echo urlencode($fiscal_year); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&fiscal_year=<?php echo urlencode($fiscal_year); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&fiscal_year=<?php echo urlencode($fiscal_year); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ بودجه‌ای یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>