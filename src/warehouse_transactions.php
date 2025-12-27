<?php
/**
 * لیست تراکنش‌های انبار (ورود، خروج، جابجایی)
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'jalali-converter.php';

check_login();

if (!check_permission('warehouse', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$type = sanitize_input($_GET['type'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$warehouse_id = sanitize_input($_GET['warehouse_id'] ?? '');
$item_id = sanitize_input($_GET['item_id'] ?? '');
$from_date = sanitize_input($_GET['from_date'] ?? '');
$to_date = sanitize_input($_GET['to_date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT wt.*,
        w.name as warehouse_name, w.code as warehouse_code,
        wi.name as item_name, wi.code as item_code, wi.unit,
        c.name as contact_name,
        p.code as project_code, p.title as project_title,
        u.fullname as requested_by_name,
        u2.fullname as approved_by_name
        FROM warehouse_transactions wt
        JOIN warehouses w ON w.id = wt.warehouse_id
        JOIN warehouse_items wi ON wi.id = wt.item_id
        LEFT JOIN contacts c ON c.id = wt.contact_id
        LEFT JOIN projects p ON p.id = wt.project_id
        LEFT JOIN users u ON u.id = wt.requested_by
        LEFT JOIN users u2 ON u2.id = wt.approved_by
        WHERE 1=1";

$params = [];

if ($type) {
    $sql .= " AND wt.type = :type";
    $params[':type'] = $type;
}

if ($status) {
    $sql .= " AND wt.status = :status";
    $params[':status'] = $status;
}

if ($warehouse_id) {
    $sql .= " AND wt.warehouse_id = :warehouse_id";
    $params[':warehouse_id'] = $warehouse_id;
}

if ($item_id) {
    $sql .= " AND wt.item_id = :item_id";
    $params[':item_id'] = $item_id;
}

if ($from_date) {
    $from_date_g = jalaliToGregorianDate($from_date);
    if ($from_date_g) {
        $sql .= " AND wt.transaction_date >= :from_date";
        $params[':from_date'] = $from_date_g;
    }
}

if ($to_date) {
    $to_date_g = jalaliToGregorianDate($to_date);
    if ($to_date_g) {
        $sql .= " AND wt.transaction_date <= :to_date";
        $params[':to_date'] = $to_date_g;
    }
}

$sql .= " ORDER BY wt.transaction_date DESC, wt.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$transactions = $result['data'];
$totalPages = $result['total_pages'];

// دریافت انبارها
$warehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");

// دریافت کالاها
$items = db()->select("SELECT id, code, name FROM warehouse_items WHERE is_active = 1 ORDER BY name");

// آمار کلی
$stats = db()->selectOne("
    SELECT 
        COUNT(CASE WHEN type = 'in' AND status = 'completed' THEN 1 END) as total_in,
        COUNT(CASE WHEN type = 'out' AND status = 'completed' THEN 1 END) as total_out,
        COUNT(CASE WHEN type = 'transfer' AND status = 'completed' THEN 1 END) as total_transfer,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM warehouse_transactions
    WHERE DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تراکنش‌های انبار - <?php echo SITE_TITLE; ?></title>
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
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-info {
            background: #2196f3;
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
        }
        
        .stat-card.in .value {
            color: #4caf50;
        }
        
        .stat-card.out .value {
            color: #f44336;
        }
        
        .stat-card.transfer .value {
            color: #2196f3;
        }
        
        .stat-card.pending .value {
            color: #ff9800;
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
        
        .badge-in {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-out {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-transfer {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-completed {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-approved {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-rejected {
            background: #ffebee;
            color: #c62828;
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
        }
        
        .btn-view {
            background: #4caf50;
            color: white;
        }
        
        .btn-approve {
            background: #2196f3;
            color: white;
        }
        
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
        
        .page-link:hover,
        .page-link.active {
            background: #667eea;
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 1200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 تراکنش‌های انبار</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if (check_permission('warehouse', PERMISSION_WRITE)): ?>
                    <a href="warehouse_in.php" class="btn btn-success">📥 ورود به انبار</a>
                    <a href="warehouse_out.php" class="btn btn-danger">📤 خروج از انبار</a>
                    <a href="warehouse_transfer.php" class="btn btn-info">🔄 جابجایی</a>
                <?php endif; ?>
                <a href="warehouse_items.php" class="btn btn-secondary">بازگشت</a>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card in">
                <h3>ورودی (30 روز اخیر)</h3>
                <div class="value"><?php echo en2fa(number_format($stats['total_in'])); ?></div>
            </div>
            <div class="stat-card out">
                <h3>خروجی (30 روز اخیر)</h3>
                <div class="value"><?php echo en2fa(number_format($stats['total_out'])); ?></div>
            </div>
            <div class="stat-card transfer">
                <h3>جابجایی (30 روز اخیر)</h3>
                <div class="value"><?php echo en2fa(number_format($stats['total_transfer'])); ?></div>
            </div>
            <div class="stat-card pending">
                <h3>در انتظار تایید</h3>
                <div class="value"><?php echo en2fa(number_format($stats['pending_count'])); ?></div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>نوع تراکنش</label>
                    <select name="type">
                        <option value="">همه</option>
                        <option value="in" <?php echo $type === 'in' ? 'selected' : ''; ?>>ورود</option>
                        <option value="out" <?php echo $type === 'out' ? 'selected' : ''; ?>>خروج</option>
                        <option value="transfer" <?php echo $type === 'transfer' ? 'selected' : ''; ?>>جابجایی</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>انبار</label>
                    <select name="warehouse_id">
                        <option value="">همه انبارها</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['id']; ?>" 
                                    <?php echo $warehouse_id == $warehouse['id'] ? 'selected' : ''; ?>>
                                <?php echo h($warehouse['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>کالا</label>
                    <select name="item_id">
                        <option value="">همه کالاها</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" 
                                    <?php echo $item_id == $item['id'] ? 'selected' : ''; ?>>
                                <?php echo h($item['code'] . ' - ' . $item['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>از تاریخ</label>
                    <input type="text" name="from_date" class="jalali-date-input"
                           value="<?php echo h($from_date); ?>">
                </div>
                
                <div class="form-group">
                    <label>تا تاریخ</label>
                    <input type="text" name="to_date" class="jalali-date-input"
                           value="<?php echo h($to_date); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-info">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($transactions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>تاریخ</th>
                            <th>نوع</th>
                            <th>انبار</th>
                            <th>کالا</th>
                            <th>مقدار</th>
                            <th>مرجع</th>
                            <th>پروژه</th>
                            <th>درخواست‌کننده</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td><strong><?php echo en2fa($trans['id']); ?></strong></td>
                                <td><?php echo en2fa(formatJalaliDate($trans['transaction_date'], 'Y/m/d')); ?></td>
                                <td>
                                    <?php
                                    $typeLabels = ['in' => 'ورود', 'out' => 'خروج', 'transfer' => 'جابجایی'];
                                    $typeClass = 'badge-' . $trans['type'];
                                    echo '<span class="badge ' . $typeClass . '">' . $typeLabels[$trans['type']] . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo h($trans['warehouse_name']); ?></strong>
                                    <br><small style="color: #999;"><?php echo h($trans['warehouse_code']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo h($trans['item_name']); ?></strong>
                                    <br><small style="color: #999;"><?php echo h($trans['item_code']); ?></small>
                                </td>
                                <td>
                                    <strong style="font-size: 16px; color: <?php echo $trans['type'] === 'in' ? '#4caf50' : '#f44336'; ?>">
                                        <?php echo ($trans['type'] === 'in' ? '+' : '-') . en2fa(number_format($trans['quantity'], 2)); ?>
                                    </strong>
                                    <br><small><?php echo h($trans['unit']); ?></small>
                                </td>
                                <td><?php echo h($trans['reference_number'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($trans['project_code']): ?>
                                        <?php echo h($trans['project_code']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($trans['requested_by_name']); ?></td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'pending' => 'در انتظار',
                                        'approved' => 'تایید شده',
                                        'completed' => 'تکمیل شده',
                                        'rejected' => 'رد شده'
                                    ];
                                    $statusClass = 'badge-' . $trans['status'];
                                    echo '<span class="badge ' . $statusClass . '">' . $statusLabels[$trans['status']] . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="warehouse_transaction_view.php?id=<?php echo $trans['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if ($trans['status'] === 'pending' && check_permission('warehouse', PERMISSION_FULL)): ?>
                                            <a href="warehouse_approve.php?id=<?php echo $trans['id']; ?>" 
                                               class="btn-sm btn-approve" title="تایید">✓</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&warehouse_id=<?php echo urlencode($warehouse_id); ?>&item_id=<?php echo urlencode($item_id); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&warehouse_id=<?php echo urlencode($warehouse_id); ?>&item_id=<?php echo urlencode($item_id); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&warehouse_id=<?php echo urlencode($warehouse_id); ?>&item_id=<?php echo urlencode($item_id); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ تراکنشی یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>