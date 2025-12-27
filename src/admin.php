<?php
/**
 * پنل مدیریت سیستم
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!$_SESSION['is_admin']) {
    die('دسترسی غیرمجاز! فقط مدیران سیستم می‌توانند به این بخش دسترسی داشته باشند.');
}

// دریافت آمار سیستم
$systemStats = [
    'total_users' => db()->count('users'),
    'active_users' => db()->count('users', 'is_active = 1'),
    'total_contacts' => db()->count('contacts'),
    'total_projects' => db()->count('projects'),
    'active_projects' => db()->count('projects', "status = 'active'"),
    'total_transactions' => db()->count('transactions'),
    'pending_transactions' => db()->count('transactions', "status = 'pending'"),
    'total_warehouse_items' => db()->count('warehouse_items'),
    'low_stock_items' => db()->count('warehouse_items', 'current_stock <= min_stock AND is_active = 1'),
    'total_employees' => db()->count('hr_employees', "status = 'active'"),
    'pending_leaves' => db()->count('hr_leaves', "status = 'pending'"),
];

// آخرین کاربران
$recentUsers = db()->select(
    "SELECT id, username, fullname, email, is_active, last_login, created_at 
     FROM users 
     ORDER BY created_at DESC 
     LIMIT 10"
);

// آخرین فعالیت‌ها
$recentLogs = db()->select(
    "SELECT l.*, u.fullname, u.username
     FROM logs l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC
     LIMIT 20"
);

// آمار ماژول‌ها
$moduleStats = [
    'financial' => [
        'accounts' => db()->count('accounts', 'is_active = 1'),
        'transactions_today' => db()->count('transactions', 'DATE(transaction_date) = CURDATE()'),
        'pending_approval' => db()->count('transactions', "status = 'pending'")
    ],
    'warehouse' => [
        'total_items' => db()->count('warehouse_items', 'is_active = 1'),
        'low_stock' => db()->count('warehouse_items', 'current_stock <= min_stock AND is_active = 1'),
        'transactions_today' => db()->count('warehouse_transactions', 'transaction_date = CURDATE()')
    ],
    'hr' => [
        'total_employees' => db()->count('hr_employees', "status = 'active'"),
        'pending_leaves' => db()->count('hr_leaves', "status = 'pending'"),
        'employees_on_leave_today' => db()->count('hr_leaves', "status = 'approved' AND CURDATE() BETWEEN start_date AND end_date")
    ],
    'projects' => [
        'active' => db()->count('projects', "status = 'active'"),
        'planning' => db()->count('projects', "status = 'planning'"),
        'overdue_tasks' => db()->count('tasks', "status != 'done' AND due_date < CURDATE()")
    ]
];

// بررسی وضعیت سیستم
$systemHealth = [
    'database' => true,
    'uploads_dir' => is_writable(UPLOAD_DIR),
    'logs_dir' => is_writable(SITE_ROOT . '/logs'),
    'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'session' => session_status() === PHP_SESSION_ACTIVE
];

$healthScore = array_sum($systemHealth) / count($systemHealth) * 100;
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - <?php echo SITE_TITLE; ?></title>
    <style>
        .admin-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
        }
        
        .admin-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .admin-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* System Health */
        .health-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .health-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .health-score {
            font-size: 48px;
            font-weight: bold;
            color: <?php echo $healthScore >= 80 ? '#4caf50' : ($healthScore >= 60 ? '#ff9800' : '#f44336'); ?>;
        }
        
        .health-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .health-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .health-status {
            font-size: 24px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .stat-card p {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .stat-card.warning h3 {
            color: #f39c12;
        }
        
        .stat-card.danger h3 {
            color: #e74c3c;
        }
        
        .stat-card.success h3 {
            color: #27ae60;
        }
        
        /* Tabs */
        .admin-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        
        .tab-btn:hover {
            border-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: right;
            font-weight: bold;
            font-size: 13px;
            color: #2c3e50;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Module Stats */
        .module-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .module-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .module-card h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .module-stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .module-stat-item:last-child {
            border-bottom: none;
        }
        
        .module-stat-label {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .module-stat-value {
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .health-items {
                grid-template-columns: 1fr;
            }
            
            .module-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Admin Header -->
        <div class="admin-header">
            <h1>⚙️ پنل مدیریت سیستم</h1>
            <p>مدیریت و نظارت بر تمام بخش‌های سیستم</p>
        </div>
        
        <!-- System Health -->
        <div class="health-card">
            <div class="health-header">
                <h2>🏥 سلامت سیستم</h2>
                <div>
                    <div class="health-score"><?php echo en2fa(round($healthScore)); ?>%</div>
                    <small style="color: #7f8c8d;">وضعیت عملکرد</small>
                </div>
            </div>
            
            <div class="health-items">
                <div class="health-item">
                    <span>دیتابیس</span>
                    <span class="health-status"><?php echo $systemHealth['database'] ? '✅' : '❌'; ?></span>
                </div>
                <div class="health-item">
                    <span>پوشه آپلود</span>
                    <span class="health-status"><?php echo $systemHealth['uploads_dir'] ? '✅' : '❌'; ?></span>
                </div>
                <div class="health-item">
                    <span>پوشه لاگ</span>
                    <span class="health-status"><?php echo $systemHealth['logs_dir'] ? '✅' : '❌'; ?></span>
                </div>
                <div class="health-item">
                    <span>نسخه PHP</span>
                    <span class="health-status"><?php echo $systemHealth['php_version'] ? '✅' : '❌'; ?></span>
                </div>
                <div class="health-item">
                    <span>Session</span>
                    <span class="health-status"><?php echo $systemHealth['session'] ? '✅' : '❌'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- System Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo en2fa($systemStats['total_users']); ?></h3>
                <p>کل کاربران</p>
            </div>
            
            <div class="stat-card success">
                <h3><?php echo en2fa($systemStats['active_users']); ?></h3>
                <p>کاربران فعال</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($systemStats['total_contacts']); ?></h3>
                <p>مخاطبین</p>
            </div>
            
            <div class="stat-card success">
                <h3><?php echo en2fa($systemStats['active_projects']); ?></h3>
                <p>پروژه‌های فعال</p>
            </div>
            
            <div class="stat-card <?php echo $systemStats['pending_transactions'] > 0 ? 'warning' : ''; ?>">
                <h3><?php echo en2fa($systemStats['pending_transactions']); ?></h3>
                <p>تراکنش‌های در انتظار</p>
            </div>
            
            <div class="stat-card <?php echo $systemStats['low_stock_items'] > 0 ? 'danger' : ''; ?>">
                <h3><?php echo en2fa($systemStats['low_stock_items']); ?></h3>
                <p>کمبود موجودی انبار</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($systemStats['total_employees']); ?></h3>
                <p>پرسنل فعال</p>
            </div>
            
            <div class="stat-card <?php echo $systemStats['pending_leaves'] > 0 ? 'warning' : ''; ?>">
                <h3><?php echo en2fa($systemStats['pending_leaves']); ?></h3>
                <p>درخواست مرخصی</p>
            </div>
        </div>
        
        <!-- Module Stats -->
        <h2 style="margin-bottom: 20px;">📊 آمار ماژول‌ها</h2>
        <div class="module-stats">
            <div class="module-card">
                <h3>💰 مالی</h3>
                <div class="module-stat-item">
                    <span class="module-stat-label">حساب‌های فعال</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['financial']['accounts']); ?></span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">تراکنش‌های امروز</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['financial']['transactions_today']); ?></span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">در انتظار تایید</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['financial']['pending_approval']); ?></span>
                </div>
            </div>
            
            <div class="module-card">
                <h3>📦 انبارداری</h3>
                <div class="module-stat-item">
                    <span class="module-stat-label">کل اقلام</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['warehouse']['total_items']); ?></span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">موجودی کم</span>
                    <span class="module-stat-value" style="color: #f44336;">
                        <?php echo en2fa($moduleStats['warehouse']['low_stock']); ?>
                    </span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">تراکنش‌های امروز</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['warehouse']['transactions_today']); ?></span>
                </div>
            </div>
            
            <div class="module-card">
                <h3>👔 منابع انسانی</h3>
                <div class="module-stat-item">
                    <span class="module-stat-label">کل پرسنل</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['hr']['total_employees']); ?></span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">درخواست مرخصی</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['hr']['pending_leaves']); ?></span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">در مرخصی امروز</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['hr']['employees_on_leave_today']); ?></span>
                </div>
            </div>
            
            <div class="module-card">
                <h3>📊 پروژه‌ها</h3>
                <div class="module-stat-item">
                    <span class="module-stat-label">پروژه‌های فعال</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['projects']['active']); ?></span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">در حال برنامه‌ریزی</span>
                    <span class="module-stat-value"><?php echo en2fa($moduleStats['projects']['planning']); ?></span>
                </div>
                <div class="module-stat-item">
                    <span class="module-stat-label">وظایف عقب‌افتاده</span>
                    <span class="module-stat-value" style="color: #f44336;">
                        <?php echo en2fa($moduleStats['projects']['overdue_tasks']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="action-buttons">
            <a href="users.php" class="btn btn-primary">👥 مدیریت کاربران</a>
            <a href="permission.php" class="btn btn-primary">🔐 مجوزها</a>
            <a href="settings.php" class="btn btn-primary">⚙️ تنظیمات</a>
            <a href="backup.php" class="btn btn-success">💾 پشتیبان‌گیری</a>
            <a href="logs.php" class="btn btn-danger">📋 لاگ سیستم</a>
        </div>
        
        <!-- Tabs -->
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="showTab('users')">کاربران اخیر</button>
            <button class="tab-btn" onclick="showTab('logs')">فعالیت‌ها</button>
        </div>
        
        <!-- Tab Contents -->
        <div id="users-tab" class="tab-content active">
            <div class="table-container">
                <div class="table-header">آخرین کاربران ثبت‌شده</div>
                <table>
                    <thead>
                        <tr>
                            <th>نام کاربری</th>
                            <th>نام و نام خانوادگی</th>
                            <th>ایمیل</th>
                            <th>وضعیت</th>
                            <th>آخرین ورود</th>
                            <th>تاریخ ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo h($user['username']); ?></td>
                                <td><?php echo h($user['fullname']); ?></td>
                                <td><?php echo h($user['email'] ?: '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $user['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? en2fa(date('Y/m/d H:i', strtotime($user['last_login']))) : 'هرگز'; ?></td>
                                <td><?php echo en2fa(date('Y/m/d', strtotime($user['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="logs-tab" class="tab-content">
            <div class="table-container">
                <div class="table-header">آخرین فعالیت‌های سیستم</div>
                <table>
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>عملیات</th>
                            <th>ماژول</th>
                            <th>IP</th>
                            <th>زمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?php echo h($log['fullname'] ?: 'سیستم'); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo h($log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo h($log['module']); ?></td>
                                <td><small><?php echo h($log['ip_address']); ?></small></td>
                                <td><?php echo en2fa(date('Y/m/d H:i', strtotime($log['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>