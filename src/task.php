<?php
/**
 * فرم وظیفه - افزودن، ویرایش، مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = sanitize_input($_GET['action'] ?? 'add');
$taskId = (int)($_GET['id'] ?? 0);
$projectId = (int)($_GET['project_id'] ?? 0);

// چک مجوز
if (!check_permission('projects', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

if (($action === 'add' || $action === 'edit') && !check_permission('projects', PERMISSION_WRITE)) {
    die('شما مجوز ایجاد/ویرایش وظیفه را ندارید.');
}

$task = null;
$project = null;
$error = '';
$success = '';

// بارگذاری اطلاعات وظیفه
if (($action === 'edit' || $action === 'view') && $taskId) {
    $task = db()->selectOne("SELECT * FROM tasks WHERE id = :id", [':id' => $taskId]);
    if (!$task) {
        die('وظیفه یافت نشد.');
    }
    $projectId = $task['project_id'];
}

// بارگذاری اطلاعات پروژه
if ($projectId) {
    $project = db()->selectOne("SELECT * FROM projects WHERE id = :id", [':id' => $projectId]);
    if (!$project) {
        die('پروژه یافت نشد.');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی.';
    } else {
        $data = [
            'project_id' => (int)($_POST['project_id'] ?? 0),
            'title' => sanitize_input($_POST['title'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'assigned_to' => (int)($_POST['assigned_to'] ?? 0) ?: null,
            'status' => sanitize_input($_POST['status'] ?? 'todo'),
            'priority' => sanitize_input($_POST['priority'] ?? 'medium'),
            'start_date' => sanitize_input($_POST['start_date'] ?? '') ?: null,
            'due_date' => sanitize_input($_POST['due_date'] ?? '') ?: null,
            'progress' => (int)($_POST['progress'] ?? 0),
            'estimated_hours' => (float)($_POST['estimated_hours'] ?? 0) ?: null,
            'parent_task_id' => (int)($_POST['parent_task_id'] ?? 0) ?: null
        ];
        
        // اعتبارسنجی
        if (empty($data['project_id'])) {
            $error = 'پروژه باید انتخاب شود.';
        } elseif (empty($data['title'])) {
            $error = 'عنوان وظیفه الزامی است.';
        } elseif ($data['start_date'] && $data['due_date'] && $data['start_date'] > $data['due_date']) {
            $error = 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.';
        } else {
            if ($action === 'add') {
                $data['created_by'] = $_SESSION['user_id'];
                $newId = db()->insert('tasks', $data);
                
                if ($newId) {
                    db()->insert('logs', [
                        'user_id' => $_SESSION['user_id'],
                        'action' => 'create',
                        'module' => 'tasks',
                        'record_id' => $newId,
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    redirect(SITE_URL . '/task.php?action=view&id=' . $newId . '&success=1');
                } else {
                    $error = 'خطا در ایجاد وظیفه.';
                }
            } else {
                // به‌روزرسانی تاریخ تکمیل
                if ($data['status'] === 'done' && empty($task['completed_date'])) {
                    $data['completed_date'] = date('Y-m-d');
                }
                
                $updated = db()->update('tasks', $data, 'id = :id', [':id' => $taskId]);
                
                if ($updated !== false) {
                    db()->insert('logs', [
                        'user_id' => $_SESSION['user_id'],
                        'action' => 'update',
                        'module' => 'tasks',
                        'record_id' => $taskId,
                        'old_data' => json_encode($task),
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $success = 'وظیفه با موفقیت به‌روزرسانی شد.';
                    $task = db()->selectOne("SELECT * FROM tasks WHERE id = :id", [':id' => $taskId]);
                } else {
                    $error = 'خطا در به‌روزرسانی وظیفه.';
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = 'وظیفه با موفقیت ایجاد شد.';
}

// لیست پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects WHERE status != 'cancelled' ORDER BY code");

// لیست کاربران
$users = db()->select("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname");

// لیست وظایف والد (برای زیروظایف)
$parentTasks = [];
if ($projectId) {
    $parentTasks = db()->select(
        "SELECT id, title FROM tasks WHERE project_id = :pid AND id != :tid AND parent_task_id IS NULL ORDER BY title",
        [':pid' => $projectId, ':tid' => $taskId]
    );
}

// زیروظایف
$subtasks = [];
if ($taskId) {
    $subtasks = db()->select(
        "SELECT t.*, u.fullname as assigned_name 
         FROM tasks t 
         LEFT JOIN users u ON u.id = t.assigned_to 
         WHERE t.parent_task_id = :tid 
         ORDER BY t.created_at",
        [':tid' => $taskId]
    );
}

// گزارش‌های کار
$workReports = [];
if ($taskId) {
    $workReports = db()->select(
        "SELECT w.*, u.fullname as creator_name 
         FROM work_reports w 
         LEFT JOIN users u ON u.id = w.created_by 
         WHERE w.task_id = :tid 
         ORDER BY w.report_date DESC 
         LIMIT 5",
        [':tid' => $taskId]
    );
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'وظیفه جدید' : ($action === 'view' ? 'مشاهده وظیفه' : 'ویرایش وظیفه'); ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1200px;
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
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tab-links {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            overflow-x: auto;
        }
        
        .tab-link {
            padding: 15px 25px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            transition: all 0.3s;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
        }
        
        .tab-link:hover {
            color: #667eea;
            background: #f8f9fa;
        }
        
        .tab-link.active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
            font-weight: bold;
        }
        
        .form-group label span {
            color: #f44336;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 30px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .list-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .list-item-content h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .list-item-content p {
            color: #666;
            font-size: 13px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-todo { background: #e0e0e0; color: #666; }
        .badge-in_progress { background: #fff3e0; color: #f57c00; }
        .badge-review { background: #e3f2fd; color: #1976d2; }
        .badge-done { background: #e8f5e9; color: #388e3c; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="projects.php">پروژه‌ها</a>
                <?php if ($project): ?>
                    <span>›</span>
                    <a href="project.php?action=view&id=<?php echo $project['id']; ?>"><?php echo h($project['title']); ?></a>
                    <span>›</span>
                    <a href="tasks.php?project_id=<?php echo $project['id']; ?>">وظایف</a>
                <?php endif; ?>
                <span>›</span>
                <span><?php echo $action === 'add' ? 'وظیفه جدید' : h($task['title'] ?? ''); ?></span>
            </div>
            <h1>
                <?php 
                if ($action === 'add') {
                    echo '➕ وظیفه جدید';
                } elseif ($action === 'view') {
                    echo '👁 مشاهده وظیفه';
                } else {
                    echo '✏️ ویرایش وظیفه';
                }
                ?>
            </h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="tabs">
                <div class="tab-links">
                    <button type="button" class="tab-link active" onclick="switchTab('general')">📋 اطلاعات کلی</button>
                    <?php if ($taskId): ?>
                        <button type="button" class="tab-link" onclick="switchTab('subtasks')">📌 زیروظایف (<?php echo en2fa(count($subtasks)); ?>)</button>
                        <button type="button" class="tab-link" onclick="switchTab('reports')">📝 گزارش‌های کار (<?php echo en2fa(count($workReports)); ?>)</button>
                    <?php endif; ?>
                </div>
                
                <!-- تب اطلاعات کلی -->
                <div class="tab-content active" id="tab-general">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>پروژه <span>*</span></label>
                            <select name="project_id" required <?php echo $action === 'view' || $taskId ? 'disabled' : ''; ?>>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?php echo $proj['id']; ?>" 
                                            <?php echo ($task['project_id'] ?? $projectId) == $proj['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($proj['code'] . ' - ' . $proj['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($taskId): ?>
                                <input type="hidden" name="project_id" value="<?php echo $task['project_id']; ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>وظیفه والد (اختیاری)</label>
                            <select name="parent_task_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">وظیفه اصلی</option>
                                <?php foreach ($parentTasks as $pt): ?>
                                    <option value="<?php echo $pt['id']; ?>" 
                                            <?php echo ($task['parent_task_id'] ?? 0) == $pt['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($pt['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>عنوان وظیفه <span>*</span></label>
                            <input type="text" name="title" required 
                                   value="<?php echo h($task['title'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>مسئول</label>
                            <select name="assigned_to" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" 
                                            <?php echo ($task['assigned_to'] ?? 0) == $u['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($u['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>وضعیت <span>*</span></label>
                            <select name="status" required <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="todo" <?php echo ($task['status'] ?? 'todo') === 'todo' ? 'selected' : ''; ?>>انجام نشده</option>
                                <option value="in_progress" <?php echo ($task['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                                <option value="review" <?php echo ($task['status'] ?? '') === 'review' ? 'selected' : ''; ?>>در بررسی</option>
                                <option value="done" <?php echo ($task['status'] ?? '') === 'done' ? 'selected' : ''; ?>>انجام شده</option>
                                <option value="cancelled" <?php echo ($task['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>اولویت <span>*</span></label>
                            <select name="priority" required <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="low" <?php echo ($task['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>کم</option>
                                <option value="medium" <?php echo ($task['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                                <option value="high" <?php echo ($task['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>بالا</option>
                                <option value="urgent" <?php echo ($task['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ شروع</label>
                            <input type="date" name="start_date" 
                                   value="<?php echo h($task['start_date'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>مهلت انجام</label>
                            <input type="date" name="due_date" 
                                   value="<?php echo h($task['due_date'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>پیشرفت (درصد)</label>
                            <input type="number" name="progress" min="0" max="100" 
                                   value="<?php echo h($task['progress'] ?? 0); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>تخمین زمان (ساعت)</label>
                            <input type="number" name="estimated_hours" step="0.5" min="0"
                                   value="<?php echo h($task['estimated_hours'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>شرح وظیفه</label>
                            <textarea name="description" <?php echo $action === 'view' ? 'disabled' : ''; ?>><?php echo h($task['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <?php if ($action !== 'view'): ?>
                        <div class="form-actions">
                            <a href="<?php echo $projectId ? 'tasks.php?project_id=' . $projectId : 'tasks.php'; ?>" class="btn btn-secondary">↩ بازگشت</a>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '➕ ایجاد وظیفه' : '💾 ذخیره تغییرات'; ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- تب زیروظایف -->
                <?php if ($taskId): ?>
                    <div class="tab-content" id="tab-subtasks">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>زیروظایف</h3>
                            <a href="task.php?action=add&project_id=<?php echo $projectId; ?>&parent_id=<?php echo $taskId; ?>" class="btn btn-primary">
                                ➕ زیروظیفه جدید
                            </a>
                        </div>
                        
                        <?php if (count($subtasks) > 0): ?>
                            <?php foreach ($subtasks as $st): ?>
                                <div class="list-item">
                                    <div class="list-item-content">
                                        <h4><?php echo h($st['title']); ?></h4>
                                        <p>مسئول: <?php echo h($st['assigned_name'] ?: 'تعیین نشده'); ?></p>
                                    </div>
                                    <div>
                                        <?php
                                        $statuses = ['todo' => 'انجام نشده', 'in_progress' => 'در حال انجام', 'review' => 'بررسی', 'done' => 'انجام شده'];
                                        ?>
                                        <span class="badge badge-<?php echo $st['status']; ?>">
                                            <?php echo $statuses[$st['status']]; ?>
                                        </span>
                                        <a href="task.php?action=view&id=<?php echo $st['id']; ?>" style="margin-right: 10px;">مشاهده</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>این وظیفه زیروظیفه‌ای ندارد.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- تب گزارش‌های کار -->
                    <div class="tab-content" id="tab-reports">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>گزارش‌های کار</h3>
                            <a href="work_report.php?task_id=<?php echo $taskId; ?>" class="btn btn-primary">
                                ➕ گزارش جدید
                            </a>
                        </div>
                        
                        <?php if (count($workReports) > 0): ?>
                            <?php foreach ($workReports as $wr): ?>
                                <div class="list-item">
                                    <div class="list-item-content">
                                        <h4><?php echo en2fa($wr['report_date']); ?> - <?php echo h($wr['creator_name']); ?></h4>
                                        <p><?php echo h(mb_substr($wr['work_description'], 0, 100)); ?>...</p>
                                        <p><strong>ساعات کار:</strong> <?php echo en2fa($wr['hours_spent']); ?> ساعت | 
                                           <strong>پیشرفت:</strong> <?php echo en2fa($wr['progress_percentage']); ?>%</p>
                                    </div>
                                    <div>
                                        <a href="work_report.php?id=<?php echo $wr['id']; ?>">مشاهده</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <a href="work_report.php?task_id=<?php echo $taskId; ?>" class="btn btn-secondary" style="margin-top: 15px;">
                                مشاهده همه گزارش‌ها
                            </a>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>هیچ گزارش کاری برای این وظیفه ثبت نشده است.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <script>
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const links = document.querySelectorAll('.tab-link');
            links.forEach(link => link.classList.remove('active'));
            
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>