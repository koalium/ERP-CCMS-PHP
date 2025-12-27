<?php
/**
 * ماژول وظایف - لیست وظایف پروژه
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('projects', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$projectId = (int)($_GET['project_id'] ?? 0);
$status = sanitize_input($_GET['status'] ?? '');
$assigned = sanitize_input($_GET['assigned'] ?? '');
$priority = sanitize_input($_GET['priority'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// بارگذاری اطلاعات پروژه
$project = null;
if ($projectId) {
    $project = db()->selectOne("SELECT * FROM projects WHERE id = :id", [':id' => $projectId]);
    if (!$project) {
        die('پروژه یافت نشد.');
    }
}

// ساخت کوئری
$sql = "SELECT t.*, 
        p.title as project_title, p.code as project_code,
        u1.fullname as assigned_name,
        u2.fullname as creator_name,
        (SELECT COUNT(*) FROM tasks WHERE parent_task_id = t.id) as subtask_count
        FROM tasks t
        LEFT JOIN projects p ON p.id = t.project_id
        LEFT JOIN users u1 ON u1.id = t.assigned_to
        LEFT JOIN users u2 ON u2.id = t.created_by
        WHERE 1=1";

$params = [];

if ($projectId) {
    $sql .= " AND t.project_id = :project_id";
    $params[':project_id'] = $projectId;
}

if ($status) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $status;
}

if ($assigned) {
    $sql .= " AND t.assigned_to = :assigned";
    $params[':assigned'] = $assigned;
}

if ($priority) {
    $sql .= " AND t.priority = :priority";
    $params[':priority'] = $priority;
}

$sql .= " ORDER BY 
    CASE t.priority
        WHEN 'urgent' THEN 1
        WHEN 'high' THEN 2
        WHEN 'medium' THEN 3
        WHEN 'low' THEN 4
    END,
    t.due_date ASC,
    t.created_at DESC";

// دریافت داده‌ها
$result = db()->paginate($sql, $params, $page, $perPage);
$tasks = $result['data'];
$totalPages = $result['total_pages'];

// لیست کاربران برای فیلتر
$users = db()->select("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname");

// آمار وظایف
$statsParams = $projectId ? [':project_id' => $projectId] : [];
$statsWhere = $projectId ? "WHERE project_id = :project_id" : "";

$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('done', 'cancelled') THEN 1 ELSE 0 END) as overdue
    FROM tasks
    $statsWhere
", $statsParams);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $project ? 'وظایف پروژه ' . h($project['title']) : 'وظایف'; ?> - <?php echo SITE_TITLE; ?></title>
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
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .stat-item.overdue {
            background: #ffebee;
            color: #c62828;
        }
        
        .stat-item.overdue .stat-number {
            color: #c62828;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .tasks-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .task-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        .task-item:hover {
            background: #f8f9fa;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 15px;
        }
        
        .task-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .task-project {
            font-size: 12px;
            color: #666;
        }
        
        .task-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .badge-todo { background: #e0e0e0; color: #666; }
        .badge-in_progress { background: #fff3e0; color: #f57c00; }
        .badge-review { background: #e3f2fd; color: #1976d2; }
        .badge-done { background: #e8f5e9; color: #388e3c; }
        .badge-cancelled { background: #ffebee; color: #c62828; }
        
        .badge-low { background: #e3f2fd; color: #1976d2; }
        .badge-medium { background: #fff3e0; color: #f57c00; }
        .badge-high { background: #ffe0b2; color: #e65100; }
        .badge-urgent { background: #ffebee; color: #c62828; }
        
        .task-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 12px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .info-item strong {
            color: #2c3e50;
        }
        
        .task-progress {
            margin-top: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50 0%, #45a049 100%);
            transition: width 0.3s;
        }
        
        .progress-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .task-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn-sm {
            padding: 6px 15px;
            font-size: 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        
        .btn-view { background: #4caf50; color: white; }
        .btn-edit { background: #2196f3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .overdue-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #c62828;
            font-size: 12px;
            font-weight: bold;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
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
            .header-top {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .task-header {
                flex-direction: column;
            }
            
            .task-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <div>
                    <?php if ($project): ?>
                        <div class="breadcrumb">
                            <a href="projects.php">پروژه‌ها</a>
                            <span>›</span>
                            <a href="project.php?action=view&id=<?php echo $projectId; ?>"><?php echo h($project['title']); ?></a>
                            <span>›</span>
                            <span>وظایف</span>
                        </div>
                    <?php endif; ?>
                    <h1>📋 <?php echo $project ? 'وظایف پروژه' : 'همه وظایف'; ?></h1>
                </div>
                <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                    <a href="task.php?action=add<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" 
                       class="btn btn-primary">
                        ➕ وظیفه جدید
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number"><?php echo en2fa($stats['total'] ?? 0); ?></div>
                    <div class="stat-label">کل وظایف</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo en2fa($stats['todo'] ?? 0); ?></div>
                    <div class="stat-label">انجام نشده</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo en2fa($stats['in_progress'] ?? 0); ?></div>
                    <div class="stat-label">در حال انجام</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo en2fa($stats['review'] ?? 0); ?></div>
                    <div class="stat-label">در بررسی</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo en2fa($stats['done'] ?? 0); ?></div>
                    <div class="stat-label">انجام شده</div>
                </div>
                <div class="stat-item overdue">
                    <div class="stat-number"><?php echo en2fa($stats['overdue'] ?? 0); ?></div>
                    <div class="stat-label">⚠ عقب‌افتاده</div>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <?php if ($projectId): ?>
                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="todo" <?php echo $status === 'todo' ? 'selected' : ''; ?>>انجام نشده</option>
                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                        <option value="review" <?php echo $status === 'review' ? 'selected' : ''; ?>>در بررسی</option>
                        <option value="done" <?php echo $status === 'done' ? 'selected' : ''; ?>>انجام شده</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>اولویت</label>
                    <select name="priority">
                        <option value="">همه</option>
                        <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>کم</option>
                        <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                        <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>بالا</option>
                        <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>مسئول</label>
                    <select name="assigned">
                        <option value="">همه</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $assigned == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo h($user['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 فیلتر</button>
                </div>
            </form>
        </div>
        
        <?php if (count($tasks) > 0): ?>
            <div class="tasks-container">
                <?php 
                $statusLabels = [
                    'todo' => 'انجام نشده',
                    'in_progress' => 'در حال انجام',
                    'review' => 'در بررسی',
                    'done' => 'انجام شده',
                    'cancelled' => 'لغو شده'
                ];
                
                $priorityLabels = [
                    'low' => 'کم',
                    'medium' => 'متوسط',
                    'high' => 'بالا',
                    'urgent' => 'فوری'
                ];
                
                foreach ($tasks as $task): 
                    $isOverdue = $task['due_date'] && 
                                 strtotime($task['due_date']) < time() && 
                                 !in_array($task['status'], ['done', 'cancelled']);
                ?>
                    <div class="task-item">
                        <div class="task-header">
                            <div>
                                <div class="task-title">
                                    <?php echo h($task['title']); ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="overdue-indicator">⚠ عقب‌افتاده</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$projectId): ?>
                                    <div class="task-project">
                                        پروژه: <?php echo h($task['project_code'] . ' - ' . $task['project_title']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="task-badges">
                                <span class="badge badge-<?php echo $task['status']; ?>">
                                    <?php echo $statusLabels[$task['status']]; ?>
                                </span>
                                <span class="badge badge-<?php echo $task['priority']; ?>">
                                    <?php echo $priorityLabels[$task['priority']]; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="task-info">
                            <div class="info-item">
                                👤 مسئول: <strong><?php echo h($task['assigned_name'] ?: 'تعیین نشده'); ?></strong>
                            </div>
                            <?php if ($task['start_date']): ?>
                                <div class="info-item">
                                    📅 شروع: <strong><?php echo en2fa($task['start_date']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ($task['due_date']): ?>
                                <div class="info-item">
                                    🎯 مهلت: <strong><?php echo en2fa($task['due_date']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ($task['subtask_count'] > 0): ?>
                                <div class="info-item">
                                    📋 زیروظایف: <strong><?php echo en2fa($task['subtask_count']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($task['progress'] > 0): ?>
                            <div class="task-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $task['progress']; ?>%"></div>
                                </div>
                                <div class="progress-label">پیشرفت: <?php echo en2fa($task['progress']); ?>%</div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="task-actions">
                            <a href="task.php?action=view&id=<?php echo $task['id']; ?>" class="btn-sm btn-view">
                                👁 مشاهده
                            </a>
                            <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                                <a href="task.php?action=edit&id=<?php echo $task['id']; ?>" class="btn-sm btn-edit">
                                    ✏️ ویرایش
                                </a>
                            <?php endif; ?>
                            <a href="work_report.php?task_id=<?php echo $task['id']; ?>" class="btn-sm" style="background: #ff9800; color: white;">
                                📝 گزارش کار
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&project_id=<?php echo $projectId; ?>&status=<?php echo urlencode($status); ?>&assigned=<?php echo urlencode($assigned); ?>&priority=<?php echo urlencode($priority); ?>" 
                           class="page-link">قبلی</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&project_id=<?php echo $projectId; ?>&status=<?php echo urlencode($status); ?>&assigned=<?php echo urlencode($assigned); ?>&priority=<?php echo urlencode($priority); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo en2fa($i); ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&project_id=<?php echo $projectId; ?>&status=<?php echo urlencode($status); ?>&assigned=<?php echo urlencode($assigned); ?>&priority=<?php echo urlencode($priority); ?>" 
                           class="page-link">بعدی</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="tasks-container">
                <div class="no-data">
                    <svg width="120" height="120" viewBox="0 0 24 24" fill="currentColor" opacity="0.3">
                        <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                    <h2>هیچ وظیفه‌ای یافت نشد</h2>
                    <p>برای شروع، اولین وظیفه خود را ایجاد کنید</p>
                    <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                        <a href="task.php?action=add<?php echo $projectId ? '&project_id=' . $projectId : ''; ?>" 
                           class="btn btn-primary" style="margin-top: 20px;">
                            ➕ ایجاد وظیفه جدید
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>