<?php
/**
 * مرکز کنترل کیفیت - QC Dashboard
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('qc', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پیام‌ها
$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = show_message('فرم کنترل کیفیت با موفقیت ثبت شد.', 'success');
            break;
        case 'updated':
            $message = show_message('فرم کنترل کیفیت با موفقیت به‌روزرسانی شد.', 'success');
            break;
        case 'approved':
            $message = show_message('فرم کنترل کیفیت تایید شد.', 'success');
            break;
    }
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$result = sanitize_input($_GET['result'] ?? '');
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT q.*,
        p.title as project_title,
        p.code as project_code,
        pr.name as product_name,
        u1.fullname as inspector_name,
        u2.fullname as creator_name
        FROM qc_forms q
        LEFT JOIN projects p ON p.id = q.project_id
        LEFT JOIN products pr ON pr.id = q.product_id
        LEFT JOIN users u1 ON u1.id = q.inspector_user_id
        LEFT JOIN users u2 ON u2.id = q.created_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (q.form_number LIKE :search OR q.title LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND q.status = :status";
    $params[':status'] = $status;
}

if ($result) {
    $sql .= " AND q.result = :result";
    $params[':result'] = $result;
}

if ($projectId) {
    $sql .= " AND q.project_id = :project_id";
    $params[':project_id'] = $projectId;
}

$sql .= " ORDER BY q.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$resultData = db()->paginate($sql, $params, $page, $perPage);
$qcForms = $resultData['data'];
$totalPages = $resultData['total_pages'];

// آمار
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN result = 'pass' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN result = 'fail' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN result = 'conditional' THEN 1 ELSE 0 END) as conditional
    FROM qc_forms
");

// دریافت پروژه‌ها برای فیلتر
$projects = db()->select(
    "SELECT DISTINCT p.id, p.code, p.title 
     FROM projects p
     INNER JOIN qc_forms q ON q.project_id = p.id
     ORDER BY p.title"
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کنترل کیفیت - <?php echo SITE_TITLE; ?></title>
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
        
        .stats-grid {
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
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            font-size: 36px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        .stat-icon.total { background: #e3f2fd; }
        .stat-icon.open { background: #fff3e0; }
        .stat-icon.progress { background: #e1f5fe; }
        .stat-icon.completed { background: #f3e5f5; }
        .stat-icon.passed { background: #e8f5e9; }
        .stat-icon.failed { background: #ffebee; }
        .stat-icon.conditional { background: #fff9c4; }
        
        .stat-content h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #666;
            font-size: 14px;
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
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-open { background: #fff3cd; color: #856404; }
        .badge-in_progress { background: #cce5ff; color: #004085; }
        .badge-completed { background: #f3e5f5; color: #7b1fa2; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        
        .badge-pass { background: #d4edda; color: #155724; }
        .badge-fail { background: #f8d7da; color: #721c24; }
        .badge-conditional { background: #fff3cd; color: #856404; }
        
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
        
        .btn-edit {
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
                min-width: 1200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ کنترل کیفیت (QC)</h1>
            <?php if (check_permission('qc', PERMISSION_WRITE)): ?>
                <a href="qcform.php?action=add" class="btn btn-primary">
                    ➕ فرم بازرسی جدید
                </a>
            <?php endif; ?>
        </div>
        
        <?php echo $message; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">📋</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['total'] ?? 0); ?></h3>
                    <p>کل فرم‌ها</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon open">📝</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['open'] ?? 0); ?></h3>
                    <p>باز</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon progress">⏳</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['in_progress'] ?? 0); ?></h3>
                    <p>در حال انجام</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon completed">✔️</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['completed'] ?? 0); ?></h3>
                    <p>تکمیل شده</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon passed">✅</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['passed'] ?? 0); ?></h3>
                    <p>قبول</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon failed">❌</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['failed'] ?? 0); ?></h3>
                    <p>رد</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon conditional">⚠️</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['conditional'] ?? 0); ?></h3>
                    <p>مشروط</p>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="شماره فرم، عنوان..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>باز</option>
                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>نتیجه بازرسی</label>
                    <select name="result">
                        <option value="">همه</option>
                        <option value="pass" <?php echo $result === 'pass' ? 'selected' : ''; ?>>قبول</option>
                        <option value="fail" <?php echo $result === 'fail' ? 'selected' : ''; ?>>رد</option>
                        <option value="conditional" <?php echo $result === 'conditional' ? 'selected' : ''; ?>>مشروط</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>پروژه</label>
                    <select name="project_id">
                        <option value="">همه پروژه‌ها</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" 
                                    <?php echo $projectId == $project['id'] ? 'selected' : ''; ?>>
                                <?php echo h($project['code']); ?> - <?php echo h($project['title']); ?>
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
            <?php if (count($qcForms) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره فرم</th>
                            <th>نوع</th>
                            <th>عنوان</th>
                            <th>پروژه</th>
                            <th>محصول</th>
                            <th>بازرس</th>
                            <th>تاریخ بازرسی</th>
                            <th>وضعیت</th>
                            <th>نتیجه</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($qcForms as $form): ?>
                            <tr>
                                <td><strong><?php echo h($form['form_number']); ?></strong></td>
                                <td><?php echo h($form['type']); ?></td>
                                <td><?php echo h($form['title']); ?></td>
                                <td>
                                    <?php if ($form['project_code']): ?>
                                        <?php echo h($form['project_code']); ?><br>
                                        <small style="color: #999;"><?php echo h($form['project_title']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($form['product_name'] ?: '-'); ?></td>
                                <td><?php echo h($form['inspector_name'] ?: '-'); ?></td>
                                <td><?php echo $form['inspection_date'] ? en2fa($form['inspection_date']) : '-'; ?></td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'open' => 'باز',
                                        'in_progress' => 'در حال انجام',
                                        'completed' => 'تکمیل شده',
                                        'approved' => 'تایید شده',
                                        'rejected' => 'رد شده'
                                    ];
                                    $statusClass = 'badge-' . $form['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$form['status']] ?? $form['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($form['result']): ?>
                                        <?php
                                        $resultLabels = [
                                            'pass' => 'قبول',
                                            'fail' => 'رد',
                                            'conditional' => 'مشروط'
                                        ];
                                        $resultClass = 'badge-' . $form['result'];
                                        ?>
                                        <span class="badge <?php echo $resultClass; ?>">
                                            <?php echo $resultLabels[$form['result']] ?? $form['result']; ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="qcform.php?action=view&id=<?php echo $form['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('qc', PERMISSION_WRITE)): ?>
                                            <a href="qcform.php?action=edit&id=<?php echo $form['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&result=<?php echo urlencode($result); ?>&project_id=<?php echo $projectId; ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&result=<?php echo urlencode($result); ?>&project_id=<?php echo $projectId; ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&result=<?php echo urlencode($result); ?>&project_id=<?php echo $projectId; ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ فرم کنترل کیفیتی یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>