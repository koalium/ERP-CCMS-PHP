<?php
/**
 * فرم افزودن/ویرایش یادآور
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$reminderId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$error = '';
$reminder = null;

// بارگذاری یادآور
if (in_array($action, ['edit', 'delete']) && $reminderId) {
    $reminder = db()->selectOne(
        "SELECT * FROM reminders WHERE id = :id AND user_id = :user_id",
        [':id' => $reminderId, ':user_id' => $userId]
    );
    
    if (!$reminder) {
        die('یادآور یافت نشد.');
    }
}

// حذف
if ($action === 'delete' && $reminder && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if (db()->delete('reminders', 'id = :id', [':id' => $reminderId])) {
            redirect(SITE_URL . '/reminders.php?msg=deleted');
        }
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی';
    } else {
        $data = [
            'title' => sanitize_input($_POST['title'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'remind_date' => sanitize_input($_POST['remind_date'] ?? ''),
            'remind_time' => sanitize_input($_POST['remind_time'] ?? '')
        ];
        
        if (empty($data['title'])) {
            $error = 'عنوان الزامی است';
        } elseif (empty($data['remind_date']) || empty($data['remind_time'])) {
            $error = 'تاریخ و ساعت یادآوری الزامی است';
        } else {
            if ($action === 'add') {
                $data['user_id'] = $userId;
                $data['is_sent'] = 0;
                
                if (db()->insert('reminders', $data)) {
                    redirect(SITE_URL . '/reminders.php?msg=added');
                } else {
                    $error = 'خطا در ذخیره';
                }
            } elseif ($action === 'edit') {
                if (db()->update('reminders', $data, 'id = :id', [':id' => $reminderId])) {
                    redirect(SITE_URL . '/reminders.php?msg=updated');
                } else {
                    $error = 'خطا در به‌روزرسانی';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'یادآور جدید' : 'ویرایش یادآور'; ?> - <?php echo SITE_TITLE; ?></title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
        }
        
        .form-card {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #f0f0f0;
        }
        
        .form-header h1 {
            color: #2c3e50;
            font-size: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 2px solid #fcc;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f093fb;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .datetime-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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
            justify-content: center;
            gap: 8px;
            flex: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.5);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-back {
            text-align: center;
            margin-top: 20px;
        }
        
        .btn-back a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .datetime-group {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 25px 20px;
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
                    <?php echo $action === 'add' ? '⏰ یادآور جدید' : '✏️ ویرایش یادآور'; ?>
                </h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($action === 'delete'): ?>
                <div class="alert alert-error">
                    آیا از حذف این یادآور اطمینان دارید؟
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">تایید حذف</button>
                        <a href="reminders.php" class="btn btn-secondary" style="text-decoration: none;">انصراف</a>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label>عنوان یادآور *</label>
                        <input type="text" name="title" required 
                               value="<?php echo h($reminder['title'] ?? ''); ?>"
                               placeholder="عنوان یادآور را وارد کنید">
                    </div>
                    
                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" rows="4" 
                                  placeholder="توضیحات تکمیلی..."><?php echo h($reminder['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="datetime-group">
                        <div class="form-group">
                            <label>📅 تاریخ یادآوری *</label>
                            <input type="text" name="remind_date" class="jalali-date" required
                                   value="<?php echo h($reminder['remind_date'] ?? ''); ?>"
                                   placeholder="۱۴۰۴/۰۱/۰۱">
                        </div>
                        
                        <div class="form-group">
                            <label>⏰ ساعت یادآوری *</label>
                            <input type="time" name="remind_time" required
                                   value="<?php echo h($reminder['remind_time'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? '➕ ذخیره یادآور' : '💾 به‌روزرسانی'; ?>
                        </button>
                        <a href="reminders.php" class="btn btn-secondary" style="text-decoration: none;">انصراف</a>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="btn-back">
                <a href="reminders.php">↩️ بازگشت به لیست یادآورها</a>
            </div>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initJalaliDatePicker('.jalali-date');
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>