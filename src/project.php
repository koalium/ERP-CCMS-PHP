<?php
/**
 * فرم پروژه - افزودن، ویرایش، مشاهده
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = sanitize_input($_GET['action'] ?? 'add');
$projectId = (int)($_GET['id'] ?? 0);
$activeTab = sanitize_input($_GET['tab'] ?? 'general');

// چک مجوز
if ($action === 'add' && !check_permission('projects', PERMISSION_WRITE)) {
    die('شما مجوز افزودن پروژه را ندارید.');
}

if ($action === 'edit' && !check_permission('projects', PERMISSION_WRITE)) {
    die('شما مجوز ویرایش پروژه را ندارید.');
}

$project = null;
$error = '';
$success = '';

// بارگذاری اطلاعات پروژه برای ویرایش/مشاهده
if (($action === 'edit' || $action === 'view') && $projectId) {
    $project = db()->selectOne("SELECT * FROM projects WHERE id = :id", [':id' => $projectId]);
    if (!$project) {
        die('پروژه یافت نشد.');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'view') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $data = [
            'code' => sanitize_input($_POST['code'] ?? ''),
            'title' => sanitize_input($_POST['title'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'client_contact_id' => (int)($_POST['client_contact_id'] ?? 0) ?: null,
            'manager_user_id' => (int)($_POST['manager_user_id'] ?? 0) ?: null,
            'status' => sanitize_input($_POST['status'] ?? 'draft'),
            'start_date' => sanitize_input($_POST['start_date'] ?? '') ?: null,
            'end_date' => sanitize_input($_POST['end_date'] ?? '') ?: null,
            'budget' => (float)str_replace(',', '', $_POST['budget'] ?? 0),
            'currency' => sanitize_input($_POST['currency'] ?? 'IRR'),
            'location' => sanitize_input($_POST['location'] ?? ''),
            'tags' => sanitize_input($_POST['tags'] ?? '')
        ];
        
        // اعتبارسنجی
        if (empty($data['code'])) {
            $error = 'کد پروژه الزامی است.';
        } elseif (empty($data['title'])) {
            $error = 'عنوان پروژه الزامی است.';
        } else {
            // چک تکراری نبودن کد
            $existing = db()->selectOne(
                "SELECT id FROM projects WHERE code = :code AND id != :id",
                [':code' => $data['code'], ':id' => $projectId]
            );
            
            if ($existing) {
                $error = 'این کد پروژه قبلاً استفاده شده است.';
            } else {
                if ($action === 'add') {
                    $data['created_by'] = $_SESSION['user_id'];
                    $newId = db()->insert('projects', $data);
                    
                    if ($newId) {
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'create',
                            'module' => 'projects',
                            'record_id' => $newId,
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        redirect(SITE_URL . '/project.php?action=edit&id=' . $newId . '&success=1');
                    } else {
                        $error = 'خطا در ایجاد پروژه.';
                    }
                } else {
                    $updated = db()->update('projects', $data, 'id = :id', [':id' => $projectId]);
                    
                    if ($updated !== false) {
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'update',
                            'module' => 'projects',
                            'record_id' => $projectId,
                            'old_data' => json_encode($project),
                            'new_data' => json_encode($data),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        $success = 'پروژه با موفقیت به‌روزرسانی شد.';
                        $project = db()->selectOne("SELECT * FROM projects WHERE id = :id", [':id' => $projectId]);
                    } else {
                        $error = 'خطا در به‌روزرسانی پروژه.';
                    }
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = 'پروژه با موفقیت ایجاد شد.';
}

// لیست مخاطبین (مشتریان)
$clients = db()->select("SELECT id, name FROM contacts WHERE is_customer = 1 AND is_active = 1 ORDER BY name");

// لیست کاربران (مدیران پروژه)
$users = db()->select("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname");

// اگر در حالت ویرایش، داده‌های مرتبط را بارگذاری کنید
$tasks = [];
$projectItems = [];
$documents = [];

if ($projectId) {
    // وظایف
    $tasks = db()->select(
        "SELECT t.*, u.fullname as assigned_name 
         FROM tasks t 
         LEFT JOIN users u ON u.id = t.assigned_to 
         WHERE t.project_id = :pid 
         ORDER BY t.created_at DESC LIMIT 10",
        [':pid' => $projectId]
    );
    
    // آیتم‌های پروژه
    $projectItems = db()->select(
        "SELECT * FROM project_items WHERE project_id = :pid ORDER BY sort_order, id",
        [':pid' => $projectId]
    );
    
    // اسناد
    $documents = db()->select(
        "SELECT * FROM documents WHERE project_id = :pid ORDER BY created_at DESC LIMIT 10",
        [':pid' => $projectId]
    );
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'پروژه جدید' : ($action === 'view' ? 'مشاهده پروژه' : 'ویرایش پروژه'); ?> - <?php echo SITE_TITLE; ?></title>
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
        
        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
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
        
        .tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .tab-links {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            overflow-x: auto;
        }
        
        .tab-link {
            padding: 15px 25px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            transition: all 0.3s;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
        }
        
        .tab-link:hover {
            color: #667eea;
            background: #f8f9fa;
        }
        
        .tab-link.active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 30px;
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
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .info-box {
            background: #f8f9fa;
            border-right: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 14px;
            font-weight: bold;
        }
        
        .tasks-list {
            list-style: none;
        }
        
        .task-item {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .task-title {
            font-size: 14px;
            color: #2c3e50;
        }
        
        .task-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-todo { background: #e0e0e0; color: #666; }
        .status-in_progress { background: #fff3e0; color: #f57c00; }
        .status-review { background: #e3f2fd; color: #1976d2; }
        .status-done { background: #e8f5e9; color: #388e3c; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .tab-links {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="breadcrumb">
                    <a href="projects.php">پروژه‌ها</a>
                    <span>›</span>
                    <span><?php echo $action === 'add' ? 'پروژه جدید' : h($project['title'] ?? ''); ?></span>
                </div>
                <h1>
                    <?php 
                    if ($action === 'add') {
                        echo '➕ پروژه جدید';
                    } elseif ($action === 'view') {
                        echo '👁 مشاهده پروژه';
                    } else {
                        echo '✏️ ویرایش پروژه';
                    }
                    ?>
                </h1>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="tabs">
                <div class="tab-links">
                    <button type="button" class="tab-link <?php echo $activeTab === 'general' ? 'active' : ''; ?>" 
                            onclick="switchTab('general')">📋 اطلاعات کلی</button>
                    
                    <?php if ($projectId): ?>
                        <button type="button" class="tab-link <?php echo $activeTab === 'tasks' ? 'active' : ''; ?>" 
                                onclick="switchTab('tasks')">✅ وظایف</button>
                        <button type="button" class="tab-link <?php echo $activeTab === 'items' ? 'active' : ''; ?>" 
                                onclick="switchTab('items')">📦 آیتم‌های پروژه</button>
                        <button type="button" class="tab-link <?php echo $activeTab === 'budget' ? 'active' : ''; ?>" 
                                onclick="switchTab('budget')">💰 بودجه</button>
                        <button type="button" class="tab-link <?php echo $activeTab === 'documents' ? 'active' : ''; ?>" 
                                onclick="switchTab('documents')">📁 اسناد</button>
                        <button type="button" class="tab-link <?php echo $activeTab === 'team' ? 'active' : ''; ?>" 
                                onclick="switchTab('team')">👥 تیم</button>
                    <?php endif; ?>
                </div>
                
                <!-- تب اطلاعات کلی -->
                <div class="tab-content <?php echo $activeTab === 'general' ? 'active' : ''; ?>" id="tab-general">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>کد پروژه <span>*</span></label>
                            <input type="text" name="code" required 
                                   value="<?php echo h($project['code'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>وضعیت <span>*</span></label>
                            <select name="status" required <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="draft" <?php echo ($project['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                                <option value="planning" <?php echo ($project['status'] ?? '') === 'planning' ? 'selected' : ''; ?>>برنامه‌ریزی</option>
                                <option value="active" <?php echo ($project['status'] ?? '') === 'active' ? 'selected' : ''; ?>>در حال اجرا</option>
                                <option value="on_hold" <?php echo ($project['status'] ?? '') === 'on_hold' ? 'selected' : ''; ?>>متوقف شده</option>
                                <option value="completed" <?php echo ($project['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                                <option value="cancelled" <?php echo ($project['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>عنوان پروژه <span>*</span></label>
                            <input type="text" name="title" required 
                                   value="<?php echo h($project['title'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>مشتری / کارفرما</label>
                            <select name="client_contact_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" 
                                            <?php echo ($project['client_contact_id'] ?? '') == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($client['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>مدیر پروژه</label>
                            <select name="manager_user_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo ($project['manager_user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($user['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ شروع</label>
                            <input type="date" name="start_date" 
                                   value="<?php echo h($project['start_date'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ پایان</label>
                            <input type="date" name="end_date" 
                                   value="<?php echo h($project['end_date'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>بودجه</label>
                            <input type="text" name="budget" 
                                   value="<?php echo h($project['budget'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>واحد پول</label>
                            <select name="currency" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                <option value="IRR" <?php echo ($project['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال (IRR)</option>
                                <option value="USD" <?php echo ($project['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار (USD)</option>
                                <option value="EUR" <?php echo ($project['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو (EUR)</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>محل اجرا</label>
                            <input type="text" name="location" 
                                   value="<?php echo h($project['location'] ?? ''); ?>"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>توضیحات</label>
                            <textarea name="description" <?php echo $action === 'view' ? 'disabled' : ''; ?>><?php echo h($project['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>برچسب‌ها (با کاما جدا کنید)</label>
                            <input type="text" name="tags" 
                                   value="<?php echo h($project['tags'] ?? ''); ?>"
                                   placeholder="مثال: صنعتی, ساختمانی, HVAC"
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <?php if ($action !== 'view'): ?>
                        <div class="form-actions">
                            <a href="projects.php" class="btn btn-secondary">↩ بازگشت</a>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '➕ ایجاد پروژه' : '💾 ذخیره تغییرات'; ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- تب وظایف -->
                <?php if ($projectId): ?>
                    <div class="tab-content <?php echo $activeTab === 'tasks' ? 'active' : ''; ?>" id="tab-tasks">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>وظایف پروژه</h3>
                            <a href="task.php?action=add&project_id=<?php echo $projectId; ?>" class="btn btn-primary">
                                ➕ وظیفه جدید
                            </a>
                        </div>
                        
                        <?php if (count($tasks) > 0): ?>
                            <ul class="tasks-list">
                                <?php foreach ($tasks as $task): ?>
                                    <li class="task-item">
                                        <div>
                                            <div class="task-title"><?php echo h($task['title']); ?></div>
                                            <small style="color: #666;">
                                                مسئول: <?php echo h($task['assigned_name'] ?: 'تعیین نشده'); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="task-status status-<?php echo $task['status']; ?>">
                                                <?php 
                                                $statuses = ['todo' => 'انجام نشده', 'in_progress' => 'در حال انجام', 'review' => 'بررسی', 'done' => 'انجام شده'];
                                                echo $statuses[$task['status']];
                                                ?>
                                            </span>
                                            <a href="task.php?action=view&id=<?php echo $task['id']; ?>" style="margin-right: 10px;">مشاهده</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="tasks.php?project_id=<?php echo $projectId; ?>" class="btn btn-secondary" style="margin-top: 15px;">
                                مشاهده همه وظایف
                            </a>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>هیچ وظیفه‌ای برای این پروژه تعریف نشده است.</p>
                                <a href="task.php?action=add&project_id=<?php echo $projectId; ?>" class="btn btn-primary" style="margin-top: 15px;">
                                    ➕ اولین وظیفه را ایجاد کنید
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- تب آیتم‌ها -->
                    <div class="tab-content <?php echo $activeTab === 'items' ? 'active' : ''; ?>" id="tab-items">
                        <h3>آیتم‌های پروژه</h3>
                        <p style="color: #666; margin-bottom: 20px;">
                            آیتم‌های مختلف این پروژه را مدیریت کنید (ایستگاه‌ها، تجهیزات، فیلترها و...)
                        </p>
                        <a href="project_items.php?project_id=<?php echo $projectId; ?>" class="btn btn-primary">
                            مدیریت آیتم‌ها
                        </a>
                    </div>
                    
                    <!-- تب بودجه -->
                    <div class="tab-content <?php echo $activeTab === 'budget' ? 'active' : ''; ?>" id="tab-budget">
                        <h3>بودجه و هزینه‌ها</h3>
                        <div class="info-box">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">بودجه کل</span>
                                    <span class="info-value"><?php echo en2fa(number_format($project['budget'] ?? 0)); ?> ریال</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">هزینه شده</span>
                                    <span class="info-value">در حال محاسبه...</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">باقیمانده</span>
                                    <span class="info-value">در حال محاسبه...</span>
                                </div>
                            </div>
                        </div>
                        <a href="project_budget.php?project_id=<?php echo $projectId; ?>" class="btn btn-primary">
                            مدیریت بودجه
                        </a>
                    </div>
                    
                    <!-- تب اسناد -->
                    <div class="tab-content <?php echo $activeTab === 'documents' ? 'active' : ''; ?>" id="tab-documents">
                        <h3>اسناد و مدارک پروژه</h3>
                        <a href="documents.php?project_id=<?php echo $projectId; ?>" class="btn btn-primary" style="margin-bottom: 20px;">
                            مدیریت اسناد
                        </a>
                        
                        <?php if (count($documents) > 0): ?>
                            <ul class="tasks-list">
                                <?php foreach ($documents as $doc): ?>
                                    <li class="task-item">
                                        <div><?php echo h($doc['title']); ?></div>
                                        <div>
                                            <small><?php echo h($doc['file_name']); ?></small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>هیچ سندی برای این پروژه آپلود نشده است.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- تب تیم -->
                    <div class="tab-content <?php echo $activeTab === 'team' ? 'active' : ''; ?>" id="tab-team">
                        <h3>اعضای تیم پروژه</h3>
                        <a href="collaborations.php?project_id=<?php echo $projectId; ?>" class="btn btn-primary">
                            مدیریت تیم
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active from all links
            const links = document.querySelectorAll('.tab-link');
            links.forEach(link => link.classList.remove('active'));
            
            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>