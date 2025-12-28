<?php
/**
 * لیست NCR (Non-Conformance Report) - گزارش عدم انطباق
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('qc', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$severity = sanitize_input($_GET['severity'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT n.*, 
        p.title as project_name, p.code as project_code,
        u.fullname as reported_by_name,
        u2.fullname as assigned_to_name,
        u3.fullname as closed_by_name
        FROM ncrs n
        LEFT JOIN projects p ON p.id = n.project_id
        LEFT JOIN users u ON u.id = n.reported_by
        LEFT JOIN users u2 ON u2.id = n.assigned_to
        LEFT JOIN users u3 ON u3.id = n.closed_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (n.ncr_number LIKE :search OR n.description LIKE :search OR n.nonconformance LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND n.status = :status";
    $params[':status'] = $status;
}

if ($severity) {
    $sql .= " AND n.severity = :severity";
    $params[':severity'] = $severity;
}

if ($project_id > 0) {
    $sql .= " AND n.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

$sql .= " ORDER BY n.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$ncrs = $result['data'];
$totalPages = $result['total_pages'];

// دریافت لیست پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects WHERE status != 'cancelled' ORDER BY created_at DESC LIMIT 50");

// آمار کلی
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
        SUM(CASE WHEN status = 'in_correction' THEN 1 ELSE 0 END) as in_correction,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical
    FROM ncrs
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لیست NCR - <?php echo SITE_TITLE; ?></title>
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
        
        .stat-icon.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.open { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.investigating { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.correction { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-icon.closed { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .stat-icon.critical { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
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
            border-color: #f5576c;
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            background: #fff5f7;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-open {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-investigating {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-in_correction {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-closed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .severity-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .severity-critical {
            background: #dc3545;
            color: white;
        }
        
        .severity-major {
            background: #fd7e14;
            color: white;
        }
        
        .severity-minor {
            background: #ffc107;
            color: #212529;
        }
        
        .severity-observation {
            background: #28a745;
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
        
        .btn-print {
            background: #ff9800;
            color: white;
        }
        
        .btn-close {
            background: #9c27b0;
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
            border: 2px solid #f5576c;
            border-radius: 6px;
            color: #f5576c;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 13px;
        }
        
        .page-link:hover,
        .page-link.active {
            background: #f5576c;
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
        
        .ncr-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .ncr-number {
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .ncr-desc {
            color: #7f8c8d;
            font-size: 11px;
            max-width: 250px;
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
            background: #ffe5ec;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: #c2185b;
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
                min-width: 1200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                ⚠️ لیست NCR (گزارش عدم انطباق)
            </h1>
            <?php if (check_permission('qc', PERMISSION_WRITE)): ?>
                <a href="ncr.php?action=add" class="btn btn-primary">
                    ➕ ثبت NCR جدید
                </a>
            <?php endif; ?>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon total">📊</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['total']); ?></h3>
                    <p>کل NCR ها</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon open">🔓</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['open']); ?></h3>
                    <p>باز</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon investigating">🔍</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['investigating']); ?></h3>
                    <p>در حال بررسی</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon correction">🔧</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['in_correction']); ?></h3>
                    <p>در حال اصلاح</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon closed">🔒</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['closed']); ?></h3>
                    <p>بسته شده</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon critical">🚨</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['critical']); ?></h3>
                    <p>بحرانی</p>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="شماره، توضیحات، عدم انطباق..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>باز</option>
                        <option value="investigating" <?php echo $status === 'investigating' ? 'selected' : ''; ?>>در حال بررسی</option>
                        <option value="in_correction" <?php echo $status === 'in_correction' ? 'selected' : ''; ?>>در حال اصلاح</option>
                        <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>بسته شده</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>شدت</label>
                    <select name="severity">
                        <option value="">همه</option>
                        <option value="critical" <?php echo $severity === 'critical' ? 'selected' : ''; ?>>بحرانی</option>
                        <option value="major" <?php echo $severity === 'major' ? 'selected' : ''; ?>>مهم</option>
                        <option value="minor" <?php echo $severity === 'minor' ? 'selected' : ''; ?>>جزئی</option>
                        <option value="observation" <?php echo $severity === 'observation' ? 'selected' : ''; ?>>مشاهده</option>
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
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($ncrs) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره NCR</th>
                            <th>شرح عدم انطباق</th>
                            <th>پروژه</th>
                            <th>شدت</th>
                            <th>وضعیت</th>
                            <th>گزارش‌دهنده</th>
                            <th>تخصیص به</th>
                            <th>تاریخ ثبت</th>
                            <th>تاریخ بستن</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ncrs as $ncr): ?>
                            <tr>
                                <td>
                                    <div class="ncr-info">
                                        <span class="ncr-number"><?php echo h($ncr['ncr_number']); ?></span>
                                        <?php if ($ncr['reference_number']): ?>
                                            <small style="color: #999;">مرجع: <?php echo h($ncr['reference_number']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="ncr-desc" title="<?php echo h($ncr['nonconformance']); ?>">
                                        <?php echo h($ncr['nonconformance']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($ncr['project_name']): ?>
                                        <div class="project-info">
                                            <span class="project-code"><?php echo h($ncr['project_code']); ?></span>
                                            <span><?php echo h(mb_substr($ncr['project_name'], 0, 20)); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $severityLabels = [
                                        'critical' => 'بحرانی',
                                        'major' => 'مهم',
                                        'minor' => 'جزئی',
                                        'observation' => 'مشاهده'
                                    ];
                                    ?>
                                    <span class="severity-badge severity-<?php echo $ncr['severity']; ?>">
                                        <?php echo $severityLabels[$ncr['severity']] ?? $ncr['severity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'open' => 'باز',
                                        'investigating' => 'در حال بررسی',
                                        'in_correction' => 'در حال اصلاح',
                                        'closed' => 'بسته شده',
                                        'rejected' => 'رد شده'
                                    ];
                                    $statusClass = 'badge-' . str_replace('_', '_', $ncr['status']);
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$ncr['status']] ?? $ncr['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px;"><?php echo h($ncr['reported_by_name']); ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 12px;">
                                        <?php echo $ncr['assigned_to_name'] ? h($ncr['assigned_to_name']) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #7f8c8d;">
                                        <?php echo en2fa(date('Y/m/d', strtotime($ncr['reported_date']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #7f8c8d;">
                                        <?php echo $ncr['closed_date'] ? en2fa(date('Y/m/d', strtotime($ncr['closed_date']))) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="ncr.php?action=view&id=<?php echo $ncr['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁️</a>
                                        <a href="ncr.php?action=print&id=<?php echo $ncr['id']; ?>" 
                                           class="btn-sm btn-print" title="چاپ" target="_blank">🖨️</a>
                                        <?php if (check_permission('qc', PERMISSION_WRITE) && $ncr['status'] !== 'closed'): ?>
                                            <a href="ncr.php?action=edit&id=<?php echo $ncr['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('qc', PERMISSION_FULL) && $ncr['status'] !== 'closed'): ?>
                                            <a href="ncr.php?action=close&id=<?php echo $ncr['id']; ?>" 
                                               class="btn-sm btn-close" title="بستن">🔒</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('qc', PERMISSION_FULL) && $ncr['status'] === 'open'): ?>
                                            <button onclick="deleteNCR(<?php echo $ncr['id']; ?>)" 
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&severity=<?php echo urlencode($severity); ?>&project_id=<?php echo $project_id; ?>" 
                               class="page-link">❮ قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&severity=<?php echo urlencode($severity); ?>&project_id=<?php echo $project_id; ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&severity=<?php echo urlencode($severity); ?>&project_id=<?php echo $project_id; ?>" 
                               class="page-link">بعدی ❯</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L2 7v10c0 5.5 3.8 10.7 10 12 6.2-1.3 10-6.5 10-12V7l-10-5zm0 18c-4.4-1.2-7.4-5.2-7.4-9.2V8.3l7.4-3.7 7.4 3.7v2.5c0 4-3 8-7.4 9.2z"/>
                    </svg>
                    <h3>هیچ گزارش عدم انطباق یافت نشد</h3>
                    <p>برای ثبت NCR جدید از دکمه بالای صفحه استفاده کنید.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function deleteNCR(id) {
            if (confirm('آیا از حذف این گزارش عدم انطباق اطمینان دارید؟\nتوجه: این عملیات قابل بازگشت نیست.')) {
                window.location.href = 'ncr.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>