<?php
/**
 * لیست تراکنش‌های مالی
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('financial', PERMISSION_READ)) {
    die('شما مجوز دسترسی ندارید.');
}

$accountId = $_GET['account_id'] ?? null;
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT t.*,
        a1.name as from_account_name,
        a2.name as to_account_name,
        c.name as contact_name,
        u1.fullname as created_by_name
        FROM transactions t
        LEFT JOIN accounts a1 ON a1.id = t.from_account_id
        LEFT JOIN accounts a2 ON a2.id = t.to_account_id
        LEFT JOIN contacts c ON c.id = t.contact_id
        LEFT JOIN users u1 ON u1.id = t.created_by
        WHERE 1=1";
$params = [];

if ($accountId) {
    $sql .= " AND (t.from_account_id = :account_id OR t.to_account_id = :account_id)";
    $params[':account_id'] = $accountId;
}

if ($type) {
    $sql .= " AND t.type = :type";
    $params[':type'] = $type;
}

if ($status) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $status;
}

if ($startDate) {
    $sql .= " AND DATE(t.transaction_date) >= :start_date";
    $params[':start_date'] = $startDate;
}

if ($endDate) {
    $sql .= " AND DATE(t.transaction_date) <= :end_date";
    $params[':end_date'] = $endDate;
}

$sql .= " ORDER BY t.transaction_date DESC, t.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$transactions = $result['data'];
$totalPages = $result['total_pages'];

// دریافت حساب اگر فیلتر شده
$account = $accountId ? db()->selectOne("SELECT * FROM accounts WHERE id = :id", [':id' => $accountId]) : null;

// آمار
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count
    FROM transactions
    WHERE 1=1 " . ($accountId ? " AND (from_account_id = :aid OR to_account_id = :aid)" : ""),
    $accountId ? [':aid' => $accountId] : []
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تراکنش‌ها - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 32px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            font-weight: bold;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(238, 9, 121, 0.5);
        }
        
        .account-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            padding: 25px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.income {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.expense {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
        }
        
        .stat-card.confirmed {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
        }
        
        .filters {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: bold;
            color: #666;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }
        
        th {
            padding: 18px 15px;
            text-align: right;
            font-weight: bold;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-income {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-expense {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-transfer {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-draft {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-pending {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .amount {
            font-weight: bold;
            font-size: 16px;
        }
        
        .amount.positive {
            color: #388e3c;
        }
        
        .amount.negative {
            color: #c62828;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-view {
            background: #4caf50;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
        }
        
        .page-link {
            padding: 10px 18px;
            border: 2px solid #ee0979;
            border-radius: 10px;
            color: #ee0979;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .page-link:hover,
        .page-link.active {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 900px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <h1>📊 تراکنش‌های مالی</h1>
                <div class="btn-group">
                    <?php if ($account): ?>
                        <a href="accounts.php" class="btn btn-secondary">↩️ بازگشت</a>
                    <?php endif; ?>
                    <?php if (check_permission('financial', PERMISSION_WRITE)): ?>
                        <a href="transaction.php?action=add<?php echo $accountId ? '&account_id=' . $accountId : ''; ?>" 
                           class="btn btn-primary">
                            ➕ تراکنش جدید
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($account): ?>
                <div class="account-info">
                    <h3>💰 حساب: <?php echo h($account['name']); ?></h3>
                    <p>موجودی: <?php echo en2fa(number_format($account['balance'], 2)); ?> <?php echo h($account['currency']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-label">کل تراکنش‌ها</div>
                    <div class="stat-value"><?php echo en2fa($stats['total']); ?></div>
                </div>
                <div class="stat-card income">
                    <div class="stat-label">درآمد</div>
                    <div class="stat-value"><?php echo en2fa(number_format($stats['total_income'])); ?></div>
                </div>
                <div class="stat-card expense">
                    <div class="stat-label">هزینه</div>
                    <div class="stat-value"><?php echo en2fa(number_format($stats['total_expense'])); ?></div>
                </div>
                <div class="stat-card confirmed">
                    <div class="stat-label">تایید شده</div>
                    <div class="stat-value"><?php echo en2fa($stats['confirmed_count']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET">
                <?php if ($accountId): ?>
                    <input type="hidden" name="account_id" value="<?php echo $accountId; ?>">
                <?php endif; ?>
                
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>نوع</label>
                        <select name="type">
                            <option value="">همه</option>
                            <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>درآمد</option>
                            <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>هزینه</option>
                            <option value="transfer" <?php echo $type === 'transfer' ? 'selected' : ''; ?>>انتقال</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>وضعیت</label>
                        <select name="status">
                            <option value="">همه</option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>تایید شده</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>از تاریخ</label>
                        <input type="text" name="start_date" class="jalali-date"
                               value="<?php echo h($startDate); ?>" placeholder="۱۴۰۴/۰۱/۰۱">
                    </div>
                    
                    <div class="filter-group">
                        <label>تا تاریخ</label>
                        <input type="text" name="end_date" class="jalali-date"
                               value="<?php echo h($endDate); ?>" placeholder="۱۴۰۴/۰۱/۰۱">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($transactions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>نوع</th>
                            <th>از حساب</th>
                            <th>به حساب</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                            <th>توضیحات</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td><?php echo date('Y/m/d H:i', strtotime($trans['transaction_date'])); ?></td>
                                <td>
                                    <?php
                                    $types = [
                                        'income' => '<span class="badge badge-income">📈 درآمد</span>',
                                        'expense' => '<span class="badge badge-expense">📉 هزینه</span>',
                                        'transfer' => '<span class="badge badge-transfer">🔄 انتقال</span>'
                                    ];
                                    echo $types[$trans['type']] ?? $trans['type'];
                                    ?>
                                </td>
                                <td><?php echo h($trans['from_account_name'] ?: '-'); ?></td>
                                <td><?php echo h($trans['to_account_name'] ?: '-'); ?></td>
                                <td>
                                    <span class="amount <?php echo $trans['type'] === 'income' ? 'positive' : 'negative'; ?>">
                                        <?php echo en2fa(number_format($trans['amount'], 2)); ?>
                                        <?php echo h($trans['currency']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statuses = [
                                        'draft' => '<span class="badge badge-draft">📝 پیش‌نویس</span>',
                                        'pending' => '<span class="badge badge-pending">⏳ در انتظار</span>',
                                        'confirmed' => '<span class="badge badge-confirmed">✅ تایید شده</span>',
                                        'cancelled' => '<span class="badge badge-cancelled">❌ لغو شده</span>'
                                    ];
                                    echo $statuses[$trans['status']] ?? $trans['status'];
                                    ?>
                                </td>
                                <td><?php echo h(mb_substr($trans['purpose'] ?: '-', 0, 50)); ?></td>
                                <td>
                                    <a href="transaction.php?action=view&id=<?php echo $trans['id']; ?>" 
                                       class="btn-sm btn-view">👁 مشاهده</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="padding: 80px 30px; text-align: center; color: #999;">
                    <div style="font-size: 80px; margin-bottom: 25px;">📊</div>
                    <h3>هیچ تراکنشی یافت نشد</h3>
                    <p>برای افزودن تراکنش جدید از دکمه بالا استفاده کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initJalaliDatePicker('.jalali-date');
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>