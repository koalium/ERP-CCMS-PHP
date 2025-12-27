<?php
/**
 * داشبورد بخش مالی
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('financial', PERMISSION_READ)) {
    die('شما مجوز دسترسی به بخش مالی را ندارید.');
}

// دریافت آمار مالی
$financialStats = [
    'total_accounts' => db()->count('accounts', 'is_active = 1'),
    'total_transactions' => db()->count('transactions'),
    'pending_transactions' => db()->count('transactions', "status = 'pending'"),
    'confirmed_today' => db()->count('transactions', "status = 'confirmed' AND DATE(confirmed_at) = CURDATE()"),
];

// محاسبه موجودی کل حساب‌ها
$totalBalance = db()->selectOne(
    "SELECT 
        SUM(CASE WHEN currency = 'IRR' THEN balance ELSE 0 END) as irr_balance,
        SUM(CASE WHEN currency = 'USD' THEN balance ELSE 0 END) as usd_balance,
        SUM(CASE WHEN currency = 'EUR' THEN balance ELSE 0 END) as eur_balance
     FROM accounts 
     WHERE is_active = 1"
);

// حساب‌های اصلی
$mainAccounts = db()->select(
    "SELECT * FROM accounts 
     WHERE is_active = 1 
     ORDER BY balance DESC 
     LIMIT 6"
);

// آخرین تراکنش‌ها
$recentTransactions = db()->select(
    "SELECT t.*, 
     fa.name as from_account, 
     ta.name as to_account,
     c.name as contact_name,
     u.fullname as creator_name
     FROM transactions t
     LEFT JOIN accounts fa ON fa.id = t.from_account_id
     LEFT JOIN accounts ta ON ta.id = t.to_account_id
     LEFT JOIN contacts c ON c.id = t.contact_id
     LEFT JOIN users u ON u.id = t.created_by
     ORDER BY t.created_at DESC
     LIMIT 10"
);

// تراکنش‌های در انتظار تایید
$pendingTransactions = db()->select(
    "SELECT t.*, 
     fa.name as from_account, 
     ta.name as to_account,
     u.fullname as creator_name
     FROM transactions t
     LEFT JOIN accounts fa ON fa.id = t.from_account_id
     LEFT JOIN accounts ta ON ta.id = t.to_account_id
     LEFT JOIN users u ON u.id = t.created_by
     WHERE t.status = 'pending'
     ORDER BY t.created_at DESC"
);

// آمار درآمد و هزینه ماه جاری
$currentMonthStats = db()->selectOne(
    "SELECT 
        SUM(CASE WHEN type = 'income' AND status = 'confirmed' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN type = 'expense' AND status = 'confirmed' THEN amount ELSE 0 END) as expense,
        SUM(CASE WHEN type = 'transfer' AND status = 'confirmed' THEN amount ELSE 0 END) as transfers
     FROM transactions 
     WHERE MONTH(transaction_date) = MONTH(CURDATE()) 
     AND YEAR(transaction_date) = YEAR(CURDATE())"
);

$monthlyProfit = ($currentMonthStats['income'] ?? 0) - ($currentMonthStats['expense'] ?? 0);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مالی - <?php echo SITE_TITLE; ?></title>
    <style>
        .financial-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .financial-header {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.3);
        }
        
        .financial-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        /* Balance Cards */
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .balance-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }
        
        .balance-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .balance-card h3 {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 15px;
            position: relative;
        }
        
        .balance-amount {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
            position: relative;
        }
        
        .balance-currency {
            font-size: 14px;
            opacity: 0.8;
            position: relative;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .stat-card.income h3 {
            color: #27ae60;
        }
        
        .stat-card.expense h3 {
            color: #e74c3c;
        }
        
        .stat-card.profit h3 {
            color: <?php echo $monthlyProfit >= 0 ? '#27ae60' : '#e74c3c'; ?>;
        }
        
        /* Accounts Grid */
        .accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .account-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-right: 4px solid #27ae60;
            transition: transform 0.3s;
        }
        
        .account-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .account-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .account-balance {
            font-size: 24px;
            color: #27ae60;
            margin-bottom: 5px;
        }
        
        .account-type {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        /* Transactions Table */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: right;
            font-weight: bold;
            font-size: 13px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-draft { background: #e0e0e0; color: #666; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        .badge-income { background: #d4edda; color: #155724; }
        .badge-expense { background: #f8d7da; color: #721c24; }
        .badge-transfer { background: #d1ecf1; color: #0c5460; }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Amount Display */
        .amount-positive {
            color: #27ae60;
            font-weight: bold;
        }
        
        .amount-negative {
            color: #e74c3c;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .balance-grid,
            .accounts-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="financial-container">
        <!-- Financial Header -->
        <div class="financial-header">
            <h1>💰 داشبورد مالی و حسابداری</h1>
            <p>مدیریت حساب‌ها، تراکنش‌ها و گزارش‌های مالی</p>
        </div>
        
        <!-- Balance Cards -->
        <div class="balance-grid">
            <div class="balance-card">
                <h3>💵 موجودی ریالی</h3>
                <div class="balance-amount">
                    <?php echo en2fa(number_format($totalBalance['irr_balance'] ?? 0)); ?>
                </div>
                <div class="balance-currency">ریال ایران (IRR)</div>
            </div>
            
            <div class="balance-card">
                <h3>💵 موجودی دلاری</h3>
                <div class="balance-amount">
                    $<?php echo en2fa(number_format($totalBalance['usd_balance'] ?? 0, 2)); ?>
                </div>
                <div class="balance-currency">دلار آمریکا (USD)</div>
            </div>
            
            <div class="balance-card">
                <h3>💵 موجودی یورو</h3>
                <div class="balance-amount">
                    €<?php echo en2fa(number_format($totalBalance['eur_balance'] ?? 0, 2)); ?>
                </div>
                <div class="balance-currency">یورو (EUR)</div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card income">
                <h3><?php echo en2fa(number_format($currentMonthStats['income'] ?? 0)); ?></h3>
                <p>درآمد ماه جاری (ریال)</p>
            </div>
            
            <div class="stat-card expense">
                <h3><?php echo en2fa(number_format($currentMonthStats['expense'] ?? 0)); ?></h3>
                <p>هزینه ماه جاری (ریال)</p>
            </div>
            
            <div class="stat-card profit">
                <h3><?php echo en2fa(number_format(abs($monthlyProfit))); ?></h3>
                <p><?php echo $monthlyProfit >= 0 ? 'سود' : 'زیان'; ?> ماه جاری (ریال)</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($financialStats['pending_transactions']); ?></h3>
                <p>تراکنش‌های در انتظار</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="accounts.php" class="btn btn-primary">🏦 مدیریت حساب‌ها</a>
            <a href="transaction.php?action=add" class="btn btn-success">➕ تراکنش جدید</a>
            <a href="transactions.php" class="btn btn-primary">📊 تمام تراکنش‌ها</a>
            <a href="budgets.php" class="btn btn-primary">💼 بودجه‌بندی</a>
            <a href="ledger.php" class="btn btn-primary">📒 دفتر کل</a>
        </div>
        
        <!-- Main Accounts -->
        <h2 style="margin: 30px 0 20px;">🏦 حساب‌های اصلی</h2>
        <div class="accounts-grid">
            <?php foreach ($mainAccounts as $account): ?>
                <div class="account-card">
                    <div class="account-name"><?php echo h($account['name']); ?></div>
                    <div class="account-balance">
                        <?php echo en2fa(number_format($account['balance'])); ?>
                        <?php echo $account['currency']; ?>
                    </div>
                    <div class="account-type">
                        <?php 
                        $types = ['bank' => '🏦 حساب بانکی', 'cash' => '💵 نقدی', 'wallet' => '💳 کیف پول', 'custom' => '📊 سفارشی'];
                        echo $types[$account['type']] ?? $account['type'];
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (check_permission('financial', PERMISSION_WRITE) && count($pendingTransactions) > 0): ?>
        <!-- Pending Transactions -->
        <div class="table-container">
            <div class="table-header">
                ⏳ تراکنش‌های در انتظار تایید
                <span><?php echo en2fa(count($pendingTransactions)); ?> مورد</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>نوع</th>
                        <th>از حساب</th>
                        <th>به حساب</th>
                        <th>مبلغ</th>
                        <th>تاریخ</th>
                        <th>ایجادکننده</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingTransactions as $trans): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php echo $trans['type']; ?>">
                                    <?php 
                                    $types = ['income' => 'درآمد', 'expense' => 'هزینه', 'transfer' => 'انتقال'];
                                    echo $types[$trans['type']] ?? $trans['type'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo h($trans['from_account'] ?: '-'); ?></td>
                            <td><?php echo h($trans['to_account'] ?: '-'); ?></td>
                            <td class="amount-positive">
                                <?php echo en2fa(number_format($trans['amount'])); ?>
                                <?php echo $trans['currency']; ?>
                            </td>
                            <td><?php echo en2fa(date('Y/m/d', strtotime($trans['transaction_date']))); ?></td>
                            <td><?php echo h($trans['creator_name']); ?></td>
                            <td>
                                <?php if (check_permission('financial', PERMISSION_FULL)): ?>
                                    <a href="transaction.php?action=approve&id=<?php echo $trans['id']; ?>" 
                                       style="color: #27ae60; text-decoration: none;">✓ تایید</a>
                                    |
                                    <a href="transaction.php?action=reject&id=<?php echo $trans['id']; ?>" 
                                       style="color: #e74c3c; text-decoration: none;">✗ رد</a>
                                <?php else: ?>
                                    <a href="transaction.php?action=view&id=<?php echo $trans['id']; ?>" 
                                       style="color: #3498db; text-decoration: none;">👁 مشاهده</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Recent Transactions -->
        <div class="table-container">
            <div class="table-header">
                📋 آخرین تراکنش‌ها
                <a href="transactions.php" style="color: white; text-decoration: none; font-size: 13px;">
                    مشاهده همه
                </a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>نوع</th>
                        <th>از حساب</th>
                        <th>به حساب</th>
                        <th>مبلغ</th>
                        <th>مخاطب</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $trans): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php echo $trans['type']; ?>">
                                    <?php 
                                    $types = ['income' => 'درآمد', 'expense' => 'هزینه', 'transfer' => 'انتقال'];
                                    echo $types[$trans['type']] ?? $trans['type'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo h($trans['from_account'] ?: '-'); ?></td>
                            <td><?php echo h($trans['to_account'] ?: '-'); ?></td>
                            <td class="<?php echo $trans['type'] === 'income' ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php echo en2fa(number_format($trans['amount'])); ?>
                                <?php echo $trans['currency']; ?>
                            </td>
                            <td><?php echo h($trans['contact_name'] ?: '-'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $trans['status']; ?>">
                                    <?php 
                                    $statuses = ['draft' => 'پیش‌نویس', 'pending' => 'در انتظار', 'confirmed' => 'تایید شده', 'cancelled' => 'لغو شده'];
                                    echo $statuses[$trans['status']] ?? $trans['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo en2fa(date('Y/m/d', strtotime($trans['transaction_date']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>