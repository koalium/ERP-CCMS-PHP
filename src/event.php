<?php
/**
 * فرم افزودن/ویرایش رویداد
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$eventId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$error = '';
$success = '';
$event = null;

// بارگذاری رویداد برای ویرایش/مشاهده
if (in_array($action, ['edit', 'view', 'delete']) && $eventId) {
    $event = db()->selectOne(
        "SELECT * FROM calendar_events WHERE id = :id AND user_id = :user_id",
        [':id' => $eventId, ':user_id' => $userId]
    );
    
    if (!$event) {
        die('رویداد یافت نشد یا شما مجوز دسترسی ندارید.');
    }
}

// حذف رویداد
if ($action === 'delete' && $event && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if (db()->delete('calendar_events', 'id = :id', [':id' => $eventId])) {
            // ثبت لاگ
            db()->insert('logs', [
                'user_id' => $userId,
                'action' => 'delete_event',
                'module' => 'calendar',
                'record_id' => $eventId,
                'old_data' => json_encode($event),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            redirect(SITE_URL . '/events.php?msg=deleted');
        } else {
            $error = 'خطا در حذف رویداد';
        }
    } else {
        $error = 'خطای امنیتی - لطفاً دوباره تلاش کنید';
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی - لطفاً دوباره تلاش کنید';
    } else {
        $data = [
            'title' => sanitize_input($_POST['title'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'start_date' => sanitize_input($_POST['start_date'] ?? ''),
            'start_time' => sanitize_input($_POST['start_time'] ?? ''),
            'end_date' => sanitize_input($_POST['end_date'] ?? ''),
            'end_time' => sanitize_input($_POST['end_time'] ?? ''),
            'location' => sanitize_input($_POST['location'] ?? ''),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'color' => sanitize_input($_POST['color'] ?? '#667eea'),
            'is_all_day' => isset($_POST['is_all_day']) ? 1 : 0,
            'reminder_minutes' => (int)($_POST['reminder_minutes'] ?? 0)
        ];
        
        // اعتبارسنجی
        if (empty($data['title'])) {
            $error = 'عنوان رویداد الزامی است';
        } elseif (empty($data['start_date'])) {
            $error = 'تاریخ شروع الزامی است';
        } else {
            if ($action === 'add') {
                $data['user_id'] = $userId;
                $newId = db()->insert('calendar_events', $data);
                
                if ($newId) {
                    // ثبت لاگ
                    db()->insert('logs', [
                        'user_id' => $userId,
                        'action' => 'create_event',
                        'module' => 'calendar',
                        'record_id' => $newId,
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    redirect(SITE_URL . '/events.php?msg=added');
                } else {
                    $error = 'خطا در ذخیره رویداد';
                }
            } elseif ($action === 'edit') {
                $updated = db()->update(
                    'calendar_events',
                    $data,
                    'id = :id AND user_id = :user_id',
                    [':id' => $eventId, ':user_id' => $userId]
                );
                
                if ($updated !== false) {
                    // ثبت لاگ
                    db()->insert('logs', [
                        'user_id' => $userId,
                        'action' => 'update_event',
                        'module' => 'calendar',
                        'record_id' => $eventId,
                        'old_data' => json_encode($event),
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    redirect(SITE_URL . '/events.php?msg=updated');
                } else {
                    $error = 'خطا در به‌روزرسانی رویداد';
                }
            }
        }
    }
}

// عنوان صفحه
$pageTitle = $action === 'add' ? 'افزودن رویداد جدید' : 
             ($action === 'edit' ? 'ویرایش رویداد' : 
             ($action === 'delete' ? 'حذف رویداد' : 'مشاهده رویداد'));
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #f0f0f0;
        }
        
        .form-header h1 {
            color: #2c3e50;
            font-size: 26px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-back {
            padding: 10px 20px;
            background: #f5f5f5;
            color: #333;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .btn-back:hover {
            background: #e0e0e0;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 2px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 2px solid #cfc;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
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
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group input[type="color"] {
            height: 50px;
            cursor: pointer;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 3px solid #f0f0f0;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(244, 67, 54, 0.5);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .view-mode {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .view-mode strong {
            display: block;
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .view-mode p {
            color: #333;
            font-size: 15px;
        }
        
        .color-preview {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            vertical-align: middle;
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 25px 20px;
            }
            
            .form-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>
                    <?php 
                    if ($action === 'add') echo '➕ افزودن رویداد جدید';
                    elseif ($action === 'edit') echo '✏️ ویرایش رویداد';
                    elseif ($action === 'delete') echo '🗑️ حذف رویداد';
                    else echo '👁 مشاهده رویداد';
                    ?>
                </h1>
                <a href="events.php" class="btn-back">↩️ بازگشت</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($action === 'delete'): ?>
                <div class="alert alert-error">
                    ⚠️ آیا از حذف این رویداد اطمینان دارید؟ این عمل قابل بازگشت نیست!
                </div>
                
                <div class="view-mode">
                    <strong>عنوان</strong>
                    <p><?php echo h($event['title']); ?></p>
                </div>
                
                <div class="view-mode">
                    <strong>تاریخ</strong>
                    <p><?php echo h($event['start_date']); ?></p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">🗑️ تایید حذف</button>
                        <a href="events.php" class="btn btn-secondary">انصراف</a>
                    </div>
                </form>
                
            <?php elseif ($action === 'view'): ?>
                <div class="view-mode">
                    <strong>عنوان</strong>
                    <p><?php echo h($event['title']); ?></p>
                </div>
                
                <?php if ($event['description']): ?>
                    <div class="view-mode">
                        <strong>توضیحات</strong>
                        <p><?php echo nl2br(h($event['description'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="view-mode">
                        <strong>تاریخ شروع</strong>
                        <p><?php echo h($event['start_date']); ?></p>
                    </div>
                    
                    <div class="view-mode">
                        <strong>ساعت شروع</strong>
                        <p><?php echo h($event['start_time'] ?: 'تمام روز'); ?></p>
                    </div>
                    
                    <?php if ($event['end_date']): ?>
                        <div class="view-mode">
                            <strong>تاریخ پایان</strong>
                            <p><?php echo h($event['end_date']); ?></p>
                        </div>
                        
                        <div class="view-mode">
                            <strong>ساعت پایان</strong>
                            <p><?php echo h($event['end_time'] ?: '-'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($event['location']): ?>
                        <div class="view-mode full-width">
                            <strong>مکان</strong>
                            <p>📍 <?php echo h($event['location']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($event['category']): ?>
                        <div class="view-mode">
                            <strong>دسته‌بندی</strong>
                            <p><?php echo h($event['category']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="view-mode">
                        <strong>رنگ</strong>
                        <p>
                            <span class="color-preview" style="background: <?php echo h($event['color']); ?>;"></span>
                            <?php echo h($event['color']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="event.php?action=edit&id=<?php echo $event['id']; ?>" class="btn btn-primary">
                        ✏️ ویرایش
                    </a>
                    <a href="events.php" class="btn btn-secondary">بازگشت</a>
                </div>
                
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>عنوان رویداد *</label>
                            <input type="text" name="title" required 
                                   value="<?php echo h($event['title'] ?? ''); ?>"
                                   placeholder="مثلاً: جلسه هیئت مدیره">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>توضیحات</label>
                            <textarea name="description" rows="4" 
                                      placeholder="توضیحات کامل رویداد..."><?php echo h($event['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>📅 تاریخ شروع *</label>
                            <input type="text" name="start_date" class="jalali-date" required
                                   value="<?php echo h($event['start_date'] ?? ''); ?>"
                                   placeholder="۱۴۰۴/۰۱/۰۱">
                        </div>
                        
                        <div class="form-group">
                            <label>⏰ ساعت شروع</label>
                            <input type="time" name="start_time"
                                   value="<?php echo h($event['start_time'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>📅 تاریخ پایان</label>
                            <input type="text" name="end_date" class="jalali-date"
                                   value="<?php echo h($event['end_date'] ?? ''); ?>"
                                   placeholder="۱۴۰۴/۰۱/۰۱">
                        </div>
                        
                        <div class="form-group">
                            <label>⏰ ساعت پایان</label>
                            <input type="time" name="end_time"
                                   value="<?php echo h($event['end_time'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>📍 مکان</label>
                            <input type="text" name="location"
                                   value="<?php echo h($event['location'] ?? ''); ?>"
                                   placeholder="آدرس یا نام مکان">
                        </div>
                        
                        <div class="form-group">
                            <label>📂 دسته‌بندی</label>
                            <input type="text" name="category"
                                   value="<?php echo h($event['category'] ?? ''); ?>"
                                   placeholder="کاری، شخصی، جلسه، ...">
                        </div>
                        
                        <div class="form-group">
                            <label>🎨 رنگ</label>
                            <input type="color" name="color"
                                   value="<?php echo h($event['color'] ?? '#667eea'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>⏰ یادآوری (دقیقه قبل)</label>
                            <select name="reminder_minutes">
                                <option value="0" <?php echo ($event['reminder_minutes'] ?? 0) == 0 ? 'selected' : ''; ?>>بدون یادآوری</option>
                                <option value="5" <?php echo ($event['reminder_minutes'] ?? 0) == 5 ? 'selected' : ''; ?>>۵ دقیقه قبل</option>
                                <option value="15" <?php echo ($event['reminder_minutes'] ?? 0) == 15 ? 'selected' : ''; ?>>۱۵ دقیقه قبل</option>
                                <option value="30" <?php echo ($event['reminder_minutes'] ?? 0) == 30 ? 'selected' : ''; ?>>۳۰ دقیقه قبل</option>
                                <option value="60" <?php echo ($event['reminder_minutes'] ?? 0) == 60 ? 'selected' : ''; ?>>۱ ساعت قبل</option>
                                <option value="1440" <?php echo ($event['reminder_minutes'] ?? 0) == 1440 ? 'selected' : ''; ?>>۱ روز قبل</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_all_day" id="is_all_day"
                                       <?php echo ($event['is_all_day'] ?? 0) ? 'checked' : ''; ?>>
                                <label for="is_all_day">رویداد تمام روز</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? '➕ ذخیره رویداد' : '💾 به‌روزرسانی'; ?>
                        </button>
                        <a href="events.php" class="btn btn-secondary">انصراف</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // اضافه کردن datepicker به فیلدهای تاریخ
            initJalaliDatePicker('.jalali-date');
            
            // غیرفعال کردن فیلد زمان در حالت تمام روز
            const allDayCheckbox = document.getElementById('is_all_day');
            const timeInputs = document.querySelectorAll('input[type="time"]');
            
            if (allDayCheckbox) {
                allDayCheckbox.addEventListener('change', function() {
                    timeInputs.forEach(input => {
                        input.disabled = this.checked;
                        if (this.checked) {
                            input.value = '';
                        }
                    });
                });
                
                // اعمال در بارگذاری
                if (allDayCheckbox.checked) {
                    timeInputs.forEach(input => {
                        input.disabled = true;
                    });
                }
            }
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>