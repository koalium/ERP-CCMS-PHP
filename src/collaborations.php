<?php
/**
 * مدیریت تیم پروژه (گروه کاری)
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('projects', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$projectId = (int)($_GET['project_id'] ?? 0);
$action = sanitize_input($_GET['action'] ?? 'list');
$memberId = (int)($_GET['id'] ?? 0);

if (!$projectId) {
    die('پروژه مشخص نشده است.');
}

// بارگذاری پروژه
$project = db()->selectOne("SELECT * FROM projects WHERE id = :id", [':id' => $projectId]);
if (!$project) {
    die('پروژه یافت نشد.');
}

$error = '';
$success = '';

// ایجاد جدول اعضای تیم اگر وجود ندارد
$createTableSql = "CREATE TABLE IF NOT EXISTS project_team (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    responsibilities TEXT,
    can_view TINYINT(1) DEFAULT 1,
    can_edit TINYINT(1) DEFAULT 0,
    can_approve TINYINT(1) DEFAULT 0,
    hourly_rate DECIMAL(10, 2),
    joined_date DATE,
    left_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_user (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSql);

// حذف عضو
if ($action === 'remove' && $memberId && check_permission('projects', PERMISSION_WRITE)) {
    if (db()->delete('project_team', 'id = :id AND project_id = :pid', [':id' => $memberId, ':pid' => $projectId])) {
        redirect(SITE_URL . '/collaborations.php?project_id=' . $projectId . '&removed=1');
    }
}

// پردازش افزودن عضو
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی.';
    } else {
        $data = [
            'project_id' => $projectId,
            'user_id' => (int)($_POST['user_id'] ?? 0),
            'role' => sanitize_input($_POST['role'] ?? ''),
            'responsibilities' => sanitize_input($_POST['responsibilities'] ?? ''),
            'can_view' => isset($_POST['can_view']) ? 1 : 0,
            'can_edit' => isset($_POST['can_edit']) ? 1 : 0,
            'can_approve' => isset($_POST['can_approve']) ? 1 : 0,
            'hourly_rate' => (float)($_POST['hourly_rate'] ?? 0) ?: null,
            'joined_date' => sanitize_input($_POST['joined_date'] ?? date('Y-m-d')),
            'is_active' => 1
        ];
        
        // اعتبارسنجی
        if (empty($data['user_id'])) {
            $error = 'لطفاً یک کاربر انتخاب کنید.';
        } elseif (empty($data['role'])) {
            $error = 'نقش عضو الزامی است.';
        } else {
            // چک تکراری
            $exists = db()->exists('project_team', 
                'project_id = :pid AND user_id = :uid',
                [':pid' => $projectId, ':uid' => $data['user_id']]
            );
            
            if ($exists) {
                $error = 'این کاربر قبلاً به تیم اضافه شده است.';
            } else {
                $newId = db()->insert('project_team', $data);
                
                if ($newId) {
                    db()->insert('logs', [
                        'user_id' => $_SESSION['user_id'],
                        'action' => 'add_team_member',
                        'module' => 'projects',
                        'record_id' => $projectId,
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $success = 'عضو با موفقیت به تیم اضافه شد.';
                    $action = 'list';
                } else {
                    $error = 'خطا در افزودن عضو.';
                }
            }
        }
    }
}

// پردازش ویرایش مجوزها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $memberId) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی.';
    } else {
        $data = [
            'role' => sanitize_input($_POST['role'] ?? ''),
            'responsibilities' => sanitize_input($_POST['responsibilities'] ?? ''),
            'can_view' => isset($_POST['can_view']) ? 1 : 0,
            'can_edit' => isset($_POST['can_edit']) ? 1 : 0,
            'can_approve' => isset($_POST['can_approve']) ? 1 : 0,
            'hourly_rate' => (float)($_POST['hourly_rate'] ?? 0) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $updated = db()->update('project_team', $data, 'id = :id', [':id' => $memberId]);
        
        if ($updated !== false) {
            $success = 'اطلاعات عضو به‌روزرسانی شد.';
            $action = 'list';
        } else {
            $error = 'خطا در به‌روزرسانی.';
        }
    }
}

if (isset($_GET['removed'])) {
    $success = 'عضو با موفقیت از تیم حذف شد.';
}

// بارگذاری اعضای تیم
$teamMembers = db()->select(
    "SELECT pt.*, u.fullname, u.email, u.username
     FROM project_team pt
     INNER JOIN users u ON u.id = pt.user_id
     WHERE pt.project_id = :pid
     ORDER BY pt.is_active DESC, pt.role, u.fullname",
    [':pid' => $projectId]
);

// بارگذاری کاربران موجود
$availableUsers = db()->select(
    "SELECT id, fullname, email FROM users 
     WHERE is_active = 1 
     AND id NOT IN (SELECT user_id FROM project_team WHERE project_id = :pid)
     ORDER BY fullname",
    [':pid' => $projectId]
);

// اطلاعات عضو برای ویرایش
$member = null;
if ($action === 'edit' && $memberId) {
    $member = db()->selectOne(
        "SELECT pt.*, u.fullname FROM project_team pt 
         INNER JOIN users u ON u.id = pt.user_id 
         WHERE pt.id = :id AND pt.project_id = :pid",
        [':id' => $memberId, ':pid' => $projectId]
    );
    if (!$member) {
        $action = 'list';
    }
}

// نقش‌های پیش‌فرض
$defaultRoles = [
    'مدیر پروژه',
    'مدیر فنی',
    'مهندس طراحی',
    'مهندس کنترل پروژه',
    'مسئول تدارکات',
    'مسئول کیفیت',
    'مسئول ایمنی',
    'کارشناس فنی',
    'ناظر',
    'مشاور'
];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تیم پروژه - <?php echo h($project['title']); ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .alert {
            padding: 12px 15px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .member-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .member-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .member-card.inactive {
            opacity: 0.6;
        }
        
        .member-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .member-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .member-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .member-role {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .member-body {
            padding: 20px;
        }
        
        .member-info {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: bold;
        }
        
        .permissions {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        
        .permission-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .perm-view { background: #e3f2fd; color: #1976d2; }
        .perm-edit { background: #fff3e0; color: #f57c00; }
        .perm-approve { background: #e8f5e9; color: #388e3c; }
        
        .member-actions {
            display: flex;
            gap: 8px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 12px;
            border-radius: 6px;
            flex: 1;
            text-align: center;
            text-decoration: none;
        }
        
        .btn-edit { background: #2196f3; color: white; }
        .btn-remove { background: #f44336; color: white; }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            color: #555;
            font-size: 14px;
            font-weight: bold;
        }
        
        .form-group label span {
            color: #f44336;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .team-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="projects.php">پروژه‌ها</a>
                <span>›</span>
                <a href="project.php?action=view&id=<?php echo $projectId; ?>"><?php echo h($project['title']); ?></a>
                <span>›</span>
                <span>تیم پروژه</span>
            </div>
            <div class="header-content">
                <h1>👥 تیم پروژه</h1>
                <?php if ($action === 'list' && check_permission('projects', PERMISSION_WRITE)): ?>
                    <a href="?project_id=<?php echo $projectId; ?>&action=add" class="btn btn-primary">
                        ➕ افزودن عضو
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <!-- لیست اعضا -->
            <?php if (count($teamMembers) > 0): ?>
                <div class="team-grid">
                    <?php foreach ($teamMembers as $tm): ?>
                        <div class="member-card <?php echo $tm['is_active'] ? '' : 'inactive'; ?>">
                            <div class="member-header">
                                <div class="member-avatar">
                                    <?php echo mb_substr($tm['fullname'], 0, 1, 'UTF-8'); ?>
                                </div>
                                <div class="member-name"><?php echo h($tm['fullname']); ?></div>
                                <div class="member-role"><?php echo h($tm['role']); ?></div>
                            </div>
                            <div class="member-body">
                                <div class="member-info">
                                    <div class="info-row">
                                        <span class="info-label">نام کاربری:</span>
                                        <span class="info-value"><?php echo h($tm['username']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">تاریخ پیوستن:</span>
                                        <span class="info-value"><?php echo en2fa($tm['joined_date']); ?></span>
                                    </div>
                                    <?php if ($tm['hourly_rate']): ?>
                                        <div class="info-row">
                                            <span class="info-label">نرخ ساعتی:</span>
                                            <span class="info-value"><?php echo en2fa(number_format($tm['hourly_rate'])); ?> ریال</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!$tm['is_active']): ?>
                                        <div class="info-row">
                                            <span class="info-label">وضعیت:</span>
                                            <span class="info-value" style="color: #f44336;">❌ غیرفعال</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($tm['responsibilities']): ?>
                                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 10px;">
                                        <strong>مسئولیت‌ها:</strong><br>
                                        <?php echo h($tm['responsibilities']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="permissions">
                                    <?php if ($tm['can_view']): ?>
                                        <span class="permission-badge perm-view">👁 مشاهده</span>
                                    <?php endif; ?>
                                    <?php if ($tm['can_edit']): ?>
                                        <span class="permission-badge perm-edit">✏️ ویرایش</span>
                                    <?php endif; ?>
                                    <?php if ($tm['can_approve']): ?>
                                        <span class="permission-badge perm-approve">✅ تایید</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                                    <div class="member-actions">
                                        <a href="?project_id=<?php echo $projectId; ?>&action=edit&id=<?php echo $tm['id']; ?>" 
                                           class="btn-sm btn-edit">✏️ ویرایش</a>
                                        <a href="?project_id=<?php echo $projectId; ?>&action=remove&id=<?php echo $tm['id']; ?>" 
                                           class="btn-sm btn-remove"
                                           onclick="return confirm('آیا از حذف این عضو اطمینان دارید؟')">🗑️ حذف</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h2>هیچ عضوی به تیم اضافه نشده است</h2>
                    <p>برای شروع، اولین عضو تیم را اضافه کنید</p>
                    <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                        <a href="?project_id=<?php echo $projectId; ?>&action=add" class="btn btn-primary" style="margin-top: 20px;">
                            ➕ افزودن اولین عضو
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php elseif ($action === 'add'): ?>
            <!-- فرم افزودن عضو -->
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">➕ افزودن عضو به تیم</h2>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>کاربر <span>*</span></label>
                            <select name="user_id" required>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($availableUsers as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo h($u['fullname'] . ' (' . $u['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>نقش <span>*</span></label>
                            <input type="text" name="role" required list="rolesList" 
                                   placeholder="مثلاً: مدیر پروژه">
                            <datalist id="rolesList">
                                <?php foreach ($defaultRoles as $role): ?>
                                    <option value="<?php echo h($role); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ پیوستن</label>
                            <input type="date" name="joined_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>نرخ ساعتی (ریال)</label>
                            <input type="number" name="hourly_rate" min="0" step="1000" 
                                   placeholder="اختیاری">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>مسئولیت‌ها</label>
                            <textarea name="responsibilities" 
                                      placeholder="شرح مسئولیت‌های این عضو در پروژه..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>سطح دسترسی</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="can_view" id="can_view" checked>
                                    <label for="can_view">👁 مشاهده پروژه و وظایف</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="can_edit" id="can_edit">
                                    <label for="can_edit">✏️ ویرایش وظایف و ثبت گزارش</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="can_approve" id="can_approve">
                                    <label for="can_approve">✅ تایید وظایف و اسناد</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="?project_id=<?php echo $projectId; ?>" class="btn btn-secondary">↩ بازگشت</a>
                        <button type="submit" class="btn btn-primary">➕ افزودن عضو</button>
                    </div>
                </form>
            </div>
            
        <?php elseif ($action === 'edit' && $member): ?>
            <!-- فرم ویرایش عضو -->
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">✏️ ویرایش اطلاعات عضو: <?php echo h($member['fullname']); ?></h2>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>نقش <span>*</span></label>
                            <input type="text" name="role" required list="rolesList" 
                                   value="<?php echo h($member['role']); ?>">
                            <datalist id="rolesList">
                                <?php foreach ($defaultRoles as $role): ?>
                                    <option value="<?php echo h($role); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label>نرخ ساعتی (ریال)</label>
                            <input type="number" name="hourly_rate" min="0" step="1000"
                                   value="<?php echo h($member['hourly_rate'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>مسئولیت‌ها</label>
                            <textarea name="responsibilities"><?php echo h($member['responsibilities']); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>سطح دسترسی</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="can_view" id="can_view" 
                                           <?php echo $member['can_view'] ? 'checked' : ''; ?>>
                                    <label for="can_view">👁 مشاهده پروژه و وظایف</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="can_edit" id="can_edit"
                                           <?php echo $member['can_edit'] ? 'checked' : ''; ?>>
                                    <label for="can_edit">✏️ ویرایش وظایف و ثبت گزارش</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="can_approve" id="can_approve"
                                           <?php echo $member['can_approve'] ? 'checked' : ''; ?>>
                                    <label for="can_approve">✅ تایید وظایف و اسناد</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="is_active" id="is_active"
                                           <?php echo $member['is_active'] ? 'checked' : ''; ?>>
                                    <label for="is_active">✅ عضو فعال (در تیم حضور دارد)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="?project_id=<?php echo $projectId; ?>" class="btn btn-secondary">↩ بازگشت</a>
                        <button type="submit" class="btn btn-primary">💾 ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>