<?php
/**
 * لیست کالاهای انبار
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

if (!check_permission('warehouse', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$warehouse_id = sanitize_input($_GET['warehouse_id'] ?? '');
$stock_status = sanitize_input($_GET['stock_status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT wi.*,
        COALESCE(SUM(CASE 
            WHEN wt.type = 'in' AND wt.status = 'completed' THEN wt.quantity
            WHEN wt.type = 'out' AND wt.status = 'completed' THEN -wt.quantity
            ELSE 0
        END), 0) as total_stock,
        COUNT(DISTINCT wt.warehouse_id) as warehouse_count
        FROM warehouse_items wi
        LEFT JOIN warehouse_transactions wt ON wt.item_id = wi.id
        WHERE wi.is_active = 1";

$params = [];

if ($search) {
    $sql .= " AND (wi.name LIKE :search OR wi.code LIKE :search OR wi.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($category) {
    $sql .= " AND wi.category = :category";
    $params[':category'] = $category;
}

$sql .= " GROUP BY wi.id";

// فیلتر وضعیت موجودی
if ($stock_status === 'low') {
    $sql = "SELECT * FROM (" . $sql . ") as items WHERE total_stock <= min_stock AND min_stock > 0";
} elseif ($stock_status === 'out') {
    $sql = "SELECT * FROM (" . $sql . ") as items WHERE total_stock = 0";
}

$sql .= " ORDER BY wi.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$items = $result['data'];
$totalPages = $result['total_pages'];

// دریافت دسته‌بندی‌ها
$categories = db()->select(
    "SELECT DISTINCT category FROM warehouse_items WHERE category IS NOT NULL AND category != '' ORDER BY category"
);

// دریافت انبارها
$warehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");

// آمار کلی
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN current_stock <= min_stock AND min_stock > 0 THEN 1 ELSE 0 END) as low_stock_items,
        SUM(CASE WHEN current_stock = 0 THEN 1 ELSE 0 END) as out_of_stock_items
    FROM warehouse_items 
    WHERE is_active = 1
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کالاهای انبار - <?php echo SITE_TITLE; ?></title>
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
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-card.low-stock .value {
            color: #ff9800;
        }
        
        .stat-card.out-stock .value {
            color: #f44336;
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
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
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
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-success {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        .stock-bar {
            width: 100%;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .stock-fill {
            height: 100%;
            background: #4caf50;
            transition: width 0.3s;
        }
        
        .stock-fill.warning {
            background: #ff9800;
        }
        
        .stock-fill.danger {
            background: #f44336;
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
        
        .btn-stock {
            background: #9c27b0;
            color: white;
        }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
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
        
        .page-link:hover,
        .page-link.active {
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
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
            <h1>📦 کالاهای انبار</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if (check_permission('warehouse', PERMISSION_WRITE)): ?>
                    <a href="warehouse_item.php?action=add" class="btn btn-primary">
                        ➕ تعریف کالای جدید
                    </a>
                    <a href="warehouse_request.php?action=add" class="btn btn-warning">
                        📋 درخواست متریال
                    </a>
                    <a href="warehouse_transactions.php" class="btn btn-success">
                        📊 تراکنش‌ها
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3>تعداد کل اقلام</h3>
                <div class="value"><?php echo en2fa(number_format($stats['total_items'])); ?></div>
            </div>
            <div class="stat-card low-stock">
                <h3>موجودی کم</h3>
                <div class="value"><?php echo en2fa(number_format($stats['low_stock_items'])); ?></div>
            </div>
            <div class="stat-card out-stock">
                <h3>ناموجود</h3>
                <div class="value"><?php echo en2fa(number_format($stats['out_of_stock_items'])); ?></div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="نام، کد، توضیحات..." 
                           value="<?php echo h($search); ?>">
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
                        <option value="low" <?php echo $stock_status === 'low' ? 'selected' : ''; ?>>موجودی کم</option>
                        <option value="out" <?php echo $stock_status === 'out' ? 'selected' : ''; ?>>ناموجود</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($items) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>کد</th>
                            <th>نام کالا</th>
                            <th>دسته‌بندی</th>
                            <th>واحد</th>
                            <th>موجودی کل</th>
                            <th>حداقل موجودی</th>
                            <th>قیمت واحد</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $stock = (float)$item['total_stock'];
                            $minStock = (float)$item['min_stock'];
                            $maxStock = (float)$item['max_stock'];
                            
                            if ($stock == 0) {
                                $statusClass = 'danger';
                                $statusText = 'ناموجود';
                            } elseif ($stock <= $minStock && $minStock > 0) {
                                $statusClass = 'warning';
                                $statusText = 'موجودی کم';
                            } else {
                                $statusClass = 'success';
                                $statusText = 'موجود';
                            }
                            
                            $stockPercent = $maxStock > 0 ? min(100, ($stock / $maxStock) * 100) : 100;
                            ?>
                            <tr>
                                <td><strong><?php echo h($item['code']); ?></strong></td>
                                <td>
                                    <strong><?php echo h($item['name']); ?></strong>
                                    <?php if ($item['description']): ?>
                                        <br><small style="color: #999;"><?php echo h(mb_substr($item['description'], 0, 50)); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($item['category'] ?: '-'); ?></td>
                                <td><?php echo h($item['unit']); ?></td>
                                <td>
                                    <strong style="font-size: 16px;"><?php echo en2fa(number_format($stock, 2)); ?></strong>
                                    <div class="stock-bar">
                                        <div class="stock-fill <?php echo $statusClass; ?>" 
                                             style="width: <?php echo $stockPercent; ?>%"></div>
                                    </div>
                                </td>
                                <td><?php echo en2fa(number_format($minStock, 2)); ?></td>
                                <td>
                                    <?php if ($item['unit_price']): ?>
                                        <?php echo en2fa(number_format($item['unit_price'])); ?>
                                        <small><?php echo h($item['currency']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="warehouse_item.php?action=view&id=<?php echo $item['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('warehouse', PERMISSION_WRITE)): ?>
                                            <a href="warehouse_item.php?action=edit&id=<?php echo $item['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                            <a href="warehouse_stock.php?item_id=<?php echo $item['id']; ?>" 
                                               class="btn-sm btn-stock" title="موجودی">📊</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&stock_status=<?php echo urlencode($stock_status); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&stock_status=<?php echo urlencode($stock_status); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&stock_status=<?php echo urlencode($stock_status); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ کالایی یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>