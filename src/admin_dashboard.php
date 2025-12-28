<?php
/**
 * داشبورد مدیر سیستم
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'پنل مدیریت';
require_once 'header.php';

check_login();

if (!$_SESSION['is_admin']) {
    die('<div class="container"><div class="alert alert-error">شما دسترسی به این بخش را ندارید.</div></div>');
}

// آمار کلی سیستم
$systemStats = [
    'users' => db()->count('users'),
    'active_users' => db()->count('users', 'is_active = 1'),
    'projects' => db()->count('projects'),
    'tasks' => db()->count('tasks'),
    'messages' => db()->count('messages'),
    'meetings' => db()->count('meetings'),
    'contacts' => db()->count('contacts'),
    'transactions' => db()->count('transactions')
];

// آمار امنیتی
$securityStats = db()->selectOne(
    "SELECT 
     COUNT(DISTINCT user_id) as active_sessions,
     (SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as recent_attempts,
     (SELECT COUNT(*) FROM logs WHERE action = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_logins_24h
     FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);

// لاگ‌های اخیر
$recentLogs = db()->select(
    "SELECT l.*, u.fullname as user_name
     FROM logs l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC
     LIMIT 20"
);

// کاربران آنلاین
$onlineUsers = db()->select(
    "SELECT id, fullname, email, last_login
     FROM users
     WHERE last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     AND is_active = 1
     ORDER BY last_login DESC"
);

// آمار عملکرد
$performanceStats = [
    'avg_response_time' => '0.3s', // در نسخه واقعی از monitoring tool
    'uptime' => '99.9%',
    'total_requests' => db()->count('logs', 'created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'),
    'errors' => db()->count('logs', "action LIKE '%error%' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
];

// آمار دیتابیس
try {
    $dbSize = db()->selectOne("SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables 
        WHERE table_schema = '" . DB_NAME . "'");
    $dbSizeMB = $dbSize['size_mb'] ?? 0;
} catch (Exception $e) {
    $dbSizeMB = 'N/A';
}

// فعالیت‌های اخیر کاربران
$userActivities = db()->select(
    "SELECT u.fullname, u.email, 
     COUNT(l.id) as activity_count,
     MAX(l.created_at) as last_activity
     FROM users u
     LEFT JOIN logs l ON l.user_id = u.id AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     WHERE u.is_active = 1
     GROUP BY u.id, u.fullname, u.email
     ORDER BY activity_count DESC
     LIMIT 10"
);

// آمار ماژول‌ها
$moduleStats = db()->select(
    "SELECT module, 
     COUNT(*) as usage_count,
     COUNT(DISTINCT user_id) as unique_users
     FROM logs
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY module
     ORDER BY usage_count DESC
     LIMIT 10"
);
?>

<style>
    .admin-dashboard {
        max-width: 1600px;
        margin: 0 auto;
    }
    
    .admin-header {
        background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(229, 57, 53, 0.3);
    }
    
    .admin-header h1 {
        font-size: 32px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .admin-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .admin-action-btn {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-decoration: none;
        color: #2c3e50;
        text-align: center;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    
    .admin-action-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: #e53935;
    }
    
    .admin-action-icon {
        font-size: 36px;
        margin-bottom: 10px;
    }
    
    .admin-action-label {
        font-weight: 600;
        font-size: 14px;
    }
    
    .system-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .system-stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .system-stat-icon {
        font-size: 40px;
        margin-bottom: 15px;
    }
    
    .system-stat-value {
        font-size: 36px;
        font-weight: bold;
        color: #e53935;
        margin-bottom: 5px;
    }
    
    .system-stat-label {
        color: #666;
        font-size: 14px;
    }
    
    .security-panel {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 25px;
        margin-bottom: 30px;
    }
    
    .security-header {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 20px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .security-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .security-stat {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border-right: 4px solid #e53935;
    }
    
    .security-stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #e53935;
        margin-bottom: 5px;
    }
    
    .security-stat-label {
        font-size: 13px;
        color: #666;
    }
    
    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .panel {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .panel-header {
        padding: 20px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .panel-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .panel-content {
        padding: 20px;
        max-height: 500px;
        overflow-y: auto;
    }
    
    .log-item {
        padding: 12px;
        border-right: 4px solid #2196f3;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 10px;
        font-size: 13px;
    }
    
    .log-item.error {
        border-right-color: #f44336;
        background: #ffebee;
    }
    
    .log-item.warning {
        border-right-color: #ff9800;
        background: #fff3e0;
    }
    
    .log-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }
    
    .log-user {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .log-time {
        color: #999;
        font-size: 11px;
    }
    
    .log-action {
        color: #666;
    }
    
    .user-item {
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .user-info {
        flex: 1;
    }
    
    .user-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 3px;
    }
    
    .user-email {
        font-size: 12px;
        color: #999;
    }
    
    .online-indicator {
        width: 10px;
        height: 10px;
        background: #4caf50;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .activity-chart {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    
    .activity-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .activity-label {
        width: 120px;
        font-size: 13px;
        color: #666;
    }
    
    .activity-progress {
        flex: 1;
        height: 20px;
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .activity-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #e53935 0%, #c62828 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 10px;
        color: white;
        font-size: 11px;
        font-weight: bold;
    }
    
    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .admin-header h1 {
            font-size: 24px;
        }
        
        .system-stats {
            grid-template-columns: 1fr;
        }
        
        .admin-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-dashboard">
    <!-- Admin Header -->
    <div class="admin-header">
        <h1>
            <span>🔧</span>
            <span>پنل مدیریت سیستم</span>
        </h1>
        <p>مدیریت و نظارت بر تمام بخش‌های سیستم</p>
    </div>
    
    <!-- Quick Admin Actions -->
    <div class="admin-actions">
        <a href="users.php" class="admin-action-btn">
            <div class="admin-action-icon">👥</div>
            <div class="admin-action-label">مدیریت کاربران</div>
        </a>
        
        <a href="permission.php" class="admin-action-btn">
            <div class="admin-action-icon">🔐</div>
            <div class="admin-action-label">مجوزهای دسترسی</div>
        </a>
        
        <a href="settings.php" class="admin-action-btn">
            <div class="admin-action-icon">⚙️</div>
            <div class="admin-action-label">تنظیمات سیستم</div>
        </a>
        
        <a href="logs.php" class="admin-action-btn">
            <div class="admin-action-icon">📊</div>
            <div class="admin-action-label">لاگ‌های سیستم</div>
        </a>
        
        <a href="backup.php" class="admin-action-btn">
            <div class="admin-action-icon">💾</div>
            <div class="admin-action-label">پشتیبان‌گیری</div>
        </a>
        
        <a href="reports.php" class="admin-action-btn">
            <div class="admin-action-icon">📈</div>
            <div class="admin-action-label">گزارش‌ها</div>
        </a>
    </div>
    
    <!-- System Stats -->
    <div class="system-stats">
        <div class="system-stat-card">
            <div class="system-stat-icon">👥</div>
            <div class="system-stat-value"><?php echo en2fa($systemStats['users']); ?></div>
            <div class="system-stat-label">کل کاربران</div>
        </div>
        
        <div class="system-stat-card">
            <div class="system-stat-icon">📊</div>
            <div class="system-stat-value"><?php echo en2fa($systemStats['projects']); ?></div>
            <div class="system-stat-label">پروژه‌ها</div>
        </div>
        
        <div class="system-stat-card">
            <div class="system-stat-icon">✅</div>
            <div class="system-stat-value"><?php echo en2fa($systemStats['tasks']); ?></div>
            <div class="system-stat-label">وظایف</div>
        </div>
        
        <div class="system-stat-card">
            <div class="system-stat-icon">💬</div>
            <div class="system-stat-value"><?php echo en2fa($systemStats['messages']); ?></div>
            <div class="system-stat-label">پیام‌ها</div>
        </div>
        
        <div class="system-stat-card">
            <div class="system-stat-icon">💾</div>
            <div class="system-stat-value"><?php echo en2fa($dbSizeMB); ?></div>
            <div class="system-stat-label">حجم دیتابیس (MB)</div>
        </div>
    </div>
    
    <!-- Security Panel -->
    <div class="security-panel">
        <div class="security-header">
            <span>🔒</span>
            <span>وضعیت امنیت سیستم</span>
        </div>
        <div class="security-stats">
            <div class="security-stat">
                <div class="security-stat-value"><?php echo en2fa($securityStats['active_sessions'] ?? 0); ?></div>
                <div class="security-stat-label">نشست‌های فعال</div>
            </div>
            
            <div class="security-stat">
                <div class="security-stat-value"><?php echo en2fa($securityStats['recent_attempts'] ?? 0); ?></div>
                <div class="security-stat-label">تلاش ورود (۱ ساعت اخیر)</div>
            </div>
            
            <div class="security-stat">
                <div class="security-stat-value"><?php echo en2fa($securityStats['failed_logins_24h'] ?? 0); ?></div>
                <div class="security-stat-label">ورود ناموفق (۲۴ ساعت)</div>
            </div>
            
            <div class="security-stat">
                <div class="security-stat-value"><?php echo en2fa($performanceStats['errors']); ?></div>
                <div class="security-stat-label">خطاها (۲۴ ساعت)</div>
            </div>
        </div>
    </div>
    
    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Recent Logs -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <span>📋</span>
                    <span>فعالیت‌های اخیر سیستم</span>
                </div>
            </div>
            <div class="panel-content">
                <?php foreach ($recentLogs as $log): 
                    $isError = strpos($log['action'], 'error') !== false || strpos($log['action'], 'fail') !== false;
                    $isWarning = strpos($log['action'], 'delete') !== false || strpos($log['action'], 'reject') !== false;
                    $class = $isError ? 'error' : ($isWarning ? 'warning' : '');
                ?>
                <div class="log-item <?php echo $class; ?>">
                    <div class="log-header">
                        <span class="log-user"><?php echo h($log['user_name'] ?: 'سیستم'); ?></span>
                        <span class="log-time"><?php echo en2fa(date('Y/m/d H:i', strtotime($log['created_at']))); ?></span>
                    </div>
                    <div class="log-action">
                        <?php 
                        $actions = [
                            'login' => '🔐 ورود به سیستم',
                            'logout' => '🚪 خروج از سیستم',
                            'create' => '➕ ایجاد',
                            'update' => '✏️ ویرایش',
                            'delete' => '🗑️ حذف',
                            'approve' => '✅ تایید',
                            'reject' => '❌ رد'
                        ];
                        
                        $actionText = $log['action'];
                        foreach ($actions as $key => $value) {
                            if (strpos($log['action'], $key) !== false) {
                                $actionText = $value;
                                break;
                            }
                        }
                        
                        echo $actionText . ' - ' . h($log['module']);
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Online Users -->
        <div>
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <span>🟢</span>
                        <span>کاربران آنلاین</span>
                    </div>
                </div>
                <div class="panel-content">
                    <?php if (count($onlineUsers) > 0): ?>
                        <?php foreach ($onlineUsers as $user): ?>
                        <div class="user-item">
                            <div class="user-info">
                                <div class="user-name"><?php echo h($user['fullname']); ?></div>
                                <div class="user-email"><?php echo h($user['email']); ?></div>
                            </div>
                            <div class="online-indicator"></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #999;">
                            هیچ کاربری آنلاین نیست
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- User Activities -->
            <div class="panel" style="margin-top: 20px;">
                <div class="panel-header">
                    <div class="panel-title">
                        <span>📊</span>
                        <span>فعال‌ترین کاربران</span>
                    </div>
                </div>
                <div class="panel-content">
                    <?php 
                    $maxActivity = $userActivities[0]['activity_count'] ?? 1;
                    foreach ($userActivities as $activity): 
                        $percent = ($activity['activity_count'] / $maxActivity) * 100;
                    ?>
                    <div class="activity-chart">
                        <div style="margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 13px;">
                            <?php echo h($activity['fullname']); ?>
                        </div>
                        <div class="activity-bar">
                            <div class="activity-progress">
                                <div class="activity-progress-fill" style="width: <?php echo $percent; ?>%">
                                    <?php echo en2fa($activity['activity_count']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto refresh every 30 seconds
    setTimeout(function() {
        location.reload();
    }, 30000);
</script>

<?php require_once 'footer.php'; ?>