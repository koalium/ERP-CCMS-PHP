<?php
/**
 * لیست حساب‌های مالی
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('financial', PERMISSION_READ)) {
    die('شما مجوز دسترسی ندارید.');
}

$type = $_GET['type'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');

$sql = "SELECT a.*, c.name as owner_name,
        (SELECT COUNT(*) FROM transactions WHERE from_account_id = a.id OR to_account_id = a.id) as transactions_count
        FROM accounts a
        LEFT JOIN contacts c ON c.id = a.owner_contact_id
        WHERE a.is_active = 1";
$params = [];

if ($type) {
    $sql .= " AND a.type = :type";
    $params[':type'] = $type;
}

if ($search) {
    $sql .= " AND (a.name LIKE :search OR a.account_number LIKE :search OR a.bank_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY a.created_at DESC";

$accounts = db()->select($sql, $params);

// محاسبه مجموع موجودی‌ها
$totals = db()->selectOne("
    SELECT 
        SUM(CASE WHEN currency = 'IRR' THEN balance ELSE 0 END) as total_irr,
        SUM(CASE WHEN currency = 'USD' THEN balance ELSE 0 END) as total_usd,
        SUM(CASE WHEN currency = 'EUR' THEN balance ELSE 0 END) as total_eur,
        COUNT(*) as total_accounts
    FROM accounts WHERE is_active = 1
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حساب‌های مالی - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(17, 153, 142, 0.5);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card.irr {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.usd {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card.eur {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 12px 24px;
            background: #f5f5f5;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            font-weight: bold;
        }
        
        .accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }
        
        .account-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-top: 6px solid;
        }
        
        .account-card.bank {
            border-top-color: #2196f3;
        }
        
        .account-card.cash {
            border-top-color: #4caf50;
        }
        
        .account-card.wallet {
            border-top-color: #ff9800;
        }
        
        .account-card.custom {
            border-top-color: #9c27b0;
        }
        
        .account-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
        }
        
        .account-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            margin-bottom: 20px;
        }
        
        .account-name {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .account-number {
            color: #999;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .account-balance {
            font-size: 32px;
            font-weight: bold;
            color: #11998e;
            margin: 20px 0;
        }
        
        .account-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-type {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-currency {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-transactions {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .account-actions {
            display: flex;
            gap: 10px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-sm {
            flex: 1;
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: #4caf50;
            color: white;
        }
        
        .btn-edit {
            background: #2196f3;
            color: white;
        }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        
        .no-data {
            background: white;
            padding: 80px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
            color: #999;
            grid-column: 1 / -1;
        }
        
        @media (max-width: 768px) {
            .accounts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <h1>💰 حساب‌های مالی</h1>
                <?php if (check_permission('financial', PERMISSION_WRITE)): ?>
                    <a href="account.php?action=add" class="btn btn-primary">
                        ➕ حساب جدید
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">تعداد حساب‌ها</div>
                    <div class="stat-value"><?php echo en2fa($totals['total_accounts']); ?></div>
                </div>
                <div class="stat-card irr">
                    <div class="stat-label">موجودی ریالی</div>
                    <div class="stat-value"><?php echo en2fa(number_format($totals['total_irr'])); ?></div>
                </div>
                <div class="stat-card usd">
                    <div class="stat-label">موجودی دلاری</div>
                    <div class="stat-value">$<?php echo en2fa(number_format($totals['total_usd'], 2)); ?></div>
                </div>
                <div class="stat-card eur">
                    <div class="stat-label">موجودی یورو</div>
                    <div class="stat-value">€<?php echo en2fa(number_format($totals['total_eur'], 2)); ?></div>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-tabs">
                <a href="?" class="filter-tab <?php echo !$type ? 'active' : ''; ?>">همه حساب‌ها</a>
                <a href="?type=bank" class="filter-tab <?php echo $type === 'bank' ? 'active' : ''; ?>">🏦 بانکی</a>
                <a href="?type=cash" class="filter-tab <?php echo $type === 'cash' ? 'active' : ''; ?>">💵 نقدی</a>
                <a href="?type=wallet" class="filter-tab <?php echo $type === 'wallet' ? 'active' : ''; ?>">👛 کیف پول</a>
                <a href="?type=custom" class="filter-tab <?php echo $type === 'custom' ? 'active' : ''; ?>">📋 سایر</a>
            </div>
            
            <form method="GET" style="display: flex; gap: 12px;">
                <input type="hidden" name="type" value="<?php echo h($type); ?>">
                <input type="text" name="search" placeholder="جستجو در نام، شماره حساب، بانک..." 
                       value="<?php echo h($search); ?>"
                       style="flex: 1; padding: 14px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 15px;">
                <button type="submit" class="btn btn-primary">🔍 جستجو</button>
            </form>
        </div>
        
        <div class="accounts-grid">
            <?php if (count($accounts) > 0): ?>
                <?php foreach ($accounts as $account): ?>
                    <div class="account-card <?php echo $account['type']; ?>">
                        <div class="account-icon">
                            <?php 
                            $icons = ['bank' => '🏦', 'cash' => '💵', 'wallet' => '👛', 'custom' => '📋'];
                            echo $icons[$account['type']] ?? '💰';
                            ?>
                        </div>
                        
                        <h3 class="account-name"><?php echo h($account['name']); ?></h3>
                        
                        <?php if ($account['account_number']): ?>
                            <div class="account-number">
                                شماره حساب: <?php echo h($account['account_number']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($account['bank_name']): ?>
                            <div class="account-number">
                                بانک: <?php echo h($account['bank_name']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="account-balance">
                            <?php echo en2fa(number_format($account['balance'], 2)); ?>
                            <small style="font-size: 16px; color: #666;">
                                <?php echo h($account['currency']); ?>
                            </small>
                        </div>
                        
                        <div class="account-meta">
                            <span class="badge badge-type">
                                <?php 
                                $types = [
                                    'bank' => '🏦 بانکی',
                                    'cash' => '💵 نقدی',
                                    'wallet' => '👛 کیف پول',
                                    'custom' => '📋 سایر'
                                ];
                                echo $types[$account['type']] ?? $account['type'];
                                ?>
                            </span>
                            
                            <span class="badge badge-currency">
                                <?php echo h($account['currency']); ?>
                            </span>
                            
                            <span class="badge badge-transactions">
                                📊 <?php echo en2fa($account['transactions_count']); ?> تراکنش
                            </span>
                        </div>
                        
                        <?php if ($account['owner_name']): ?>
                            <div style="color: #666; font-size: 14px; margin-top: 10px;">
                                👤 <?php echo h($account['owner_name']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="account-actions">
                            <a href="account.php?action=view&id=<?php echo $account['id']; ?>" 
                               class="btn-sm btn-view">👁 مشاهده</a>
                            
                            <?php if (check_permission('financial', PERMISSION_WRITE)): ?>
                                <a href="account.php?action=edit&id=<?php echo $account['id']; ?>" 
                                   class="btn-sm btn-edit">✏️ ویرایش</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <div style="font-size: 80px; margin-bottom: 25px;">💰</div>
                    <h3>هیچ حسابی یافت نشد</h3>
                    <p>برای افزودن حساب جدید از دکمه بالا استفاده کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>