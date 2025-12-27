<?php
/**
 * لیست رویدادهای تقویم
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

// دریافت رویدادها
$userId = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'list'; // list, calendar, month
$selectedDate = $_GET['date'] ?? null;

// فیلترها
$search = sanitize_input($_GET['search'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$startDate = $_GET['start'] ?? null;
$endDate = $_GET['end'] ?? null;

$sql = "SELECT * FROM calendar_events WHERE user_id = :user_id";
$params = [':user_id' => $userId];

if ($search) {
    $sql .= " AND (title LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($category) {
    $sql .= " AND category = :category";
    $params[':category'] = $category;
}

if ($startDate) {
    $sql .= " AND start_date >= :start_date";
    $params[':start_date'] = $startDate;
}

if ($endDate) {
    $sql .= " AND start_date <= :end_date";
    $params[':end_date'] = $endDate;
}

$sql .= " ORDER BY start_date DESC, start_time DESC";

$events = db()->select($sql, $params);

// دریافت دسته‌بندی‌ها
$categories = db()->select(
    "SELECT DISTINCT category FROM calendar_events 
     WHERE user_id = :user_id AND category IS NOT NULL AND category != ''",
    [':user_id' => $userId]
);

// تابع تبدیل تاریخ میلادی به جلالی برای نمایش
function formatJalaliDate($gregorianDate) {
    if (!$gregorianDate) return '-';
    
    $parts = explode('-', $gregorianDate);
    if (count($parts) !== 3) return $gregorianDate;
    
    // اینجا باید از تابع تبدیل استفاده شود - برای سادگی فارسی می‌کنیم
    $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    return str_replace($englishDigits, $persianDigits, $gregorianDate);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رویدادها - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .filters {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 25px;
        }
        
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 6px;
            color: #555;
            font-size: 13px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .view-switcher {
            display: flex;
            gap: 10px;
            background: #f5f5f5;
            padding: 5px;
            border-radius: 10px;
        }
        
        .view-btn {
            padding: 10px 20px;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .view-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
        }
        
        .events-container {
            display: grid;
            gap: 20px;
        }
        
        .event-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-right: 5px solid #667eea;
            transition: all 0.3s;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .event-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .event-date {
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            min-width: 80px;
        }
        
        .event-date .day {
            font-size: 32px;
            font-weight: bold;
            display: block;
        }
        
        .event-date .month {
            font-size: 14px;
            display: block;
        }
        
        .event-info {
            flex: 1;
        }
        
        .event-info h3 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 18px;
        }
        
        .event-info p {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .event-meta {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-time {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-location {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-category {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .event-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: #4caf50;
            color: white;
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
        }
        
        .no-data-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .event-card {
                grid-template-columns: 1fr;
            }
            
            .event-date {
                min-width: auto;
            }
            
            .event-actions {
                flex-direction: row;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 رویدادهای تقویم</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="view-switcher">
                    <button class="view-btn <?php echo $view === 'list' ? 'active' : ''; ?>" 
                            onclick="location.href='?view=list'">لیست</button>
                    <button class="view-btn <?php echo $view === 'calendar' ? 'active' : ''; ?>" 
                            onclick="location.href='calendar.php'">تقویم</button>
                </div>
                <a href="event.php?action=add" class="btn btn-primary">
                    ➕ رویداد جدید
                </a>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <input type="hidden" name="view" value="<?php echo h($view); ?>">
                
                <div class="form-group">
                    <label>🔍 جستجو</label>
                    <input type="text" name="search" placeholder="عنوان یا توضیحات..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>📂 دسته‌بندی</label>
                    <select name="category">
                        <option value="">همه</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo h($cat['category']); ?>" 
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo h($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>📅 از تاریخ</label>
                    <input type="text" name="start" class="jalali-date" 
                           placeholder="YYYY/MM/DD" value="<?php echo h($startDate); ?>">
                </div>
                
                <div class="form-group">
                    <label>📅 تا تاریخ</label>
                    <input type="text" name="end" class="jalali-date" 
                           placeholder="YYYY/MM/DD" value="<?php echo h($endDate); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="events-container">
            <?php if (count($events) > 0): ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <div class="event-date" style="border-right-color: <?php echo h($event['color'] ?: '#667eea'); ?>;">
                            <span class="day"><?php echo formatJalaliDate($event['start_date']); ?></span>
                            <span class="month"><?php echo $event['start_time'] ?: 'تمام روز'; ?></span>
                        </div>
                        
                        <div class="event-info">
                            <h3><?php echo h($event['title']); ?></h3>
                            <?php if ($event['description']): ?>
                                <p><?php echo h(mb_substr($event['description'], 0, 100)) . (mb_strlen($event['description']) > 100 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
                            <div class="event-meta">
                                <?php if ($event['start_time']): ?>
                                    <span class="badge badge-time">⏰ <?php echo h($event['start_time']); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($event['location']): ?>
                                    <span class="badge badge-location">📍 <?php echo h($event['location']); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($event['category']): ?>
                                    <span class="badge badge-category">📂 <?php echo h($event['category']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="event-actions">
                            <a href="event.php?action=view&id=<?php echo $event['id']; ?>" 
                               class="btn-sm btn-view">👁 مشاهده</a>
                            <a href="event.php?action=edit&id=<?php echo $event['id']; ?>" 
                               class="btn-sm btn-edit">✏️ ویرایش</a>
                            <a href="event.php?action=delete&id=<?php echo $event['id']; ?>" 
                               class="btn-sm btn-delete"
                               onclick="return confirm('آیا از حذف این رویداد اطمینان دارید؟')">🗑️ حذف</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">📅</div>
                    <h3>هیچ رویدادی یافت نشد</h3>
                    <p>برای افزودن رویداد جدید از دکمه بالا استفاده کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        // اضافه کردن datepicker به تمام فیلدهای تاریخ
        document.addEventListener('DOMContentLoaded', function() {
            initJalaliDatePicker('.jalali-date');
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>