<?php
/**
 * لیست MR (Material Request) - درخواست مواد
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('warehouse', PERMISSION_READ) && !check_permission('engineering', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$priority = sanitize_input($_GET['priority'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$requested_by_id = (int)($_GET['requested_by_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT mr.*, 
        p.title as project_name, p.code as project_code,
        u.fullname as requested_by_name,
        u2.fullname as approved_by_name,
        w.name as warehouse_name,
        (SELECT COUNT(*) FROM mr_items WHERE mr_id = mr.id) as items_count,
        (SELECT SUM(quantity) FROM mr_items WHERE mr_id = mr.id) as total_quantity
        FROM material_requests mr
        LEFT JOIN projects p ON p.id = mr.project_id
        LEFT JOIN users u ON u.id = mr.requested_by
        LEFT JOIN users u2 ON u2.id = mr.approved_by
        LEFT JOIN warehouses w ON w.id = mr.warehouse_id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (mr.mr_number LIKE :search OR mr.purpose LIKE :search OR mr.notes LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND mr.status = :status";
    $params[':status'] = $status;
}

if ($priority) {
    $sql .= " AND mr.priority = :priority";
    $params[':priority'] = $priority;
}

if ($project_id > 0) {
    $sql .= " AND mr.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

if ($requested_by_id > 0) {
    $sql .= " AND mr.requested_by = :requested_by_id";
    $params[':requested_by_id'] = $requested_by_id;
}

$sql .= " ORDER BY mr.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$mrs = $result['data'];
$totalPages = $result['total_pages'];

// دریافت لیست پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects WHERE status != 'cancelled' ORDER BY created_at DESC LIMIT 50");

// دریافت لیست درخواست‌کنندگان
$requesters = db()->select("
    SELECT DISTINCT u.id, u.fullname 
    FROM users u 
    INNER JOIN material_requests mr ON mr.requested_by = u.id 
    ORDER BY u.fullname 
    LIMIT 50
");

// آمار کلی
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'partially_issued' THEN 1 ELSE 0 END) as partially_issued,
        SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM material_requests
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لیست درخواست مواد - <?php echo SITE_TITLE; ?></title>
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
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.total { background: linear-gradient(135deg, #e96443 0%, #904e95 100%); }
        .stat-icon.pending { background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%); }
        .stat-icon.approved { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-icon.partial { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-icon.issued { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .stat-icon.rejected { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); }
        
        .stat-info h3 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #7f8c8d;
            font-size: 13px;
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
            background: linear-gradient(135deg, #e96443 0%, #904e95 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 100, 67, 0.4);
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
            font-size: 13px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e96443;
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
            background: linear-gradient(135deg, #e96443 0%, #904e95 100%);
            color: white;
        }
        
        th {
            padding: 15px 12px;
            text-align: right;
            font-weight: bold;
            font-size: 13px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #fff8f5;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-partially_issued {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-issued {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-cancelled {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .priority-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .priority-urgent {
            background: #dc3545;
            color: white;
        }
        
        .priority-high {
            background: #fd7e14;
            color: white;
        }
        
        .priority-normal {
            background: #17a2b8;
            color: white;
        }
        
        .priority-low {
            background: #6c757d;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-issue {
            background: #6f42c1;
            color: white;
        }
        
        .btn-print {
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
            align-items: center;
            gap: 10px;
            padding: 20px;
        }
        
        .page-link {
            padding: 8px 15px;
            border: 2px solid #e96443;
            border-radius: 6px;
            color: #e96443;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 13px;
        }
        
        .page-link:hover,
        .page-link.active {
            background: #e96443;
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-data svg {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .mr-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .mr-number {
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .mr-purpose {
            color: #7f8c8d;
            font-size: 11px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .project-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }
        
        .project-code {
            background: #fff3e0;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: #e65100;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 1300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                📦 لیست درخواست مواد (MR)
            </h1>
            <?php if (check_permission('warehouse', PERMISSION_WRITE) || check_permission('engineering', PERMISSION_WRITE)): ?>
                <a href="mr.php?action=add" class="btn btn-primary">
                    ➕ درخواست مواد جدید
                </a>
            <?php endif; ?>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon total">📋</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['total']); ?></h3>
                    <p>کل درخواست‌ها</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">⏳</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['pending']); ?></h3>
                    <p>در انتظار تایید</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">✅</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['approved']); ?></h3>
                    <p>تایید شده</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon partial">⚠️</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['partially_issued']); ?></h3>
                    <p>صادر شده جزئی</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon issued">📤</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['issued']); ?></h3>
                    <p>صادر شده کامل</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rejected">❌</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['rejected']); ?></h3>
                    <p>رد شده</p>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="شماره، هدف، یادداشت..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار تایید</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="partially_issued" <?php echo $status === 'partially_issued' ? 'selected' : ''; ?>>صادر شده جزئی</option>
                        <option value="issued" <?php echo $status === 'issued' ? 'selected' : ''; ?>>صادر شده کامل</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>اولویت</label>
                    <select name="priority">
                        <option value="">همه</option>
                        <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                        <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>بالا</option>
                        <option value="normal" <?php echo $priority === 'normal' ? 'selected' : ''; ?>>عادی</option>
                        <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>پایین</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>پروژه</label>
                    <select name="project_id">
                        <option value="">همه پروژه‌ها</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" 
                                    <?php echo $project_id == $project['id'] ? 'selected' : ''; ?>>
                                [<?php echo h($project['code']); ?>] <?php echo h($project['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>درخواست‌کننده</label>
                    <select name="requested_by_id">
                        <option value="">همه</option>
                        <?php foreach ($requesters as $requester): ?>
                            <option value="<?php echo $requester['id']; ?>" 
                                    <?php echo $requested_by_id == $requester['id'] ? 'selected' : ''; ?>>
                                <?php echo h($requester['fullname']); ?>
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
            <?php if (count($mrs) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره MR</th>
                            <th>هدف</th>
                            <th>پروژه</th>
                            <th>انبار</th>
                            <th>تعداد اقلام</th>
                            <th>کل مقدار</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>درخواست‌کننده</th>
                            <th>تاییدکننده</th>
                            <th>تاریخ نیاز</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mrs as $mr): ?>
                            <tr>
                                <td>
                                    <div class="mr-info">
                                        <span class="mr-number"><?php echo h($mr['mr_number']); ?></span>
                                        <small style="color: #999; font-size: 10px;">
                                            <?php echo en2fa(date('Y/m/d', strtotime($mr['created_at']))); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="mr-purpose" title="<?php echo h($mr['purpose']); ?>">
                                        <?php echo h($mr['purpose']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($mr['project_name']): ?>
                                        <div class="project-info">
                                            <span class="project-code"><?php echo h($mr['project_code']); ?></span>
                                            <span><?php echo h(mb_substr($mr['project_name'], 0, 18)); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 12px;">
                                        <?php echo $mr['warehouse_name'] ? h($mr['warehouse_name']) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: #2c3e50; font-size: 14px;">
                                        <?php echo en2fa($mr['items_count']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color: #27ae60; font-size: 13px;">
                                        <?php echo en2fa(number_format($mr['total_quantity'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $priorityLabels = [
                                        'urgent' => 'فوری',
                                        'high' => 'بالا',
                                        'normal' => 'عادی',
                                        'low' => 'پایین'
                                    ];
                                    ?>
                                    <span class="priority-badge priority-<?php echo $mr['priority']; ?>">
                                        <?php echo $priorityLabels[$mr['priority']] ?? $mr['priority']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'pending' => 'در انتظار',
                                        'approved' => 'تایید شده',
                                        'partially_issued' => 'جزئی',
                                        'issued' => 'صادر شده',
                                        'rejected' => 'رد شده',
                                        'cancelled' => 'لغو شده'
                                    ];
                                    $statusClass = 'badge-' . str_replace('_', '_', $mr['status']);
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$mr['status']] ?? $mr['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px;"><?php echo h($mr['requested_by_name']); ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 12px;">
                                        <?php echo $mr['approved_by_name'] ? h($mr['approved_by_name']) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #e74c3c; font-weight: bold;">
                                        <?php echo $mr['required_date'] ? en2fa(date('Y/m/d', strtotime($mr['required_date']))) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="mr.php?action=view&id=<?php echo $mr['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁️</a>
                                        <a href="mr.php?action=print&id=<?php echo $mr['id']; ?>" 
                                           class="btn-sm btn-print" title="چاپ" target="_blank">🖨️</a>
                                        <?php if (check_permission('warehouse', PERMISSION_WRITE) && $mr['status'] === 'pending'): ?>
                                            <a href="mr.php?action=edit&id=<?php echo $mr['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('warehouse', PERMISSION_FULL) && $mr['status'] === 'pending'): ?>
                                            <a href="mr.php?action=approve&id=<?php echo $mr['id']; ?>" 
                                               class="btn-sm btn-approve" title="تایید">✔️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('warehouse', PERMISSION_FULL) && in_array($mr['status'], ['approved', 'partially_issued'])): ?>
                                            <a href="mr.php?action=issue&id=<?php echo $mr['id']; ?>" 
                                               class="btn-sm btn-issue" title="صدور">📤</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('warehouse', PERMISSION_FULL) && $mr['status'] === 'pending'): ?>
                                            <button onclick="deleteMR(<?php echo $mr['id']; ?>)" 
                                                    class="btn-sm btn-delete" title="حذف">🗑️</button>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&project_id=<?php echo $project_id; ?>&requested_by_id=<?php echo $requested_by_id; ?>" 
                               class="page-link">❮ قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&project_id=<?php echo $project_id; ?>&requested_by_id=<?php echo $requested_by_id; ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&project_id=<?php echo $project_id; ?>&requested_by_id=<?php echo $requested_by_id; ?>" 
                               class="page-link">بعدی ❯</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3H5c-1.1 0-1.99.9-1.99 2L3 19c0 1.1.89 2 1.99 2H19c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/>
                        <path d="M7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z"/>
                    </svg>
                    <h3>هیچ درخواست مواد یافت نشد</h3>
                    <p>برای ثبت درخواست مواد جدید از دکمه بالای صفحه استفاده کنید.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function deleteMR(id) {
            if (confirm('آیا از حذف این درخواست مواد اطمینان دارید؟\nتوجه: این عملیات قابل بازگشت نیست.')) {
                window.location.href = 'mr.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>