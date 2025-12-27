<?php
/**
 * تقویم و رویدادها
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

// دریافت ماه و سال (برای سادگی از تاریخ میلادی استفاده می‌شود)
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');

// محاسبه ماه قبل و بعد
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// دریافت رویدادهای ماه جاری
$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

$events = db()->select(
    "SELECT * FROM calendar_events 
     WHERE user_id = :user_id 
     AND start_date BETWEEN :start_date AND :end_date
     ORDER BY start_date, start_time",
    [
        ':user_id' => $_SESSION['user_id'],
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ]
);

// گروه‌بندی رویدادها بر اساس روز
$eventsByDay = [];
foreach ($events as $event) {
    $day = date('j', strtotime($event['start_date']));
    if (!isset($eventsByDay[$day])) {
        $eventsByDay[$day] = [];
    }
    $eventsByDay[$day][] = $event;
}

// دریافت یادآورهای ماه جاری
$reminders = db()->select(
    "SELECT * FROM reminders 
     WHERE user_id = :user_id 
     AND remind_date BETWEEN :start_date AND :end_date
     AND is_sent = 0
     ORDER BY remind_date, remind_time",
    [
        ':user_id' => $_SESSION['user_id'],
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ]
);

// محاسبه اطلاعات تقویم
$firstDayOfMonth = date('N', strtotime($startDate)); // 1 (Monday) to 7 (Sunday)
$daysInMonth = date('t', strtotime($startDate));
$monthName = date('F', strtotime($startDate));
$monthNames = [
    'January' => 'ژانویه', 'February' => 'فوریه', 'March' => 'مارس',
    'April' => 'آوریل', 'May' => 'مه', 'June' => 'ژوئن',
    'July' => 'جولای', 'August' => 'اوت', 'September' => 'سپتامبر',
    'October' => 'اکتبر', 'November' => 'نوامبر', 'December' => 'دسامبر'
];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقویم - <?php echo SITE_TITLE; ?></title>
    <style>
        .calendar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(155, 89, 182, 0.3);
        }
        
        .calendar-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .month-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: transform 0.2s;
        }
        
        .nav-btn:hover {
            transform: scale(1.05);
        }
        
        .current-month {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            min-width: 150px;
            text-align: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
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
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Calendar Grid */
        .calendar-wrapper {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        
        .calendar-main {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .calendar-sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .calendar-day-header {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 8px;
            position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .calendar-day:hover {
            background: #f8f9fa;
        }
        
        .calendar-day.empty {
            background: #f5f5f5;
            cursor: default;
        }
        
        .calendar-day.today {
            background: #fff3e0;
            border: 2px solid #ff9800;
        }
        
        .day-number {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .calendar-day.today .day-number {
            color: #ff9800;
        }
        
        .day-events {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .event-dot {
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 11px;
            color: white;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .event-dot:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        /* Event List */
        .events-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .event-item {
            padding: 15px;
            border-right: 4px solid #9b59b6;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .event-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .event-time {
            font-weight: bold;
            color: #9b59b6;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .event-title {
            color: #2c3e50;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .event-location {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        /* Reminders */
        .reminder-item {
            padding: 12px;
            background: #fff3cd;
            border-right: 4px solid #ffc107;
            margin-bottom: 8px;
            border-radius: 6px;
        }
        
        .reminder-time {
            font-weight: bold;
            color: #f57c00;
            font-size: 12px;
        }
        
        .reminder-title {
            color: #2c3e50;
            font-size: 13px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .close-btn {
            font-size: 28px;
            cursor: pointer;
            color: #7f8c8d;
            background: none;
            border: none;
        }
        
        .close-btn:hover {
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #9b59b6;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 1024px) {
            .calendar-wrapper {
                grid-template-columns: 1fr;
            }
            
            .calendar-grid {
                font-size: 12px;
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 5px;
            }
        }
        
        @media (max-width: 768px) {
            .calendar-controls {
                flex-direction: column;
                gap: 15px;
            }
            
            .current-month {
                font-size: 20px;
            }
            
            .calendar-day-header {
                padding: 10px 5px;
                font-size: 12px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="calendar-container">
        <!-- Calendar Header -->
        <div class="calendar-header">
            <h1>📅 تقویم و رویدادها</h1>
            <p>مدیریت رویدادها، جلسات و یادآورها</p>
        </div>
        
        <!-- Calendar Controls -->
        <div class="calendar-controls">
            <div class="month-nav">
                <a href="?year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>" class="nav-btn">◀ ماه قبل</a>
                <div class="current-month">
                    <?php echo $monthNames[$monthName] . ' ' . en2fa($year); ?>
                </div>
                <a href="?year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>" class="nav-btn">ماه بعد ▶</a>
            </div>
            
            <div class="action-buttons">
                <button onclick="showEventModal()" class="btn btn-success">➕ رویداد جدید</button>
                <a href="reminders.php" class="btn btn-primary">🔔 یادآورها</a>
                <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('m'); ?>" class="btn btn-primary">📍 امروز</a>
            </div>
        </div>
        
        <!-- Calendar Wrapper -->
        <div class="calendar-wrapper">
            <!-- Calendar Main -->
            <div class="calendar-main">
                <div class="calendar-grid">
                    <!-- Day Headers -->
                    <div class="calendar-day-header">شنبه</div>
                    <div class="calendar-day-header">یکشنبه</div>
                    <div class="calendar-day-header">دوشنبه</div>
                    <div class="calendar-day-header">سه‌شنبه</div>
                    <div class="calendar-day-header">چهارشنبه</div>
                    <div class="calendar-day-header">پنجشنبه</div>
                    <div class="calendar-day-header">جمعه</div>
                    
                    <!-- Empty days before month starts -->
                    <?php 
                    $dayOfWeek = ($firstDayOfMonth == 7) ? 0 : $firstDayOfMonth; // Saturday = 0
                    for ($i = 0; $i < $dayOfWeek; $i++): 
                    ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                    
                    <!-- Days of month -->
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                        $date = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $isToday = ($date == date('Y-m-d'));
                        $dayEvents = $eventsByDay[$day] ?? [];
                        
                        $colors = ['#9b59b6', '#3498db', '#27ae60', '#f39c12', '#e74c3c'];
                    ?>
                        <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?>" 
                             onclick="showDayEvents('<?php echo $date; ?>')">
                            <div class="day-number"><?php echo en2fa($day); ?></div>
                            <div class="day-events">
                                <?php foreach (array_slice($dayEvents, 0, 3) as $i => $event): ?>
                                    <div class="event-dot" 
                                         style="background: <?php echo $event['color'] ?: $colors[$i % 5]; ?>;"
                                         title="<?php echo h($event['title']); ?>">
                                        <?php echo h(mb_substr($event['title'], 0, 15)); ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($dayEvents) > 3): ?>
                                    <div style="font-size: 10px; color: #7f8c8d; text-align: center;">
                                        +<?php echo en2fa(count($dayEvents) - 3); ?> مورد دیگر
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Calendar Sidebar -->
            <div class="calendar-sidebar">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">📋 رویدادهای این ماه</h3>
                <div class="events-list">
                    <?php if (count($events) > 0): ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-item" onclick="showEventDetails(<?php echo $event['id']; ?>)">
                                <div class="event-time">
                                    📅 <?php echo en2fa(date('Y/m/d', strtotime($event['start_date']))); ?>
                                    <?php if ($event['start_time']): ?>
                                        - ⏰ <?php echo en2fa(date('H:i', strtotime($event['start_time']))); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="event-title"><?php echo h($event['title']); ?></div>
                                <?php if ($event['location']): ?>
                                    <div class="event-location">📍 <?php echo h($event['location']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            📅 رویدادی برای این ماه ثبت نشده است
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($reminders) > 0): ?>
                    <h3 style="margin: 20px 0 15px; color: #2c3e50;">🔔 یادآورها</h3>
                    <div>
                        <?php foreach (array_slice($reminders, 0, 5) as $reminder): ?>
                            <div class="reminder-item">
                                <div class="reminder-time">
                                    <?php echo en2fa(date('Y/m/d H:i', strtotime($reminder['remind_date'] . ' ' . $reminder['remind_time']))); ?>
                                </div>
                                <div class="reminder-title"><?php echo h($reminder['title']); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($reminders) > 5): ?>
                            <a href="reminders.php" style="display: block; text-align: center; margin-top: 10px; color: #9b59b6;">
                                مشاهده همه یادآورها
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">رویداد جدید</div>
                <button class="close-btn" onclick="closeEventModal()">×</button>
            </div>
            
            <form action="event.php" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>عنوان رویداد *</label>
                    <input type="text" name="title" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تاریخ شروع *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>زمان شروع</label>
                        <input type="time" name="start_time">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تاریخ پایان</label>
                        <input type="date" name="end_date">
                    </div>
                    
                    <div class="form-group">
                        <label>زمان پایان</label>
                        <input type="time" name="end_time">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>مکان</label>
                    <input type="text" name="location">
                </div>
                
                <div class="form-group">
                    <label>دسته‌بندی</label>
                    <select name="category">
                        <option value="">انتخاب کنید</option>
                        <option value="meeting">جلسه</option>
                        <option value="task">وظیفه</option>
                        <option value="personal">شخصی</option>
                        <option value="project">پروژه</option>
                        <option value="other">سایر</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>رنگ</label>
                    <input type="color" name="color" value="#9b59b6">
                </div>
                
                <div class="form-group">
                    <label>توضیحات</label>
                    <textarea name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_all_day" value="1">
                        تمام روز
                    </label>
                </div>
                
                <div class="form-group">
                    <label>یادآوری (دقیقه قبل)</label>
                    <select name="reminder_minutes">
                        <option value="0">بدون یادآوری</option>
                        <option value="15">۱۵ دقیقه قبل</option>
                        <option value="30">۳۰ دقیقه قبل</option>
                        <option value="60">۱ ساعت قبل</option>
                        <option value="1440">۱ روز قبل</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeEventModal()" class="btn" style="background: #95a5a6;">
                        انصراف
                    </button>
                    <button type="submit" class="btn btn-success">
                        ذخیره رویداد
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showEventModal() {
            document.getElementById('eventModal').classList.add('show');
        }
        
        function closeEventModal() {
            document.getElementById('eventModal').classList.remove('show');
        }
        
        function showDayEvents(date) {
            window.location.href = 'events.php?date=' + date;
        }
        
        function showEventDetails(id) {
            window.location.href = 'event.php?action=view&id=' + id;
        }
        
        // Close modal on outside click
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEventModal();
            }
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>