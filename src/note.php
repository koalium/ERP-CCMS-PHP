<?php
/**
 * فرم یادداشت - افزودن/ویرایش/مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$userId = $_SESSION['user_id'];
$action = sanitize_input($_GET['action'] ?? 'add');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// دریافت یادداشت برای ویرایش یا مشاهده
$note = null;
if ($id > 0 && in_array($action, ['edit', 'view'])) {
    $note = db()->selectOne(
        "SELECT n.*, u.fullname as creator_name
         FROM notes n
         LEFT JOIN users u ON u.id = n.user_id
         WHERE n.id = :id",
        [':id' => $id]
    );
    
    if (!$note) {
        die('یادداشت یافت نشد.');
    }
    
    // چک دسترسی
    $canAccess = false;
    if ($note['user_id'] == $userId) {
        $canAccess = true; // صاحب یادداشت
    } elseif (!$note['is_private']) {
        // یادداشت عمومی یا به اشتراک گذاشته شده
        $sharedWith = json_decode($note['shared_with'] ?? '[]', true);
        if (in_array($userId, $sharedWith)) {
            $canAccess = true;
        }
    }
    
    if (!$canAccess && $action !== 'view') {
        die('شما مجوز دسترسی به این یادداشت را ندارید.');
    }
}

// حذف یادداشت
if ($action === 'delete' && $id > 0) {
    if ($note && $note['user_id'] == $userId) {
        if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
            db()->delete('notes', 'id = :id', [':id' => $id]);
            
            db()->insert('logs', [
                'user_id' => $userId,
                'action' => 'delete_note',
                'module' => 'notes',
                'record_id' => $id,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            redirect(SITE_URL . '/notes.php?msg=deleted');
        }
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $title = sanitize_input($_POST['title'] ?? '');
        $content = $_POST['content'] ?? ''; // محتوا را sanitize نمی‌کنیم چون ممکن است شامل HTML باشد
        $category = sanitize_input($_POST['category'] ?? '');
        $tags = sanitize_input($_POST['tags'] ?? '');
        $isPrivate = isset($_POST['is_private']) ? 1 : 0;
        $sharedWith = [];
        
        if (!$isPrivate && isset($_POST['shared_with']) && is_array($_POST['shared_with'])) {
            $sharedWith = array_map('intval', $_POST['shared_with']);
        }
        
        // اعتبارسنجی
        if (empty($title)) {
            $error = 'لطفاً عنوان یادداشت را وارد کنید.';
        } else {
            $data = [
                'title' => $title,
                'content' => $content,
                'category' => $category,
                'tags' => $tags,
                'is_private' => $isPrivate,
                'shared_with' => json_encode($sharedWith)
            ];
            
            if ($action === 'add') {
                $data['user_id'] = $userId;
                $newId = db()->insert('notes', $data);
                
                if ($newId) {
                    db()->insert('logs', [
                        'user_id' => $userId,
                        'action' => 'create_note',
                        'module' => 'notes',
                        'record_id' => $newId,
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    redirect(SITE_URL . '/notes.php?msg=added');
                } else {
                    $error = 'خطا در ذخیره یادداشت.';
                }
            } elseif ($action === 'edit') {
                if ($note && $note['user_id'] == $userId) {
                    $updated = db()->update('notes', $data, 'id = :id', [':id' => $id]);
                    
                    if ($updated !== false) {
                        db()->insert('logs', [
                            'user_id' => $userId,
                            'action' => 'update_note',
                            'module' => 'notes',
                            'record_id' => $id,
                            'old_data' => json_encode($note),
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        redirect(SITE_URL . '/notes.php?msg=updated');
                    } else {
                        $error = 'خطا در به‌روزرسانی یادداشت.';
                    }
                }
            }
        }
    }
}

// دریافت لیست کاربران برای اشتراک‌گذاری
$users = db()->select(
    "SELECT id, fullname, username FROM users WHERE id != :user_id AND is_active = 1 ORDER BY fullname",
    [':user_id' => $userId]
);

$readonly = ($action === 'view' || ($note && $note['user_id'] != $userId)) ? 'readonly disabled' : '';
$canEdit = !$readonly;
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'افزودن' : ($action === 'edit' ? 'ویرایش' : 'مشاهده'); ?> یادداشت</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: #f5f7fa;
            direction: rtl;
        }
        
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .required {
            color: #f44336;
        }
        
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group textarea {
            min-height: 300px;
            resize: vertical;
            line-height: 1.6;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input[readonly],
        .form-group select:disabled,
        .form-group textarea[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
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
        
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .user-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: white;
            border-radius: 6px;
        }
        
        .user-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .user-checkbox label {
            font-size: 13px;
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            border-right: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-box p {
            color: #555;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .helper-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .users-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function toggleSharedUsers() {
            const isPrivate = document.getElementById('is_private').checked;
            const sharedUsersDiv = document.getElementById('shared_users_div');
            
            if (sharedUsersDiv) {
                sharedUsersDiv.style.display = isPrivate ? 'none' : 'block';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                📝 
                <?php 
                echo $action === 'add' ? 'یادداشت جدید' : 
                     ($action === 'edit' ? 'ویرایش یادداشت' : 'مشاهده یادداشت');
                ?>
            </h1>
            <a href="notes.php" class="btn btn-back">⬅️ بازگشت</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'view' && $note): ?>
            <div class="info-box">
                <h3>اطلاعات یادداشت</h3>
                <p><strong>نویسنده:</strong> <?php echo h($note['creator_name']); ?></p>
                <p><strong>تاریخ ایجاد:</strong> <?php echo en2fa(date('Y/m/d H:i', strtotime($note['created_at']))); ?></p>
                <p><strong>آخرین ویرایش:</strong> <?php echo en2fa(date('Y/m/d H:i', strtotime($note['updated_at']))); ?></p>
                <p><strong>نوع:</strong> <?php echo $note['is_private'] ? '🔒 خصوصی' : '👥 عمومی/اشتراک‌گذاری شده'; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label>
                        عنوان یادداشت <span class="required">*</span>
                    </label>
                    <input type="text" name="title" 
                           value="<?php echo h($note['title'] ?? ''); ?>" 
                           required <?php echo $readonly; ?> autofocus>
                </div>
                
                <div class="form-group">
                    <label>محتوای یادداشت</label>
                    <textarea name="content" <?php echo $readonly; ?>><?php echo h($note['content'] ?? ''); ?></textarea>
                    <?php if ($canEdit): ?>
                        <div class="helper-text">💡 می‌توانید از خطوط جدید و متن چند خطی استفاده کنید</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>دسته‌بندی</label>
                    <input type="text" name="category" 
                           value="<?php echo h($note['category'] ?? ''); ?>" 
                           placeholder="کاری، شخصی، ایده، پروژه..."
                           <?php echo $readonly; ?>>
                </div>
                
                <div class="form-group">
                    <label>برچسب‌ها</label>
                    <input type="text" name="tags" 
                           value="<?php echo h($note['tags'] ?? ''); ?>" 
                           placeholder="برچسب1, برچسب2, برچسب3"
                           <?php echo $readonly; ?>>
                    <?php if ($canEdit): ?>
                        <div class="helper-text">💡 برچسب‌ها را با کاما (,) از هم جدا کنید</div>
                    <?php endif; ?>
                </div>
                
                <?php if ($canEdit): ?>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_private" name="is_private" 
                                   value="1" 
                                   <?php echo ($note['is_private'] ?? 1) ? 'checked' : ''; ?>
                                   onchange="toggleSharedUsers()">
                            <label for="is_private">🔒 یادداشت خصوصی (فقط من می‌بینم)</label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="shared_users_div" 
                         style="display: <?php echo ($note['is_private'] ?? 1) ? 'none' : 'block'; ?>">
                        <label>👥 اشتراک‌گذاری با:</label>
                        <div class="users-grid">
                            <?php 
                            $sharedWith = json_decode($note['shared_with'] ?? '[]', true);
                            foreach ($users as $user): 
                            ?>
                                <div class="user-checkbox">
                                    <input type="checkbox" 
                                           id="user_<?php echo $user['id']; ?>" 
                                           name="shared_with[]" 
                                           value="<?php echo $user['id']; ?>"
                                           <?php echo in_array($user['id'], $sharedWith) ? 'checked' : ''; ?>>
                                    <label for="user_<?php echo $user['id']; ?>">
                                        <?php echo h($user['fullname']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="helper-text">💡 کاربران انتخاب شده می‌توانند این یادداشت را مشاهده کنند</div>
                    </div>
                <?php endif; ?>
                
                <?php if ($canEdit): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            💾 <?php echo $action === 'add' ? 'ذخیره یادداشت' : 'به‌روزرسانی'; ?>
                        </button>
                        <a href="notes.php" class="btn btn-back">❌ انصراف</a>
                    </div>
                <?php else: ?>
                    <div class="form-actions">
                        <?php if ($note && $note['user_id'] == $userId): ?>
                            <a href="note.php?action=edit&id=<?php echo $note['id']; ?>" 
                               class="btn btn-primary">✏️ ویرایش</a>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('آیا از حذف این یادداشت اطمینان دارید؟');">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-danger">🗑️ حذف</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>