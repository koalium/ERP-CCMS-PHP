<?php
/**
 * لیست یادآورها
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$userId = $_SESSION['user_id'];

// فیلترها
$status = $_GET['status'] ?? 'all'; // all, pending, sent
$search = sanitize_input($_GET['search'] ?? '');

$sql = "SELECT * FROM reminders WHERE user_id = :user_id";
$params = [':user_id' => $userId];

if ($status === 'pending') {
    $sql .= " AND is_sent = 0 AND CONCAT(remind_date, ' ', remind_time) >= NOW()";
} elseif ($status === 'sent') {
    $sql .= " AND is_sent = 1";
}

if ($search) {
    $sql .= " AND (title LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY remind_date DESC, remind_time DESC";

$reminders = db()->select($sql, $params);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>یادآورها - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            font-weight: bold;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.5);
        }
        
        .filters {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 25px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            text-decoration: none;
            color: #333;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            font-weight: bold;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .reminders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .reminder-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-top: 5px solid #f093fb;
            transition: all 0.3s;
            position: relative;
        }
        
        .reminder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .reminder-card.sent {
            opacity: 0.7;
            border-top-color: #ccc;
        }
        
        .reminder-status {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-sent {
            background: #d4edda;
            color: #155724;
        }
        
        .reminder-datetime {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .datetime-box {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            text-align: center;
            min-width: 80px;
        }
        
        .datetime-box .date {
            font-size: 14px;
            font-weight: bold;
        }
        
        .datetime-box .time {
            font-size: 18px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .reminder-content h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .reminder-content p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .reminder-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            flex: 1;
            justify-content: center;
        }
        
        .btn-edit {
            background: #2196f3;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .no-data {
            background: white;
            padding: 60px 20px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            color: #999;
            grid-column: 1 / -1;
        }
        
        .no-data-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .reminders-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ یادآورها</h1>
            <a href="reminder.php?action=add" class="btn btn-primary">
                ➕ یادآور جدید
            </a>
        </div>
        
        <div class="filters">
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                    همه یادآورها
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                    در انتظار
                </a>
                <a href="?status=sent" class="filter-tab <?php echo $status === 'sent' ? 'active' : ''; ?>">
                    ارسال شده
                </a>
            </div>
            
            <form method="GET" class="search-box">
                <input type="hidden" name="status" value="<?php echo h($status); ?>">
                <input type="text" name="search" placeholder="جستجو در یادآورها..." 
                       value="<?php echo h($search); ?>">
                <button type="submit" class="btn btn-primary">🔍 جستجو</button>
            </form>
        </div>
        
        <div class="reminders-grid">
            <?php if (count($reminders) > 0): ?>
                <?php foreach ($reminders as $reminder): ?>
                    <?php
                    $isPast = strtotime($reminder['remind_date'] . ' ' . $reminder['remind_time']) < time();
                    ?>
                    <div class="reminder-card <?php echo $reminder['is_sent'] ? 'sent' : ''; ?>">
                        <span class="reminder-status <?php echo $reminder['is_sent'] ? 'status-sent' : 'status-pending'; ?>">
                            <?php echo $reminder['is_sent'] ? '✓ ارسال شده' : ($isPast ? '⏰ منقضی شده' : '⏳ در انتظار'); ?>
                        </span>
                        
                        <div class="reminder-datetime">
                            <div class="datetime-box">
                                <div class="date"><?php echo h($reminder['remind_date']); ?></div>
                                <div class="time"><?php echo h($reminder['remind_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="reminder-content">
                            <h3><?php echo h($reminder['title']); ?></h3>
                            <?php if ($reminder['description']): ?>
                                <p><?php echo nl2br(h($reminder['description'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="reminder-actions">
                            <a href="reminder.php?action=edit&id=<?php echo $reminder['id']; ?>" 
                               class="btn-sm btn-edit">✏️ ویرایش</a>
                            <a href="reminder.php?action=delete&id=<?php echo $reminder['id']; ?>" 
                               class="btn-sm btn-delete"
                               onclick="return confirm('آیا از حذف این یادآور اطمینان دارید؟')">🗑️ حذف</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">⏰</div>
                    <h3>هیچ یادآوری یافت نشد</h3>
                    <p>برای افزودن یادآور جدید از دکمه بالا استفاده کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>