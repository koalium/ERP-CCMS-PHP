<?php
/**
 * ایجاد گروه جدید
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'ایجاد گروه جدید';
require_once 'header.php';

check_login();

$currentUserId = $_SESSION['user_id'];
$error = '';
$success = '';

// دریافت لیست کاربران
$users = db()->select(
    "SELECT id, fullname, email FROM users 
     WHERE id != :current_user AND is_active = 1 
     ORDER BY fullname",
    [':current_user' => $currentUserId]
);

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $members = $_POST['members'] ?? [];
        
        if (empty($name)) {
            $error = 'نام گروه الزامی است';
        } elseif (empty($members)) {
            $error = 'حداقل یک عضو انتخاب کنید';
        } else {
            // افزودن خود کاربر به اعضا
            if (!in_array($currentUserId, $members)) {
                $members[] = $currentUserId;
            }
            
            $groupId = db()->insert('message_groups', [
                'name' => $name,
                'description' => $description,
                'created_by' => $currentUserId,
                'members' => json_encode(array_map('intval', $members))
            ]);
            
            if ($groupId) {
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $currentUserId,
                    'action' => 'create_group',
                    'module' => 'messenger',
                    'record_id' => $groupId,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                redirect(SITE_URL . '/chat_advanced.php?type=group&group_id=' . $groupId);
            } else {
                $error = 'خطا در ایجاد گروه';
            }
        }
    }
}
?>

<style>
    .form-container {
        max-width: 700px;
        margin: 40px auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .form-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .form-header h1 {
        color: #2c3e50;
        font-size: 28px;
        margin-bottom: 10px;
    }
    
    .form-header p {
        color: #666;
        font-size: 14px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 600;
        font-size: 14px;
    }
    
    .form-group label.required::after {
        content: ' *';
        color: #f44336;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: border-color 0.3s;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
    }
    
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .members-section {
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .members-search {
        margin-bottom: 15px;
    }
    
    .members-search input {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 20px;
        font-size: 14px;
    }
    
    .member-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 8px;
        transition: background 0.2s;
        cursor: pointer;
    }
    
    .member-item:hover {
        background: #f8f9fa;
    }
    
    .member-item input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .member-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
    }
    
    .member-info {
        flex: 1;
    }
    
    .member-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }
    
    .member-email {
        font-size: 12px;
        color: #999;
    }
    
    .selected-count {
        background: #667eea;
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        display: inline-block;
        margin-top: 10px;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
    }
    
    .btn {
        padding: 14px 40px;
        border: none;
        border-radius: 25px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: inherit;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }
    
    .btn-secondary {
        background: #e0e0e0;
        color: #666;
    }
    
    .btn-secondary:hover {
        background: #d0d0d0;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
    }
    
    .alert-error {
        background: #ffebee;
        color: #c62828;
        border-right: 4px solid #f44336;
    }
    
    .alert-success {
        background: #e8f5e9;
        color: #2e7d32;
        border-right: 4px solid #4caf50;
    }
</style>

<div class="form-container">
    <div class="form-header">
        <h1>👥 ایجاد گروه جدید</h1>
        <p>یک گروه برای چت گروهی ایجاد کنید</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?php echo h($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?php echo h($success); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div class="form-group">
            <label class="required">نام گروه</label>
            <input type="text" name="name" placeholder="مثلاً: تیم پروژه A" 
                   value="<?php echo h($_POST['name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label>توضیحات</label>
            <textarea name="description" 
                      placeholder="توضیحات کوتاهی درباره این گروه..."><?php echo h($_POST['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="required">انتخاب اعضا</label>
            <div class="members-section">
                <div class="members-search">
                    <input type="text" id="searchMembers" placeholder="🔍 جستجوی اعضا...">
                </div>
                
                <div id="membersList">
                    <?php foreach ($users as $user): ?>
                        <label class="member-item" data-name="<?php echo h($user['fullname']); ?>" 
                               data-email="<?php echo h($user['email']); ?>">
                            <input type="checkbox" name="members[]" value="<?php echo $user['id']; ?>"
                                   onchange="updateCount()">
                            <div class="member-avatar">
                                <?php echo mb_substr($user['fullname'], 0, 1); ?>
                            </div>
                            <div class="member-info">
                                <div class="member-name"><?php echo h($user['fullname']); ?></div>
                                <div class="member-email"><?php echo h($user['email']); ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="selected-count" id="selectedCount">
                    ۰ عضو انتخاب شده
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <a href="messenger_advanced.php" class="btn btn-secondary">
                ← انصراف
            </a>
            <button type="submit" class="btn btn-primary">
                ✅ ایجاد گروه
            </button>
        </div>
    </form>
</div>

<script>
    // جستجو در اعضا
    document.getElementById('searchMembers').addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.member-item').forEach(item => {
            const name = item.dataset.name.toLowerCase();
            const email = item.dataset.email.toLowerCase();
            
            if (name.includes(search) || email.includes(search)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // به‌روزرسانی تعداد اعضا انتخاب شده
    function updateCount() {
        const checked = document.querySelectorAll('input[name="members[]"]:checked').length;
        const countElement = document.getElementById('selectedCount');
        
        const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const persianCount = checked.toString().split('').map(d => persianNumbers[parseInt(d)]).join('');
        
        countElement.textContent = `${persianCount} عضو انتخاب شده`;
    }
    
    // تنظیم تعداد اولیه
    updateCount();
</script>

<?php require_once 'footer.php'; ?>