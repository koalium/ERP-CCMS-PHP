<?php
/**
 * داشبورد اصلی کاربر
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

// دریافت آمار کلی
$stats = [
    'contacts' => db()->count('contacts', 'is_active = 1'),
    'projects' => db()->count('projects', "status IN ('active', 'planning')"),
    'tasks_pending' => db()->count('tasks', "status IN ('todo', 'in_progress') AND assigned_to = :user_id", [':user_id' => $_SESSION['user_id']]),
    'messages_unread' => db()->count('messages', 'receiver_id = :user_id AND is_read = 0', [':user_id' => $_SESSION['user_id']]),
];

// وظایف امروز
$todayTasks = db()->select(
    "SELECT t.*, p.title as project_title 
     FROM tasks t 
     LEFT JOIN projects p ON p.id = t.project_id
     WHERE t.assigned_to = :user_id 
     AND t.status IN ('todo', 'in_progress')
     AND t.due_date = CURDATE()
     ORDER BY t.priority DESC
     LIMIT 5",
    [':user_id' => $_SESSION['user_id']]
);

// پروژه‌های فعال
$activeProjects = db()->select(
    "SELECT p.*, 
     (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'done') as completed_tasks,
     (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks
     FROM projects p
     WHERE p.status = 'active'
     ORDER BY p.created_at DESC
     LIMIT 5"
);

// رویدادهای امروز
$todayEvents = db()->select(
    "SELECT * FROM calendar_events 
     WHERE user_id = :user_id 
     AND start_date = CURDATE()
     ORDER BY start_time",
    [':user_id' => $_SESSION['user_id']]
);

// یادآورهای امروز
$todayReminders = db()->select(
    "SELECT * FROM reminders 
     WHERE user_id = :user_id 
     AND remind_date = CURDATE()
     AND is_sent = 0
     ORDER BY remind_time",
    [':user_id' => $_SESSION['user_id']]
);

// آخرین فعالیت‌ها
$recentLogs = db()->select(
    "SELECT l.*, u.fullname 
     FROM logs l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC
     LIMIT 10"
);

// تراکنش‌های مالی اخیر (اگر مجوز دارد)
$recentTransactions = [];
if (check_permission('financial', PERMISSION_READ)) {
    $recentTransactions = db()->select(
        "SELECT t.*, 
         fa.name as from_account, 
         ta.name as to_account
         FROM transactions t
         LEFT JOIN accounts fa ON fa.id = t.from_account_id
         LEFT JOIN accounts ta ON ta.id = t.to_account_id
         ORDER BY t.transaction_date DESC
         LIMIT 5"
    );
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - <?php echo SITE_TITLE; ?></title>
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-section h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 48px;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        .stat-icon.blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-icon.green {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
        }
        
        .stat-icon.orange {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }
        
        .stat-icon.red {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }
        
        .stat-details h3 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        /* Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-body.no-padding {
            padding: 0;
        }
        
        /* Task List */
        .task-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        
        .task-item:hover {
            background: #f8f9fa;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-info h4 {
            font-size: 14px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .task-info p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .priority-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .priority-urgent {
            background: #fee;
            color: #c33;
        }
        
        .priority-high {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .priority-medium {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .priority-low {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        /* Project Progress */
        .project-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .project-item:last-child {
            border-bottom: none;
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .project-title {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .project-progress {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50 0%, #45a049 100%);
            transition: width 0.3s;
        }
        
        /* Event List */
        .event-item {
            padding: 12px 15px;
            border-right: 4px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 6px;
        }
        
        .event-time {
            font-weight: bold;
            color: #667eea;
            font-size: 13px;
        }
        
        .event-title {
            color: #2c3e50;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .quick-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .quick-btn:hover {
            transform: translateY(-2px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-section h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>سلام، <?php echo h($_SESSION['fullname']); ?>! 👋</h1>
            <p>امروز <?php echo en2fa(date('l، d F Y')); ?> است</p>
            
            <div class="quick-actions">
                <a href="tasks.php?action=add" class="quick-btn">➕ وظیفه جدید</a>
                <a href="calendar.php" class="quick-btn">📅 مشاهده تقویم</a>
                <a href="notes.php" class="quick-btn">📝 یادداشت جدید</a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📇</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($stats['contacts']); ?></h3>
                    <p>مخاطبین فعال</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">📊</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($stats['projects']); ?></h3>
                    <p>پروژه‌های فعال</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">📋</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($stats['tasks_pending']); ?></h3>
                    <p>وظایف در انتظار</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">💬</div>
                <div class="stat-details">
                    <h3><?php echo en2fa($stats['messages_unread']); ?></h3>
                    <p>پیام‌های خوانده نشده</p>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Today's Tasks -->
            <div class="dashboard-card">
                <div class="card-header">
                    📋 وظایف امروز
                    <a href="tasks.php" style="color: white; text-decoration: none; font-size: 12px;">مشاهده همه</a>
                </div>
                <div class="card-body no-padding">
                    <?php if (count($todayTasks) > 0): ?>
                        <?php foreach ($todayTasks as $task): ?>
                            <div class="task-item">
                                <div class="task-info">
                                    <h4><?php echo h($task['title']); ?></h4>
                                    <p><?php echo h($task['project_title'] ?? 'بدون پروژه'); ?></p>
                                </div>
                                <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                    <?php 
                                    $priorities = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'بالا', 'urgent' => 'فوری'];
                                    echo $priorities[$task['priority']] ?? $task['priority'];
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <p>شما وظیفه‌ای برای امروز ندارید!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Active Projects -->
            <div class="dashboard-card">
                <div class="card-header">
                    📊 پروژه‌های فعال
                    <a href="projects.php" style="color: white; text-decoration: none; font-size: 12px;">مشاهده همه</a>
                </div>
                <div class="card-body no-padding">
                    <?php if (count($activeProjects) > 0): ?>
                        <?php foreach ($activeProjects as $project): 
                            $progress = $project['total_tasks'] > 0 
                                ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) 
                                : 0;
                        ?>
                            <div class="project-item">
                                <div class="project-header">
                                    <span class="project-title"><?php echo h($project['title']); ?></span>
                                    <span class="project-progress"><?php echo en2fa($progress); ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📊</div>
                            <p>پروژه فعالی وجود ندارد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Today's Events -->
            <div class="dashboard-card">
                <div class="card-header">
                    📅 رویدادهای امروز
                    <a href="calendar.php" style="color: white; text-decoration: none; font-size: 12px;">تقویم کامل</a>
                </div>
                <div class="card-body">
                    <?php if (count($todayEvents) > 0): ?>
                        <?php foreach ($todayEvents as $event): ?>
                            <div class="event-item">
                                <div class="event-time">
                                    ⏰ <?php echo en2fa(date('H:i', strtotime($event['start_time']))); ?>
                                </div>
                                <div class="event-title"><?php echo h($event['title']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📅</div>
                            <p>رویدادی برای امروز ثبت نشده است</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reminders -->
            <div class="dashboard-card">
                <div class="card-header">
                    🔔 یادآورهای امروز
                    <a href="reminders.php" style="color: white; text-decoration: none; font-size: 12px;">مشاهده همه</a>
                </div>
                <div class="card-body">
                    <?php if (count($todayReminders) > 0): ?>
                        <?php foreach ($todayReminders as $reminder): ?>
                            <div class="event-item">
                                <div class="event-time">
                                    ⏰ <?php echo en2fa(date('H:i', strtotime($reminder['remind_time']))); ?>
                                </div>
                                <div class="event-title"><?php echo h($reminder['title']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🔔</div>
                            <p>یادآوری برای امروز ندارید</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (check_permission('financial', PERMISSION_READ) && count($recentTransactions) > 0): ?>
        <!-- Recent Transactions -->
        <div class="dashboard-card">
            <div class="card-header">
                💰 تراکنش‌های مالی اخیر
                <a href="transactions.php" style="color: white; text-decoration: none; font-size: 12px;">مشاهده همه</a>
            </div>
            <div class="card-body no-padding">
                <?php foreach ($recentTransactions as $trans): ?>
                    <div class="task-item">
                        <div class="task-info">
                            <h4>
                                <?php echo h($trans['from_account']); ?> 
                                → 
                                <?php echo h($trans['to_account']); ?>
                            </h4>
                            <p><?php echo en2fa(number_format($trans['amount'])); ?> ریال</p>
                        </div>
                        <span class="priority-badge priority-<?php echo $trans['status'] === 'confirmed' ? 'low' : 'medium'; ?>">
                            <?php 
                            $statuses = ['draft' => 'پیش‌نویس', 'pending' => 'در انتظار', 'confirmed' => 'تایید شده', 'cancelled' => 'لغو شده'];
                            echo $statuses[$trans['status']] ?? $trans['status'];
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>