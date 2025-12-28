<?php
/**
 * گزارش موجودی انبار
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'jalali-converter.php';

check_login();

if (!check_permission('warehouse', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای فیلتر
$warehouse_id = sanitize_input($_GET['warehouse_id'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$stock_status = sanitize_input($_GET['stock_status'] ?? '');

// دریافت انبارها
$warehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");

// دریافت دسته‌بندی‌ها
$categories = db()->select(
    "SELECT DISTINCT category FROM warehouse_items WHERE category IS NOT NULL AND category != '' ORDER BY category"
);

// ساخت کوئری موجودی
$sql = "SELECT 
    wi.id, wi.code, wi.name, wi.category, wi.unit, wi.min_stock, wi.unit_price, wi.currency,
    w.id as warehouse_id, w.name as warehouse_name, w.code as warehouse_code,
    COALESCE(SUM(CASE 
        WHEN wt.type = 'in' AND wt.status = 'completed' THEN wt.quantity
        WHEN wt.type = 'out' AND wt.status = 'completed' THEN -wt.quantity
        WHEN wt.type = 'transfer' AND wt.status = 'completed' AND wt.from_warehouse_id = w.id THEN -wt.quantity
        WHEN wt.type = 'transfer' AND wt.status = 'completed' AND wt.to_warehouse_id = w.id THEN wt.quantity
        ELSE 0
    END), 0) as stock
    FROM warehouse_items wi
    CROSS JOIN warehouses w
    LEFT JOIN warehouse_transactions wt ON 
        wt.item_id = wi.id AND 
        (wt.warehouse_id = w.id OR wt.from_warehouse_id = w.id OR wt.to_warehouse_id = w.id)
    WHERE wi.is_active = 1 AND w.is_active = 1";

$params = [];

if ($warehouse_id) {
    $sql .= " AND w.id = :warehouse_id";
    $params[':warehouse_id'] = $warehouse_id;
}

if ($category) {
    $sql .= " AND wi.category = :category";
    $params[':category'] = $category;
}

$sql .= " GROUP BY wi.id, w.id";

// فیلتر وضعیت موجودی
if ($stock_status === 'available') {
    $sql = "SELECT * FROM (" . $sql . ") as inv WHERE stock > 0";
} elseif ($stock_status === 'low') {
    $sql = "SELECT * FROM (" . $sql . ") as inv WHERE stock > 0 AND stock <= inv.min_stock AND inv.min_stock > 0";
} elseif ($stock_status === 'out') {
    $sql = "SELECT * FROM (" . $sql . ") as inv WHERE stock = 0";
}

$sql .= " ORDER BY warehouse_name, category, name";

$inventory = db()->select($sql, $params);

// محاسبه آمار کلی
$totalValue = 0;
$totalItems = 0;
$itemsByWarehouse = [];

foreach ($inventory as $item) {
    $totalValue += $item['stock'] * $item['unit_price'];
    $totalItems += $item['stock'];
    
    if (!isset($itemsByWarehouse[$item['warehouse_id']])) {
        $itemsByWarehouse[$item['warehouse_id']] = [
            'name' => $item['warehouse_name'],
            'count' => 0,
            'value' => 0
        ];
    }
    
    $itemsByWarehouse[$item['warehouse_id']]['count'] += $item['stock'];
    $itemsByWarehouse[$item['warehouse_id']]['value'] += $item['stock'] * $item['unit_price'];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش موجودی انبار - <?php echo SITE_TITLE; ?></title>
    <script src="jalali-datepicker.js"></script>
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
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .summary {
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
        }
        
        .summary-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .summary-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .summary-card .sub-value {
            font-size: 14px;
            color: #999;
            margin-top: 5px;
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
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            color: #555;
            font-size: 14px;
        }
        
        .form-group select {
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
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: right;
            font-weight: bold;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .warehouse-group {
            background: #e3f2fd;
            font-weight: bold;
        }
        
        .stock-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .stock-available {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .stock-low {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .stock-out {
            background: #ffebee;
            color: #c62828;
        }
        
        .print-btn {
            background: #9c27b0;
            color: white;
        }
        
        @media print {
            .header, .filters, .btn {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
            }
        }
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 1000px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 گزارش موجودی انبار</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="window.print()" class="btn print-btn">🖨️ چاپ گزارش</button>
                <button onclick="exportToExcel()" class="btn btn-success">📥 خروجی Excel</button>
                <a href="warehouse_items.php" class="btn btn-secondary">بازگشت</a>
            </div>
        </div>
        
        <div class="summary">
            <div class="summary-card">
                <h3>تعداد کل اقلام</h3>
                <div class="value"><?php echo en2fa(number_format($totalItems, 0)); ?></div>
                <div class="sub-value">در تمام انبارها</div>
            </div>
            <div class="summary-card">
                <h3>ارزش کل موجودی</h3>
                <div class="value"><?php echo en2fa(number_format($totalValue, 0)); ?></div>
                <div class="sub-value">ریال</div>
            </div>
            <?php foreach ($itemsByWarehouse as $wh): ?>
            <div class="summary-card">
                <h3><?php echo h($wh['name']); ?></h3>
                <div class="value"><?php echo en2fa(number_format($wh['count'], 0)); ?></div>
                <div class="sub-value"><?php echo en2fa(number_format($wh['value'], 0)); ?> ریال</div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>انبار</label>
                    <select name="warehouse_id">
                        <option value="">همه انبارها</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo $w['id']; ?>" 
                                    <?php echo $warehouse_id == $w['id'] ? 'selected' : ''; ?>>
                                <?php echo h($w['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>دسته‌بندی</label>
                    <select name="category">
                        <option value="">همه دسته‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo h($cat['category']); ?>" 
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo h($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>وضعیت موجودی</label>
                    <select name="stock_status">
                        <option value="">همه</option>
                        <option value="available" <?php echo $stock_status === 'available' ? 'selected' : ''; ?>>موجود</option>
                        <option value="low" <?php echo $stock_status === 'low' ? 'selected' : ''; ?>>کم</option>
                        <option value="out" <?php echo $stock_status === 'out' ? 'selected' : ''; ?>>ناموجود</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 نمایش گزارش</button>
                </div>
            </form>
        </div>
        
        <div class="table-container" id="inventoryTable">
            <table>
                <thead>
                    <tr>
                        <th>انبار</th>
                        <th>کد کالا</th>
                        <th>نام کالا</th>
                        <th>دسته‌بندی</th>
                        <th>موجودی</th>
                        <th>واحد</th>
                        <th>حداقل</th>
                        <th>قیمت واحد</th>
                        <th>ارزش کل</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentWarehouse = '';
                    foreach ($inventory as $item): 
                        if ($currentWarehouse !== $item['warehouse_name']):
                            $currentWarehouse = $item['warehouse_name'];
                    ?>
                        <tr class="warehouse-group">
                            <td colspan="10">🏢 <?php echo h($item['warehouse_name']); ?></td>
                        </tr>
                    <?php 
                        endif;
                        
                        $stockStatus = 'available';
                        $stockLabel = 'موجود';
                        if ($item['stock'] == 0) {
                            $stockStatus = 'out';
                            $stockLabel = 'ناموجود';
                        } elseif ($item['stock'] <= $item['min_stock'] && $item['min_stock'] > 0) {
                            $stockStatus = 'low';
                            $stockLabel = 'کم';
                        }
                        
                        $totalPrice = $item['stock'] * $item['unit_price'];
                    ?>
                        <tr>
                            <td><?php echo h($item['warehouse_code']); ?></td>
                            <td><strong><?php echo h($item['code']); ?></strong></td>
                            <td><?php echo h($item['name']); ?></td>
                            <td><?php echo h($item['category'] ?: '-'); ?></td>
                            <td><strong style="font-size: 16px;"><?php echo en2fa(number_format($item['stock'], 2)); ?></strong></td>
                            <td><?php echo h($item['unit']); ?></td>
                            <td><?php echo en2fa(number_format($item['min_stock'], 2)); ?></td>
                            <td><?php echo en2fa(number_format($item['unit_price'], 0)); ?></td>
                            <td><strong><?php echo en2fa(number_format($totalPrice, 0)); ?></strong></td>
                            <td><span class="stock-status stock-<?php echo $stockStatus; ?>"><?php echo $stockLabel; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function exportToExcel() {
            const table = document.getElementById('inventoryTable');
            const html = table.outerHTML;
            const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            const link = document.createElement('a');
            link.download = 'inventory_' + new Date().getTime() + '.xls';
            link.href = url;
            link.click();
        }
    </script>
</body>
</html>