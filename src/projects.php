<?php
/**
 * ماژول پروژه‌ها - لیست و مدیریت پروژه‌ها
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('projects', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$manager = sanitize_input($_GET['manager'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT p.*, 
        c.name as client_name,
        u.fullname as manager_name,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'done') as completed_tasks,
        (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense') as total_expense
        FROM projects p
        LEFT JOIN contacts c ON c.id = p.client_contact_id
        LEFT JOIN users u ON u.id = p.manager_user_id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (p.code LIKE :search OR p.title LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND p.status = :status";
    $params[':status'] = $status;
}

if ($manager) {
    $sql .= " AND p.manager_user_id = :manager";
    $params[':manager'] = $manager;
}

$sql .= " ORDER BY p.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$projects = $result['data'];
$totalPages = $result['total_pages'];

// دریافت مدیران برای فیلتر
$managers = db()->select(
    "SELECT DISTINCT u.id, u.fullname 
     FROM users u 
     INNER JOIN projects p ON p.manager_user_id = u.id 
     ORDER BY u.fullname"
);

// آمار کلی
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_projects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
        SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as onhold_projects,
        SUM(budget) as total_budget
    FROM projects
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پروژه‌ها - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1400px;
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
        
        .stats-container {
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
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.success { background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); }
        .stat-icon.warning { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); }
        .stat-icon.info { background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%); }
        
        .stat-content h3 {
            color: #666;
            font-size: 14px;
            font-weight: normal;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #2c3e50;
            font-size: 24px;
            font-weight: bold;
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
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .project-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .project-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .project-code {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .project-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .project-client {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .project-body {
            padding: 20px;
        }
        
        .project-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: bold;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50 0%, #45a049 100%);
            transition: width 0.3s;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-draft { background: #e0e0e0; color: #666; }
        .badge-planning { background: #fff3e0; color: #f57c00; }
        .badge-active { background: #e8f5e9; color: #388e3c; }
        .badge-on_hold { background: #ffe0b2; color: #e65100; }
        .badge-completed { background: #e3f2fd; color: #1976d2; }
        .badge-cancelled { background: #ffebee; color: #c62828; }
        
        .project-actions {
            display: flex;
            gap: 8px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
            flex: 1;
            justify-content: center;
        }
        
        .btn-view { background: #4caf50; color: white; }
        .btn-edit { background: #2196f3; color: white; }
        .btn-tasks { background: #ff9800; color: white; }
        
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
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            color: #999;
        }
        
        .no-data svg {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .projects-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏗️ مدیریت پروژه‌ها</h1>
            <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                <a href="project.php?action=add" class="btn btn-primary">
                    ➕ پروژه جدید
                </a>
            <?php endif; ?>
        </div>
        
        <!-- آمار -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon primary">📊</div>
                <div class="stat-content">
                    <h3>کل پروژه‌ها</h3>
                    <p><?php echo en2fa($stats['total_projects'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">✅</div>
                <div class="stat-content">
                    <h3>در حال اجرا</h3>
                    <p><?php echo en2fa($stats['active_projects'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">🎯</div>
                <div class="stat-content">
                    <h3>تکمیل شده</h3>
                    <p><?php echo en2fa($stats['completed_projects'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">💰</div>
                <div class="stat-content">
                    <h3>بودجه کل (میلیون ریال)</h3>
                    <p><?php echo en2fa(number_format(($stats['total_budget'] ?? 0) / 1000000, 0)); ?></p>
                </div>
            </div>
        </div>
        
        <!-- فیلترها -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="کد، عنوان، توضیحات..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="planning" <?php echo $status === 'planning' ? 'selected' : ''; ?>>برنامه‌ریزی</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>در حال اجرا</option>
                        <option value="on_hold" <?php echo $status === 'on_hold' ? 'selected' : ''; ?>>متوقف شده</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>مدیر پروژه</label>
                    <select name="manager">
                        <option value="">همه</option>
                        <?php foreach ($managers as $mgr): ?>
                            <option value="<?php echo $mgr['id']; ?>" 
                                    <?php echo $manager == $mgr['id'] ? 'selected' : ''; ?>>
                                <?php echo h($mgr['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <!-- لیست پروژه‌ها -->
        <?php if (count($projects) > 0): ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                    <?php
                    $progress = 0;
                    if ($project['task_count'] > 0) {
                        $progress = round(($project['completed_tasks'] / $project['task_count']) * 100);
                    }
                    
                    $statusLabels = [
                        'draft' => 'پیش‌نویس',
                        'planning' => 'برنامه‌ریزی',
                        'active' => 'در حال اجرا',
                        'on_hold' => 'متوقف',
                        'completed' => 'تکمیل شده',
                        'cancelled' => 'لغو شده'
                    ];
                    ?>
                    <div class="project-card">
                        <div class="project-header">
                            <div class="project-code"><?php echo h($project['code']); ?></div>
                            <div class="project-title"><?php echo h($project['title']); ?></div>
                            <div class="project-client">
                                👤 <?php echo h($project['client_name'] ?: 'مشتری تعیین نشده'); ?>
                            </div>
                        </div>
                        
                        <div class="project-body">
                            <div class="project-info">
                                <div class="info-row">
                                    <span class="info-label">وضعیت:</span>
                                    <span class="badge badge-<?php echo $project['status']; ?>">
                                        <?php echo $statusLabels[$project['status']]; ?>
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">مدیر پروژه:</span>
                                    <span class="info-value"><?php echo h($project['manager_name'] ?: '-'); ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">تاریخ شروع:</span>
                                    <span class="info-value"><?php echo $project['start_date'] ? en2fa($project['start_date']) : '-'; ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">وظایف:</span>
                                    <span class="info-value">
                                        <?php echo en2fa($project['completed_tasks']); ?> / 
                                        <?php echo en2fa($project['task_count']); ?>
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">پیشرفت:</span>
                                    <span class="info-value"><?php echo en2fa($progress); ?>%</span>
                                </div>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            
                            <div class="project-actions">
                                <a href="project.php?action=view&id=<?php echo $project['id']; ?>" 
                                   class="btn-sm btn-view">👁 مشاهده</a>
                                
                                <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                                    <a href="project.php?action=edit&id=<?php echo $project['id']; ?>" 
                                       class="btn-sm btn-edit">✏️ ویرایش</a>
                                <?php endif; ?>
                                
                                <a href="tasks.php?project_id=<?php echo $project['id']; ?>" 
                                   class="btn-sm btn-tasks">📋 وظایف</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&manager=<?php echo urlencode($manager); ?>" 
                           class="page-link">قبلی</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&manager=<?php echo urlencode($manager); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo en2fa($i); ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&manager=<?php echo urlencode($manager); ?>" 
                           class="page-link">بعدی</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-data">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                </svg>
                <h2>هیچ پروژه‌ای یافت نشد</h2>
                <p>برای شروع، اولین پروژه خود را ایجاد کنید</p>
                <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                    <a href="project.php?action=add" class="btn btn-primary" style="margin-top: 20px;">
                        ➕ ایجاد پروژه جدید
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>