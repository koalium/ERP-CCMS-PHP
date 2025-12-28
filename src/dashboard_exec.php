<?php
/**
 * داشبورد مدیریت ارشد
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'داشبورد مدیریت';
require_once 'header.php';

check_login();

$userId = $_SESSION['user_id'];
$isAdmin = $_SESSION['is_admin'];

// آمار کلی پروژه‌ها
$projectStats = [
    'total' => db()->count('projects'),
    'active' => db()->count('projects', 'status = "active"'),
    'planning' => db()->count('projects', 'status = "planning"'),
    'on_hold' => db()->count('projects', 'status = "on_hold"'),
    'completed' => db()->count('projects', 'status = "completed"')
];

// پروژه‌های در حال اجرا
$activeProjects = db()->select(
    "SELECT p.*, 
     (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
     (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'done') as completed_tasks,
     c.name as client_name,
     u.fullname as manager_name
     FROM projects p
     LEFT JOIN contacts c ON c.id = p.client_contact_id
     LEFT JOIN users u ON u.id = p.manager_user_id
     WHERE p.status IN ('active', 'planning')
     ORDER BY p.created_at DESC
     LIMIT 5"
);

// وظایف در حال انجام
$activeTasks = db()->select(
    "SELECT t.*, p.title as project_title, u.fullname as assigned_name
     FROM tasks t
     LEFT JOIN projects p ON p.id = t.project_id
     LEFT JOIN users u ON u.id = t.assigned_to
     WHERE t.status IN ('todo', 'in_progress')
     AND t.due_date >= CURDATE()
     ORDER BY t.priority DESC, t.due_date ASC
     LIMIT 10"
);

// آمار مالی
$financialStats = db()->selectOne(
    "SELECT 
     SUM(CASE WHEN type = 'income' AND status = 'confirmed' THEN amount ELSE 0 END) as total_income,
     SUM(CASE WHEN type = 'expense' AND status = 'confirmed' THEN amount ELSE 0 END) as total_expense,
     SUM(CASE WHEN type = 'income' AND status = 'pending' THEN amount ELSE 0 END) as pending_income,
     SUM(CASE WHEN type = 'expense' AND status = 'pending' THEN amount ELSE 0 END) as pending_expense
     FROM transactions
     WHERE MONTH(transaction_date) = MONTH(CURDATE())
     AND YEAR(transaction_date) = YEAR(CURDATE())"
);

// درخواست‌های مالی در انتظار تایید
$pendingFinancial = db()->select(
    "SELECT t.*, u.fullname as creator_name, 
     a1.name as from_account, a2.name as to_account
     FROM transactions t
     LEFT JOIN users u ON u.id = t.created_by
     LEFT JOIN accounts a1 ON a1.id = t.from_account_id
     LEFT JOIN accounts a2 ON a2.id = t.to_account_id
     WHERE t.status = 'pending'
     ORDER BY t.created_at DESC
     LIMIT 8"
);

// آمار منابع انسانی
$hrStats = db()->selectOne(
    "SELECT 
     COUNT(*) as total_employees,
     SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_employees,
     SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_employees
     FROM hr_employees"
);

// درخواست‌های مرخصی در انتظار
$pendingLeaves = db()->select(
    "SELECT l.*, e.employee_code, c.name as employee_name
     FROM hr_leaves l
     LEFT JOIN hr_employees e ON e.id = l.employee_id
     LEFT JOIN contacts c ON c.id = e.contact_id
     WHERE l.status = 'pending'
     ORDER BY l.created_at DESC
     LIMIT 5"
);

// مناقصات جدید
$newTenders = db()->select(
    "SELECT * FROM tenders
     WHERE status IN ('identified', 'reviewing')
     ORDER BY deadline_date ASC
     LIMIT 5"
);

// جلسات پیش رو
$upcomingMeetings = db()->select(
    "SELECT m.*, 
     (SELECT COUNT(*) FROM messages WHERE JSON_CONTAINS(m.attendees, CAST(receiver_id AS JSON), '$')) as attendees_count
     FROM meetings m
     WHERE m.meeting_date >= CURDATE()
     AND m.status = 'scheduled'
     ORDER BY m.meeting_date ASC, m.meeting_time ASC
     LIMIT 5"
);

// پیام‌های خوانده نشده
$unreadMessages = db()->count('messages', 'receiver_id = :user_id AND is_read = 0', [':user_id' => $userId]);

// یادآورهای امروز
$todayReminders = db()->select(
    "SELECT * FROM reminders
     WHERE user_id = :user_id
     AND remind_date = CURDATE()
     AND is_sent = 0
     ORDER BY remind_time ASC
     LIMIT 5",
    [':user_id' => $userId]
);

// محاسبه نسبت‌ها
$balance = ($financialStats['total_income'] ?? 0) - ($financialStats['total_expense'] ?? 0);
$activeEmployeePercent = $hrStats['total_employees'] > 0 
    ? round(($hrStats['active_employees'] / $hrStats['total_employees']) * 100) 
    : 0;
?>

<style>
    .dashboard-container {
        padding: 0;
    }
    
    .dashboard-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
    }
    
    .dashboard-header h1 {
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .dashboard-header p {
        opacity: 0.9;
        font-size: 16px;
    }
    
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .quick-action-btn {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-decoration: none;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    
    .quick-action-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: #667eea;
    }
    
    .quick-action-icon {
        font-size: 32px;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .quick-action-info h3 {
        font-size: 16px;
        margin-bottom: 5px;
    }
    
    .quick-action-info p {
        font-size: 12px;
        color: #999;
    }
    
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
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-radius: 0 0 0 100%;
    }
    
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }
    
    .stat-icon {
        font-size: 36px;
        opacity: 0.8;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .stat-progress {
        height: 6px;
        background: #e0e0e0;
        border-radius: 3px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    
    .stat-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 3px;
        transition: width 0.5s ease;
    }
    
    .stat-details {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #999;
    }
    
    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .widget {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .widget-header {
        padding: 20px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .widget-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .widget-action {
        color: #667eea;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s;
    }
    
    .widget-action:hover {
        color: #764ba2;
    }
    
    .widget-content {
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .widget-content::-webkit-scrollbar {
        width: 6px;
    }
    
    .widget-content::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }
    
    .project-item {
        padding: 15px;
        border-radius: 8px;
        background: #f8f9fa;
        margin-bottom: 15px;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .project-item:hover {
        background: #e3e7ff;
        transform: translateX(-5px);
    }
    
    .project-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 10px;
    }
    
    .project-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .project-client {
        font-size: 12px;
        color: #999;
    }
    
    .project-status {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .project-status.active {
        background: #e8f5e9;
        color: #388e3c;
    }
    
    .project-status.planning {
        background: #fff3e0;
        color: #f57c00;
    }
    
    .project-progress {
        margin-top: 10px;
    }
    
    .progress-label {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .progress-bar-container {
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #4caf50 0%, #8bc34a 100%);
        border-radius: 4px;
        transition: width 0.5s ease;
    }
    
    .task-item {
        padding: 12px;
        border-right: 4px solid #667eea;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: all 0.3s;
    }
    
    .task-item:hover {
        background: #e3e7ff;
        transform: translateX(-3px);
    }
    
    .task-item.priority-high {
        border-right-color: #f44336;
    }
    
    .task-item.priority-urgent {
        border-right-color: #ff5722;
        background: #ffebee;
    }
    
    .task-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 8px;
    }
    
    .task-title {
        font-weight: 500;
        color: #2c3e50;
        font-size: 14px;
    }
    
    .task-priority {
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .task-priority.high {
        background: #ffebee;
        color: #c62828;
    }
    
    .task-priority.urgent {
        background: #ff5722;
        color: white;
    }
    
    .task-meta {
        display: flex;
        gap: 15px;
        font-size: 12px;
        color: #999;
    }
    
    .financial-item {
        padding: 15px;
        border-radius: 8px;
        background: #f8f9fa;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s;
    }
    
    .financial-item:hover {
        background: #fff3e0;
        transform: translateX(-3px);
    }
    
    .financial-info h4 {
        font-size: 14px;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .financial-info p {
        font-size: 12px;
        color: #999;
    }
    
    .financial-amount {
        text-align: left;
    }
    
    .amount {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .amount.income {
        color: #4caf50;
    }
    
    .amount.expense {
        color: #f44336;
    }
    
    .financial-actions {
        display: flex;
        gap: 8px;
    }
    
    .btn-approve {
        padding: 6px 12px;
        background: #4caf50;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }
    
    .btn-approve:hover {
        background: #388e3c;
        transform: scale(1.05);
    }
    
    .btn-reject {
        padding: 6px 12px;
        background: #f44336;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }
    
    .btn-reject:hover {
        background: #d32f2f;
        transform: scale(1.05);
    }
    
    .meeting-item {
        padding: 15px;
        border-radius: 8px;
        background: #f8f9fa;
        margin-bottom: 12px;
        border-right: 4px solid #2196f3;
        transition: all 0.3s;
    }
    
    .meeting-item:hover {
        background: #e3f2fd;
        transform: translateX(-3px);
    }
    
    .meeting-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }
    
    .meeting-meta {
        display: flex;
        gap: 15px;
        font-size: 12px;
        color: #666;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }
    
    .empty-icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-header h1 {
            font-size: 24px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1>👋 خوش آمدید، <?php echo h($_SESSION['fullname']); ?></h1>
        <p>📅 <?php echo en2fa(date('l، d F Y')); ?> - ساعت <?php echo en2fa(date('H:i')); ?></p>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="meeting.php?action=add" class="quick-action-btn">
            <div class="quick-action-icon">🤝</div>
            <div class="quick-action-info">
                <h3>برنامه‌ریزی جلسه</h3>
                <p>جلسه جدید ایجاد کنید</p>
            </div>
        </a>
        
        <a href="task.php?action=add" class="quick-action-btn">
            <div class="quick-action-icon">✅</div>
            <div class="quick-action-info">
                <h3>ابلاغ وظیفه</h3>
                <p>وظیفه جدید تعریف کنید</p>
            </div>
        </a>
        
        <a href="transactions.php?status=pending" class="quick-action-btn">
            <div class="quick-action-icon">💰</div>
            <div class="quick-action-info">
                <h3>تایید مالی</h3>
                <p><?php echo en2fa(count($pendingFinancial)); ?> درخواست</p>
            </div>
        </a>
        
        <a href="tenders.php?status=reviewing" class="quick-action-btn">
            <div class="quick-action-icon">📋</div>
            <div class="quick-action-info">
                <h3>بررسی مناقصات</h3>
                <p><?php echo en2fa(count($newTenders)); ?> مناقصه جدید</p>
            </div>
        </a>
        
        <a href="messenger.php" class="quick-action-btn">
            <div class="quick-action-icon">💬</div>
            <div class="quick-action-info">
                <h3>پیام‌ها</h3>
                <p><?php echo en2fa($unreadMessages); ?> خوانده نشده</p>
            </div>
        </a>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <!-- Project Stats -->
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo en2fa($projectStats['active']); ?></div>
                    <div class="stat-label">پروژه‌های فعال</div>
                </div>
                <div class="stat-icon">📊</div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: <?php echo $projectStats['total'] > 0 ? ($projectStats['active'] / $projectStats['total'] * 100) : 0; ?>%"></div>
            </div>
            <div class="stat-details">
                <span>کل: <?php echo en2fa($projectStats['total']); ?></span>
                <span>تکمیل شده: <?php echo en2fa($projectStats['completed']); ?></span>
            </div>
        </div>
        
        <!-- Financial Stats -->
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value" style="color: <?php echo $balance >= 0 ? '#4caf50' : '#f44336'; ?>">
                        <?php echo en2fa(number_format($balance)); ?>
                    </div>
                    <div class="stat-label">تراز مالی (ریال)</div>
                </div>
                <div class="stat-icon">💰</div>
            </div>
            <div class="stat-details">
                <span style="color: #4caf50">درآمد: <?php echo en2fa(number_format($financialStats['total_income'] ?? 0)); ?></span>
                <span style="color: #f44336">هزینه: <?php echo en2fa(number_format($financialStats['total_expense'] ?? 0)); ?></span>
            </div>
        </div>
        
        <!-- HR Stats -->
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo en2fa($hrStats['active_employees'] ?? 0); ?></div>
                    <div class="stat-label">پرسنل فعال</div>
                </div>
                <div class="stat-icon">👥</div>
            </div>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: <?php echo $activeEmployeePercent; ?>%"></div>
            </div>
            <div class="stat-details">
                <span>کل: <?php echo en2fa($hrStats['total_employees'] ?? 0); ?></span>
                <span>تعلیق: <?php echo en2fa($hrStats['suspended_employees'] ?? 0); ?></span>
            </div>
        </div>
        
        <!-- Tasks Stats -->
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo en2fa(count($activeTasks)); ?></div>
                    <div class="stat-label">وظایف در دست اقدام</div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
            <div class="stat-details">
                <span>امروز: <?php echo en2fa(array_reduce($activeTasks, function($c, $t) { 
                    return $c + ($t['due_date'] === date('Y-m-d') ? 1 : 0); 
                }, 0)); ?></span>
                <span>این هفته: <?php echo en2fa(array_reduce($activeTasks, function($c, $t) { 
                    return $c + (date('W', strtotime($t['due_date'])) === date('W') ? 1 : 0); 
                }, 0)); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Left Column -->
        <div>
            <!-- Active Projects -->
            <div class="widget">
                <div class="widget-header">
                    <div class="widget-title">
                        <span>📊</span>
                        <span>پروژه‌های در حال اجرا</span>
                    </div>
                    <a href="projects.php" class="widget-action">مشاهده همه ⬅️</a>
                </div>
                <div class="widget-content">
                    <?php if (count($activeProjects) > 0): ?>
                        <?php foreach ($activeProjects as $project): 
                            $progress = $project['total_tasks'] > 0 
                                ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) 
                                : 0;
                        ?>
                        <div class="project-item" onclick="window.location.href='project.php?action=view&id=<?php echo $project['id']; ?>'">
                            <div class="project-header">
                                <div>
                                    <div class="project-title"><?php echo h($project['title']); ?></div>
                                    <div class="project-client">
                                        👤 <?php echo h($project['client_name'] ?: 'بدون مشتری'); ?> | 
                                        👨‍💼 <?php echo h($project['manager_name'] ?: 'بدون مدیر'); ?>
                                    </div>
                                </div>
                                <span class="project-status <?php echo $project['status']; ?>">
                                    <?php 
                                    $statuses = ['active' => 'فعال', 'planning' => 'برنامه‌ریزی'];
                                    echo $statuses[$project['status']] ?? $project['status'];
                                    ?>
                                </span>
                            </div>
                            <div class="project-progress">
                                <div class="progress-label">
                                    <span>پیشرفت</span>
                                    <span><?php echo en2fa($progress); ?>%</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <p>هیچ پروژه فعالی وجود ندارد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Financial Approvals -->
            <div class="widget" style="margin-top: 20px;">
                <div class="widget-header">
                    <div class="widget-title">
                        <span>💰</span>
                        <span>درخواست‌های مالی در انتظار تایید</span>
                    </div>
                    <a href="transactions.php?status=pending" class="widget-action">مشاهده همه ⬅️</a>
                </div>
                <div class="widget-content">
                    <?php if (count($pendingFinancial) > 0): ?>
                        <?php foreach ($pendingFinancial as $trans): ?>
                        <div class="financial-item">
                            <div class="financial-info">
                                <h4>
                                    <?php 
                                    $types = ['income' => 'دریافت', 'expense' => 'پرداخت', 'transfer' => 'انتقال'];
                                    echo $types[$trans['type']] ?? $trans['type'];
                                    ?>
                                </h4>
                                <p>
                                    <?php if ($trans['from_account']): ?>
                                        از: <?php echo h($trans['from_account']); ?>
                                    <?php endif; ?>
                                    <?php if ($trans['to_account']): ?>
                                        به: <?php echo h($trans['to_account']); ?>
                                    <?php endif; ?>
                                </p>
                                <p style="font-size: 11px; margin-top: 5px;">
                                    توسط: <?php echo h($trans['creator_name']); ?> - 
                                    <?php echo en2fa(date('Y/m/d', strtotime($trans['created_at']))); ?>
                                </p>
                            </div>
                            <div class="financial-amount">
                                <div class="amount <?php echo $trans['type']; ?>">
                                    <?php echo en2fa(number_format($trans['amount'])); ?> ریال
                                </div>
                                <div class="financial-actions">
                                    <button class="btn-approve" onclick="approveTransaction(<?php echo $trans['id']; ?>)">
                                        ✓ تایید
                                    </button>
                                    <button class="btn-reject" onclick="rejectTransaction(<?php echo $trans['id']; ?>)">
                                        ✗ رد
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">💰</div>
                            <p>درخواست مالی در انتظار تایید وجود ندارد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div>
            <!-- Active Tasks -->
            <div class="widget">
                <div class="widget-header">
                    <div class="widget-title">
                        <span>✅</span>
                        <span>وظایف ضروری</span>
                    </div>
                    <a href="tasks.php" class="widget-action">مشاهده همه ⬅️</a>
                </div>
                <div class="widget-content">
                    <?php if (count($activeTasks) > 0): ?>
                        <?php foreach (array_slice($activeTasks, 0, 8) as $task): ?>
                        <div class="task-item priority-<?php echo $task['priority']; ?>">
                            <div class="task-header">
                                <div class="task-title"><?php echo h($task['title']); ?></div>
                                <span class="task-priority <?php echo $task['priority']; ?>">
                                    <?php 
                                    $priorities = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'بالا', 'urgent' => 'فوری'];
                                    echo $priorities[$task['priority']] ?? $task['priority'];
                                    ?>
                                </span>
                            </div>
                            <div class="task-meta">
                                <span>📊 <?php echo h($task['project_title']); ?></span>
                                <span>👤 <?php echo h($task['assigned_name'] ?: 'بدون مسئول'); ?></span>
                                <span>📅 <?php echo en2fa(date('Y/m/d', strtotime($task['due_date']))); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">✅</div>
                            <p>وظیفه‌ای در دست اقدام نیست</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Upcoming Meetings -->
            <div class="widget" style="margin-top: 20px;">
                <div class="widget-header">
                    <div class="widget-title">
                        <span>🤝</span>
                        <span>جلسات پیش رو</span>
                    </div>
                    <a href="meetings.php" class="widget-action">مشاهده همه ⬅️</a>
                </div>
                <div class="widget-content">
                    <?php if (count($upcomingMeetings) > 0): ?>
                        <?php foreach ($upcomingMeetings as $meeting): ?>
                        <div class="meeting-item" onclick="window.location.href='meeting.php?action=view&id=<?php echo $meeting['id']; ?>'">
                            <div class="meeting-title"><?php echo h($meeting['title']); ?></div>
                            <div class="meeting-meta">
                                <span>📅 <?php echo en2fa(date('Y/m/d', strtotime($meeting['meeting_date']))); ?></span>
                                <span>⏰ <?php echo en2fa(date('H:i', strtotime($meeting['meeting_time']))); ?></span>
                                <span>👥 <?php echo en2fa($meeting['attendees_count']); ?> نفر</span>
                                <?php if ($meeting['location']): ?>
                                    <span>📍 <?php echo h($meeting['location']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">🤝</div>
                            <p>جلسه‌ای برنامه‌ریزی نشده</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Today Reminders -->
            <?php if (count($todayReminders) > 0): ?>
            <div class="widget" style="margin-top: 20px;">
                <div class="widget-header">
                    <div class="widget-title">
                        <span>⏰</span>
                        <span>یادآورهای امروز</span>
                    </div>
                </div>
                <div class="widget-content">
                    <?php foreach ($todayReminders as $reminder): ?>
                    <div class="task-item">
                        <div class="task-header">
                            <div class="task-title"><?php echo h($reminder['title']); ?></div>
                            <span style="font-size: 12px; color: #999;">
                                ⏰ <?php echo en2fa(date('H:i', strtotime($reminder['remind_time']))); ?>
                            </span>
                        </div>
                        <?php if ($reminder['description']): ?>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                <?php echo h($reminder['description']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function approveTransaction(id) {
        if (confirm('آیا از تایید این تراکنش اطمینان دارید؟')) {
            window.location.href = `transaction_action.php?action=approve&id=${id}`;
        }
    }
    
    function rejectTransaction(id) {
        const reason = prompt('دلیل رد تراکنش را وارد کنید:');
        if (reason) {
            window.location.href = `transaction_action.php?action=reject&id=${id}&reason=${encodeURIComponent(reason)}`;
        }
    }
    
    // Auto refresh every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
</script>

<?php require_once 'footer.php'; ?>