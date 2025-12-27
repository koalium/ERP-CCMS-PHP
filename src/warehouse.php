<?php
/**
 * داشبورد مرکزی انبار
 * Warehouse Dashboard
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('warehouse', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$userId = $_SESSION['user_id'];
$canManage = check_permission('warehouse', PERMISSION_FULL);

// دریافت انبارهایی که کاربر به آن‌ها دسترسی دارد
if ($canManage) {
    // مدیر انبار: همه انبارها
    $userWarehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");
} else {
    // انبار دار: فقط انبارهای محول شده
    $userWarehouses = db()->select(
        "SELECT w.* FROM warehouses w
         INNER JOIN warehouse_users wu ON w.id = wu.warehouse_id
         WHERE wu.user_id = :uid AND w.is_active = 1
         ORDER BY w.name",
        [':uid' => $userId]
    );
}

// انبار انتخاب شده
$selectedWarehouseId = (int)($_GET['warehouse_id'] ?? ($userWarehouses[0]['id'] ?? 0));

if ($selectedWarehouseId && !$canManage) {
    // چک دسترسی کاربر به انبار
    $hasAccess = false;
    foreach ($userWarehouses as $w) {
        if ($w['id'] == $selectedWarehouseId) {
            $hasAccess = true;
            break;
        }
    }
    if (!$hasAccess) {
        $selectedWarehouseId = $userWarehouses[0]['id'] ?? 0;
    }
}

// آمار کلی
$stats = [];

// تعداد کل انبارها
$stats['total_warehouses'] = count($userWarehouses);

// تعداد کل اقلام
$stats['total_items'] = db()->count('warehouse_items', 'is_active = 1');

// تعداد اقلام با موجودی کم
$stats['low_stock_items'] = db()->count(
    'warehouse_items',
    'is_active = 1 AND current_stock <= min_stock AND min_stock > 0'
);

// تراکنش‌های امروز
$today = date('Y-m-d');
$stats['today_transactions'] = db()->count(
    'warehouse_transactions',
    'DATE(transaction_date) = :date' . ($selectedWarehouseId ? ' AND warehouse_id = :wid' : ''),
    $selectedWarehouseId ? [':date' => $today, ':wid' => $selectedWarehouseId] : [':date' => $today]
);

// ارزش کل موجودی (تقریبی)
$totalValue = db()->selectOne(
    "SELECT SUM(current_stock * unit_price) as total_value 
     FROM warehouse_items 
     WHERE is_active = 1"
);
$stats['inventory_value'] = $totalValue['total_value'] ?? 0;

// آخرین تراکنش‌ها
$sql = "SELECT wt.*, w.name as warehouse_name, wi.name as item_name, wi.code as item_code,
        u.fullname as user_name
        FROM warehouse_transactions wt
        LEFT JOIN warehouses w ON w.id = wt.warehouse_id
        LEFT JOIN warehouse_items wi ON wi.id = wt.item_id
        LEFT JOIN users u ON u.id = wt.requested_by";

if ($selectedWarehouseId) {
    $sql .= " WHERE wt.warehouse_id = :wid";
    $params = [':wid' => $selectedWarehouseId];
} else {
    $params = [];
}

$sql .= " ORDER BY wt.created_at DESC LIMIT 10";

$recentTransactions = db()->select($sql, $params);

// اقلام با موجودی کم
$lowStockItems = db()->select(
    "SELECT * FROM warehouse_items 
     WHERE is_active = 1 AND current_stock <= min_stock AND min_stock > 0
     ORDER BY (current_stock / min_stock) ASC
     LIMIT 10"
);

// آمار انبار انتخاب شده
$warehouseStats = null;
if ($selectedWarehouseId) {
    $warehouseStats = db()->selectOne(
        "SELECT 
            COUNT(DISTINCT wt.item_id) as unique_items,
            SUM(CASE WHEN wt.type = 'in' THEN 1 ELSE 0 END) as total_in,
            SUM(CASE WHEN wt.type = 'out' THEN 1 ELSE 0 END) as total_out,
            SUM(CASE WHEN wt.type = 'transfer' THEN 1 ELSE 0 END) as total_transfer
         FROM warehouse_transactions wt
         WHERE wt.warehouse_id = :wid AND DATE(wt.transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        [':wid' => $selectedWarehouseId]
    );
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد انبار - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .warehouse-selector {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .warehouse-selector select {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            color: #2c3e50;
            cursor: pointer;
            min-width: 250px;
        }
        
        .warehouse-selector button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .warehouse-selector button:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.red { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .stat-content h3 {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .stat-content .number {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .quick-actions h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .btn-action {
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: bold;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
            justify-content: center;
        }
        
        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .btn-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .btn-danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
            font-size: 18px;
        }
        
        .transaction-item {
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .transaction-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .transaction-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .transaction-type {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-in { background: #d4edda; color: #155724; }
        .badge-out { background: #f8d7da; color: #721c24; }
        .badge-transfer { background: #d1ecf1; color: #0c5460; }
        .badge-adjustment { background: #fff3cd; color: #856404; }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-completed { background: #cce5ff; color: #004085; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        
        .transaction-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }
        
        .item-alert {
            padding: 12px 15px;
            background: #fff3cd;
            border-right: 4px solid #ffc107;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .item-alert-critical {
            background: #f8d7da;
            border-right-color: #dc3545;
        }
        
        .item-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .item-stock {
            font-size: 13px;
            color: #666;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 داشبورد مدیریت انبار</h1>
            <form method="GET" class="warehouse-selector">
                <label style="opacity: 0.9;">انبار:</label>
                <select name="warehouse_id" onchange="this.form.submit()">
                    <option value="0">همه انبارها</option>
                    <?php foreach ($userWarehouses as $w): ?>
                        <option value="<?php echo $w['id']; ?>" <?php echo $selectedWarehouseId == $w['id'] ? 'selected' : ''; ?>>
                            <?php echo h($w['name']); ?> (<?php echo h($w['code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($canManage): ?>
                    <button type="button" onclick="location.href='warehouses.php'">⚙️ مدیریت انبارها</button>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- آمار کلیدی -->
        <div class="stats-grid">
            <div class="stat-card" onclick="location.href='warehouses.php'">
                <div class="stat-icon blue">🏢</div>
                <div class="stat-content">
                    <h3>تعداد انبارها</h3>
                    <div class="number"><?php echo en2fa($stats['total_warehouses']); ?></div>
                </div>
            </div>
            
            <div class="stat-card" onclick="location.href='warehouse_items.php'">
                <div class="stat-icon green">📋</div>
                <div class="stat-content">
                    <h3>تعداد اقلام</h3>
                    <div class="number"><?php echo en2fa($stats['total_items']); ?></div>
                </div>
            </div>
            
            <div class="stat-card" onclick="location.href='warehouse_items.php?filter=low_stock'">
                <div class="stat-icon orange">⚠️</div>
                <div class="stat-content">
                    <h3>موجودی کم</h3>
                    <div class="number"><?php echo en2fa($stats['low_stock_items']); ?></div>
                </div>
            </div>
            
            <div class="stat-card" onclick="location.href='warehouse_transactions.php?date=today'">
                <div class="stat-icon purple">📊</div>
                <div class="stat-content">
                    <h3>تراکنش امروز</h3>
                    <div class="number"><?php echo en2fa($stats['today_transactions']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">💰</div>
                <div class="stat-content">
                    <h3>ارزش موجودی</h3>
                    <div class="number" style="font-size: 18px;">
                        <?php echo en2fa(number_format($stats['inventory_value'])); ?> ریال
                    </div>
                </div>
            </div>
        </div>
        
        <!-- عملیات سریع -->
        <?php if (check_permission('warehouse', PERMISSION_WRITE)): ?>
            <div class="quick-actions">
                <h2>⚡ عملیات سریع</h2>
                <div class="action-buttons">
                    <a href="warehouse_transaction.php?action=add&type=in" class="btn-action btn-success">
                        📥 ورود به انبار
                    </a>
                    <a href="warehouse_transaction.php?action=add&type=out" class="btn-action btn-warning">
                        📤 خروج از انبار
                    </a>
                    <a href="warehouse_transaction.php?action=add&type=transfer" class="btn-action btn-info">
                        🔄 جابجایی
                    </a>
                    <a href="warehouse_item.php?action=add" class="btn-action btn-primary">
                        ➕ قلم جدید
                    </a>
                    <a href="warehouse_inventory.php<?php echo $selectedWarehouseId ? '?warehouse_id=' . $selectedWarehouseId : ''; ?>" class="btn-action btn-primary">
                        📊 گزارش موجودی
                    </a>
                    <a href="warehouse_reports.php" class="btn-action btn-danger">
                        📈 گزارش‌گیری
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- محتوای اصلی -->
        <div class="content-grid">
            <!-- آخرین تراکنش‌ها -->
            <div class="section">
                <h2>📋 آخرین تراکنش‌ها</h2>
                <?php if (count($recentTransactions) > 0): ?>
                    <?php foreach ($recentTransactions as $tr): ?>
                        <div class="transaction-item">
                            <div class="transaction-header">
                                <div>
                                    <div class="transaction-type">
                                        <?php 
                                        $typeIcons = ['in' => '📥', 'out' => '📤', 'transfer' => '🔄', 'adjustment' => '⚙️'];
                                        echo $typeIcons[$tr['type']] ?? '📝';
                                        ?>
                                        <?php echo h($tr['item_code']); ?> - <?php echo h($tr['item_name']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                        انبار: <?php echo h($tr['warehouse_name']); ?>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $typeLabels = ['in' => 'ورود', 'out' => 'خروج', 'transfer' => 'جابجایی', 'adjustment' => 'تعدیل'];
                                    $typeClass = 'badge-' . $tr['type'];
                                    ?>
                                    <span class="badge <?php echo $typeClass; ?>">
                                        <?php echo $typeLabels[$tr['type']] ?? $tr['type']; ?>
                                    </span>
                                    <br>
                                    <?php
                                    $statusLabels = ['pending' => 'در انتظار', 'approved' => 'تایید', 'completed' => 'انجام شده', 'rejected' => 'رد'];
                                    $statusClass = 'badge-' . $tr['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>" style="margin-top: 5px;">
                                        <?php echo $statusLabels[$tr['status']] ?? $tr['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="transaction-meta">
                                <span>تعداد: <strong><?php echo en2fa($tr['quantity']); ?></strong></span>
                                <?php if ($tr['unit_price']): ?>
                                    <span>قیمت واحد: <strong><?php echo en2fa(number_format($tr['unit_price'])); ?> ریال</strong></span>
                                <?php endif; ?>
                                <?php if ($tr['user_name']): ?>
                                    <span>توسط: <?php echo h($tr['user_name']); ?></span>
                                <?php endif; ?>
                                <span>تاریخ: <?php echo en2fa($tr['transaction_date']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="warehouse_transactions.php" style="color: #667eea; text-decoration: none; font-weight: bold;">
                            مشاهده همه تراکنش‌ها ←
                        </a>
                    </div>
                <?php else: ?>
                    <div class="no-data">تراکنشی ثبت نشده است</div>
                <?php endif; ?>
            </div>
            
            <!-- موجودی کم -->
            <div class="section">
                <h2>⚠️ هشدار موجودی</h2>
                <?php if (count($lowStockItems) > 0): ?>
                    <?php foreach ($lowStockItems as $item): ?>
                        <?php 
                        $stockRatio = $item['min_stock'] > 0 ? ($item['current_stock'] / $item['min_stock']) : 1;
                        $isCritical = $stockRatio <= 0.5;
                        ?>
                        <div class="item-alert <?php echo $isCritical ? 'item-alert-critical' : ''; ?>">
                            <div class="item-name">
                                <?php echo h($item['code']); ?> - <?php echo h($item['name']); ?>
                            </div>
                            <div class="item-stock">
                                موجودی: <strong><?php echo en2fa($item['current_stock']); ?></strong> <?php echo h($item['unit']); ?>
                                / حداقل: <strong><?php echo en2fa($item['min_stock']); ?></strong>
                            </div>
                            <?php if ($isCritical): ?>
                                <div style="margin-top: 5px; color: #dc3545; font-size: 12px; font-weight: bold;">
                                    🚨 نیاز فوری به تامین
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="warehouse_items.php?filter=low_stock" style="color: #667eea; text-decoration: none; font-weight: bold;">
                            مشاهده همه موارد ←
                        </a>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="padding: 20px;">
                        ✅ موجودی همه اقلام مناسب است
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>