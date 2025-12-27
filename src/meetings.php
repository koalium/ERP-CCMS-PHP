<?php
/**
 * مدیریت جلسات و صورتجلسات
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

// فیلترها
$status = sanitize_input($_GET['status'] ?? '');
$search = sanitize_input($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// ساخت کوئری
$sql = "SELECT m.*, u.fullname as creator_name
        FROM meetings m
        LEFT JOIN users u ON u.id = m.created_by
        WHERE 1=1";

$params = [];

// فیلتر بر اساس شرکت‌کننده
$sql .= " AND (m.created_by = :user_id OR JSON_CONTAINS(m.attendees, :user_id_json))";
$params[':user_id'] = $_SESSION['user_id'];
$params[':user_id_json'] = '"' . $_SESSION['user_id'] . '"';

if ($status) {
    $sql .= " AND m.status = :status";
    $params[':status'] = $status;
}

if ($search) {
    $sql .= " AND (m.title LIKE :search OR m.meeting_number LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY m.meeting_date DESC, m.meeting_time DESC";

// دریافت جلسات
$result = db()->paginate($sql, $params, $page, $perPage);
$meetings = $result['data'];
$totalPages = $result['total_pages'];

// آمار جلسات
$meetingStats = [
    'total' => db()->count('meetings'),
    'scheduled' => db()->count('meetings', "status = 'scheduled' AND meeting_date >= CURDATE()"),
    'completed' => db()->count('meetings', "status = 'completed'"),
    'today' => db()->count('meetings', "meeting_date = CURDATE()"),
];

// جلسات امروز
$todayMeetings = db()->select(
    "SELECT m.*, u.fullname as creator_name
     FROM meetings m
     LEFT JOIN users u ON u.id = m.created_by
     WHERE m.meeting_date = CURDATE()
     AND (m.created_by = :user_id OR JSON_CONTAINS(m.attendees, :user_id_json))
     ORDER BY m.meeting_time",
    [
        ':user_id' => $_SESSION['user_id'],
        ':user_id_json' => '"' . $_SESSION['user_id'] . '"'
    ]
);

// جلسات آتی (7 روز آینده)
$upcomingMeetings = db()->select(
    "SELECT m.*, u.fullname as creator_name
     FROM meetings m
     LEFT JOIN users u ON u.id = m.created_by
     WHERE m.meeting_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     AND (m.created_by = :user_id OR JSON_CONTAINS(m.attendees, :user_id_json))
     AND m.status = 'scheduled'
     ORDER BY m.meeting_date, m.meeting_time
     LIMIT 5",
    [
        ':user_id' => $_SESSION['user_id'],
        ':user_id_json' => '"' . $_SESSION['user_id'] . '"'
    ]
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت جلسات - <?php echo SITE_TITLE; ?></title>
    <style>
        .meetings-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .meetings-header {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(41, 128, 185, 0.3);
        }
        
        .meetings-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
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
            color: #2980b9;
        }
        
        /* Today's Meetings Alert */
        .today-alert {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .alert-icon {
            font-size: 36px;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #555;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Meetings Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        
        .meetings-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .meeting-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-right: 4px solid #2980b9;
            cursor: pointer;
        }
        
        .meeting-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .meeting-card.scheduled {
            border-right-color: #3498db;
        }
        
        .meeting-card.in_progress {
            border-right-color: #f39c12;
        }
        
        .meeting-card.completed {
            border-right-color: #27ae60;
        }
        
        .meeting-card.cancelled {
            border-right-color: #e74c3c;
            opacity: 0.7;
        }
        
        .meeting-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .meeting-number {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: bold;
        }
        
        .meeting-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-scheduled {
            background: #d6eaf8;
            color: #2874a6;
        }
        
        .status-in_progress {
            background: #fef5e7;
            color: #d68910;
        }
        
        .status-completed {
            background: #d5f4e6;
            color: #1e8449;
        }
        
        .status-cancelled {
            background: #fadbd8;
            color: #a93226;
        }
        
        .meeting-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .meeting-datetime {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .meeting-datetime span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .meeting-location {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .meeting-attendees {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #ecf0f1;
        }
        
        .attendee-count {
            padding: 5px 12px;
            background: #ecf0f1;
            border-radius: 20px;
            font-size: 12px;
            color: #2c3e50;
        }
        
        /* Sidebar */
        .meetings-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-card h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .upcoming-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .upcoming-date {
            font-weight: bold;
            color: #2980b9;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .upcoming-title {
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .upcoming-time {
            font-size: 11px;
            color: #7f8c8d;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
        }
        
        .page-link {
            padding: 8px 15px;
            border: 2px solid #2980b9;
            border-radius: 6px;
            color: #2980b9;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .page-link:hover,
        .page-link.active {
            background: #2980b9;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .form-group {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="meetings-container">
        <!-- Meetings Header -->
        <div class="meetings-header">
            <h1>🤝 مدیریت جلسات</h1>
            <p>برنامه‌ریزی، برگزاری و ثبت صورتجلسات</p>
        </div>
        
        <!-- Today's Meetings Alert -->
        <?php if (count($todayMeetings) > 0): ?>
        <div class="today-alert">
            <div class="alert-icon">📅</div>
            <div class="alert-content">
                <div class="alert-title">جلسات امروز</div>
                <div><?php echo en2fa(count($todayMeetings)); ?> جلسه برای امروز برنامه‌ریزی شده است</div>
            </div>
            <a href="#today" class="btn btn-success">مشاهده</a>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo en2fa($meetingStats['total']); ?></h3>
                <p>کل جلسات</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($meetingStats['scheduled']); ?></h3>
                <p>برنامه‌ریزی شده</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($meetingStats['today']); ?></h3>
                <p>جلسات امروز</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($meetingStats['completed']); ?></h3>
                <p>تکمیل شده</p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="form-group">
                        <label>جستجو</label>
                        <input type="text" name="search" placeholder="عنوان یا شماره جلسه..." 
                               value="<?php echo h($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">همه</option>
                            <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>برنامه‌ریزی شده</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>در حال برگزاری</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                    <a href="meeting.php?action=add" class="btn btn-success">➕ جلسه جدید</a>
                </div>
            </form>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Meetings List -->
            <div>
                <?php if (count($meetings) > 0): ?>
                    <div class="meetings-list" id="today">
                        <?php foreach ($meetings as $meeting): 
                            $statusLabels = [
                                'scheduled' => 'برنامه‌ریزی شده',
                                'in_progress' => 'در حال برگزاری',
                                'completed' => 'تکمیل شده',
                                'cancelled' => 'لغو شده'
                            ];
                            
                            $attendees = json_decode($meeting['attendees'], true) ?? [];
                            $attendeeCount = count($attendees);
                        ?>
                            <div class="meeting-card <?php echo $meeting['status']; ?>" 
                                 onclick="window.location.href='meeting.php?action=view&id=<?php echo $meeting['id']; ?>'">
                                
                                <div class="meeting-header">
                                    <div class="meeting-number">
                                        <?php echo h($meeting['meeting_number']); ?>
                                    </div>
                                    <div class="meeting-status status-<?php echo $meeting['status']; ?>">
                                        <?php echo $statusLabels[$meeting['status']] ?? $meeting['status']; ?>
                                    </div>
                                </div>
                                
                                <div class="meeting-title"><?php echo h($meeting['title']); ?></div>
                                
                                <div class="meeting-datetime">
                                    <span>📅 <?php echo en2fa(date('Y/m/d', strtotime($meeting['meeting_date']))); ?></span>
                                    <span>🕐 <?php echo en2fa(date('H:i', strtotime($meeting['meeting_time']))); ?></span>
                                    <?php if ($meeting['duration_minutes']): ?>
                                        <span>⏱️ <?php echo en2fa($meeting['duration_minutes']); ?> دقیقه</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($meeting['location']): ?>
                                    <div class="meeting-location">
                                        📍 <?php echo h($meeting['location']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($meeting['agenda']): ?>
                                    <p style="font-size: 13px; color: #7f8c8d; margin: 10px 0;">
                                        <?php echo h(mb_substr($meeting['agenda'], 0, 100)) . '...'; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="meeting-attendees">
                                    <span style="font-size: 13px; color: #7f8c8d;">👥 شرکت‌کنندگان:</span>
                                    <span class="attendee-count"><?php echo en2fa($attendeeCount); ?> نفر</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="page-link">قبلی</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo en2fa($i); ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="page-link">بعدی</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div style="font-size: 64px; margin-bottom: 20px;">🤝</div>
                        <h3>جلسه‌ای یافت نشد</h3>
                        <p>برای شروع، جلسه جدید ایجاد کنید</p>
                        <a href="meeting.php?action=add" class="btn btn-success" style="margin-top: 20px;">
                            ➕ ایجاد جلسه اول
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="meetings-sidebar">
                <!-- Upcoming Meetings -->
                <?php if (count($upcomingMeetings) > 0): ?>
                <div class="sidebar-card">
                    <h3>📆 جلسات آتی</h3>
                    <?php foreach ($upcomingMeetings as $meeting): ?>
                        <div class="upcoming-item">
                            <div class="upcoming-date">
                                <?php echo en2fa(date('Y/m/d', strtotime($meeting['meeting_date']))); ?>
                            </div>
                            <div class="upcoming-title"><?php echo h($meeting['title']); ?></div>
                            <div class="upcoming-time">
                                🕐 <?php echo en2fa(date('H:i', strtotime($meeting['meeting_time']))); ?>
                                <?php if ($meeting['location']): ?>
                                    - 📍 <?php echo h($meeting['location']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="sidebar-card">
                    <h3>⚡ دسترسی سریع</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="meeting.php?action=add" class="btn btn-success" style="width: 100%;">
                            ➕ جلسه جدید
                        </a>
                        <a href="?status=scheduled" class="btn btn-primary" style="width: 100%;">
                            📅 جلسات برنامه‌ریزی شده
                        </a>
                        <a href="?status=completed" class="btn btn-primary" style="width: 100%;">
                            ✅ صورتجلسات
                        </a>
                        <a href="calendar.php" class="btn btn-primary" style="width: 100%;">
                            📆 تقویم جلسات
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>