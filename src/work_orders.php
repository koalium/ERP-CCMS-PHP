<?php
/**
 * لیست دستورات کار
 * Work Orders List
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('production', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$priority = sanitize_input($_GET['priority'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT wo.*, 
        p.title as project_title, p.code as project_code,
        pr.name as product_name, pr.code as product_code,
        u.fullname as assigned_name,
        creator.fullname as creator_name
        FROM work_orders wo
        LEFT JOIN projects p ON p.id = wo.project_id
        LEFT JOIN products pr ON pr.id = wo.product_id
        LEFT JOIN users u ON u.id = wo.assigned_to
        LEFT JOIN users creator ON creator.id = wo.created_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (wo.work_order_number LIKE :search OR wo.title LIKE :search OR pr.name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND wo.status = :status";
    $params[':status'] = $status;
}

if ($priority) {
    $sql .= " AND wo.priority = :priority";
    $params[':priority'] = $priority;
}

if ($project_id) {
    $sql .= " AND wo.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

$sql .= " ORDER BY 
    CASE wo.priority 
        WHEN 'urgent' THEN 1
        WHEN 'high' THEN 2
        WHEN 'medium' THEN 3
        WHEN 'low' THEN 4
    END,
    wo.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$workOrders = $result['data'];
$totalPages = $result['total_pages'];

// دریافت لیست پروژه‌ها برای فیلتر
$projects = db()->select("SELECT id, title FROM projects WHERE status = 'active' ORDER BY title");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دستورات کار - <?php echo SITE_TITLE; ?></title>
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
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
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
            font-size: 14px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
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
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-in_progress { background: #cce5ff; color: #004085; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        .badge-urgent { background: #f8d7da; color: #721c24; }
        .badge-high { background: #fff3cd; color: #856404; }
        .badge-medium { background: #d1ecf1; color: #0c5460; }
        .badge-low { background: #d4edda; color: #155724; }
        
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
        
        .btn-report {
            background: #ff9800;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
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
        
        .progress-mini {
            width: 60px;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }
        
        .progress-mini-fill {
            height: 100%;
            background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%);
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
            <h1>📋 دستورات کار</h1>
            <div style="display: flex; gap: 10px;">
                <a href="production.php" class="btn btn-back">⬅ بازگشت</a>
                <?php if (check_permission('production', PERMISSION_WRITE)): ?>
                    <a href="work_order.php?action=add" class="btn btn-primary">
                        ➕ دستور کار جدید
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="شماره، عنوان، محصول..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>اولویت</label>
                    <select name="priority">
                        <option value="">همه</option>
                        <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                        <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>بالا</option>
                        <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                        <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>پایین</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>پروژه</label>
                    <select name="project_id">
                        <option value="">همه</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>" 
                                    <?php echo $project_id === $proj['id'] ? 'selected' : ''; ?>>
                                <?php echo h($proj['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($workOrders) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>عنوان</th>
                            <th>محصول</th>
                            <th>پروژه</th>
                            <th>تعداد</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>پیشرفت</th>
                            <th>مسئول</th>
                            <th>سررسید</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workOrders as $wo): ?>
                            <tr>
                                <td><strong><?php echo h($wo['work_order_number']); ?></strong></td>
                                <td><?php echo h($wo['title']); ?></td>
                                <td>
                                    <?php if ($wo['product_name']): ?>
                                        <?php echo h($wo['product_code']); ?><br>
                                        <small style="color: #666;"><?php echo h($wo['product_name']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($wo['project_title']): ?>
                                        <?php echo h($wo['project_code']); ?><br>
                                        <small style="color: #666;"><?php echo h($wo['project_title']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo en2fa($wo['quantity'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $priorityLabels = ['urgent' => 'فوری', 'high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین'];
                                    $priorityClass = 'badge-' . $wo['priority'];
                                    ?>
                                    <span class="badge <?php echo $priorityClass; ?>">
                                        <?php echo $priorityLabels[$wo['priority']] ?? $wo['priority']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = ['pending' => 'در انتظار', 'in_progress' => 'در حال انجام', 
                                                    'completed' => 'تکمیل', 'cancelled' => 'لغو شده'];
                                    $statusClass = 'badge-' . $wo['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$wo['status']] ?? $wo['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress-mini">
                                        <div class="progress-mini-fill" style="width: <?php echo $wo['progress']; ?>%"></div>
                                    </div>
                                    <?php echo en2fa($wo['progress']); ?>٪
                                </td>
                                <td><?php echo h($wo['assigned_name'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($wo['due_date']): ?>
                                        <?php echo en2fa($wo['due_date']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="work_order.php?action=view&id=<?php echo $wo['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('production', PERMISSION_WRITE)): ?>
                                            <a href="work_order.php?action=edit&id=<?php echo $wo['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                            <a href="work_report.php?work_order_id=<?php echo $wo['id']; ?>" 
                                               class="btn-sm btn-report" title="گزارش">📝</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&project_id=<?php echo $project_id; ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&project_id=<?php echo $project_id; ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&project_id=<?php echo $project_id; ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ دستور کاری یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>