<?php
/**
 * مدیریت آیتم‌های پروژه (تجهیزات)
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
$itemId = (int)($_GET['id'] ?? 0);

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
$item = null;

// بارگذاری آیتم برای ویرایش
if ($itemId && ($action === 'edit' || $action === 'view')) {
    $item = db()->selectOne("SELECT * FROM project_items WHERE id = :id AND project_id = :pid", 
        [':id' => $itemId, ':pid' => $projectId]);
    if (!$item) {
        die('آیتم یافت نشد.');
    }
}

// حذف آیتم
if ($action === 'delete' && $itemId && check_permission('projects', PERMISSION_FULL)) {
    if (db()->delete('project_items', 'id = :id AND project_id = :pid', [':id' => $itemId, ':pid' => $projectId])) {
        redirect(SITE_URL . '/project_items.php?project_id=' . $projectId . '&deleted=1');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی.';
    } else {
        $templateId = (int)($_POST['template_id'] ?? 0);
        $specifications = [];
        
        // دریافت مشخصات
        if (isset($_POST['specs']) && is_array($_POST['specs'])) {
            foreach ($_POST['specs'] as $key => $value) {
                if (!empty($value)) {
                    $specifications[$key] = sanitize_input($value);
                }
            }
        }
        
        $data = [
            'project_id' => $projectId,
            'template_id' => $templateId ?: null,
            'item_code' => sanitize_input($_POST['item_code'] ?? ''),
            'item_name' => sanitize_input($_POST['item_name'] ?? ''),
            'item_type' => sanitize_input($_POST['item_type'] ?? ''),
            'quantity' => (int)($_POST['quantity'] ?? 1),
            'specifications' => json_encode($specifications, JSON_UNESCAPED_UNICODE),
            'status' => sanitize_input($_POST['status'] ?? 'planning'),
            'location' => sanitize_input($_POST['location'] ?? ''),
            'notes' => sanitize_input($_POST['notes'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ];
        
        // اعتبارسنجی
        if (empty($data['item_code'])) {
            $error = 'کد آیتم الزامی است.';
        } elseif (empty($data['item_name'])) {
            $error = 'نام آیتم الزامی است.';
        } else {
            if ($action === 'add') {
                $data['created_by'] = $_SESSION['user_id'];
                $newId = db()->insert('project_items', $data);
                
                if ($newId) {
                    $success = 'آیتم با موفقیت اضافه شد.';
                    $action = 'list';
                } else {
                    $error = 'خطا در افزودن آیتم.';
                }
            } else {
                $updated = db()->update('project_items', $data, 'id = :id', [':id' => $itemId]);
                
                if ($updated !== false) {
                    $success = 'آیتم با موفقیت به‌روزرسانی شد.';
                    $item = db()->selectOne("SELECT * FROM project_items WHERE id = :id", [':id' => $itemId]);
                } else {
                    $error = 'خطا در به‌روزرسانی آیتم.';
                }
            }
        }
    }
}

// بارگذاری لیست آیتم‌ها
$items = db()->select(
    "SELECT pi.*, u.fullname as creator_name 
     FROM project_items pi 
     LEFT JOIN users u ON u.id = pi.created_by 
     WHERE pi.project_id = :pid 
     ORDER BY pi.sort_order, pi.id",
    [':pid' => $projectId]
);

// بارگذاری قالب‌ها
$templates = db()->select("SELECT * FROM project_item_templates WHERE is_active = 1 ORDER BY item_type, template_name");

// گروه‌بندی قالب‌ها
$groupedTemplates = [];
foreach ($templates as $t) {
    $groupedTemplates[$t['item_type']][] = $t;
}

// آمار
$stats = db()->selectOne(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(quantity) as total_quantity
    FROM project_items WHERE project_id = :pid",
    [':pid' => $projectId]
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آیتم‌های پروژه - <?php echo h($project['title']); ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1400px;
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
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
        
        .form-container,
        .items-container {
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .template-selector {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .template-selector h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .template-btn {
            padding: 12px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            text-align: right;
            transition: all 0.3s;
            font-size: 13px;
        }
        
        .template-btn:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .template-btn.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        
        .specs-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .specs-container h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .spec-field {
            margin-bottom: 15px;
        }
        
        .spec-field label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-size: 13px;
        }
        
        .spec-field input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .items-table th {
            padding: 12px;
            text-align: right;
            font-size: 14px;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .items-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-planning { background: #fff3e0; color: #f57c00; }
        .badge-design { background: #e3f2fd; color: #1976d2; }
        .badge-procurement { background: #f3e5f5; color: #7b1fa2; }
        .badge-production { background: #e8f5e9; color: #388e3c; }
        .badge-testing { background: #ffe0b2; color: #e65100; }
        .badge-installed { background: #e0f2f1; color: #00796b; }
        .badge-completed { background: #c8e6c9; color: #2e7d32; }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit { background: #2196f3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .template-grid {
                grid-template-columns: 1fr;
            }
            
            .items-table {
                display: block;
                overflow-x: auto;
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
                <span>آیتم‌ها</span>
            </div>
            <div class="header-content">
                <h1>📦 آیتم‌های پروژه</h1>
                <?php if ($action === 'list' && check_permission('projects', PERMISSION_WRITE)): ?>
                    <a href="?project_id=<?php echo $projectId; ?>&action=add" class="btn btn-primary">
                        ➕ افزودن آیتم
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
            <!-- آمار -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?php echo en2fa($stats['total'] ?? 0); ?></div>
                    <div class="stat-label">کل آیتم‌ها</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo en2fa($stats['total_quantity'] ?? 0); ?></div>
                    <div class="stat-label">تعداد کل</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo en2fa($stats['planning'] ?? 0); ?></div>
                    <div class="stat-label">در برنامه‌ریزی</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo en2fa($stats['completed'] ?? 0); ?></div>
                    <div class="stat-label">تکمیل شده</div>
                </div>
            </div>
            
            <!-- لیست آیتم‌ها -->
            <div class="items-container">
                <?php if (count($items) > 0): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>ردیف</th>
                                <th>کد</th>
                                <th>نام آیتم</th>
                                <th>نوع</th>
                                <th>تعداد</th>
                                <th>وضعیت</th>
                                <th>محل</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $statusLabels = [
                                'planning' => 'برنامه‌ریزی',
                                'design' => 'طراحی',
                                'procurement' => 'تدارکات',
                                'production' => 'تولید',
                                'testing' => 'تست',
                                'installed' => 'نصب شده',
                                'completed' => 'تکمیل'
                            ];
                            
                            foreach ($items as $idx => $it): 
                            ?>
                                <tr>
                                    <td><?php echo en2fa($idx + 1); ?></td>
                                    <td><strong><?php echo h($it['item_code']); ?></strong></td>
                                    <td><?php echo h($it['item_name']); ?></td>
                                    <td><small style="color: #666;"><?php echo h($it['item_type']); ?></small></td>
                                    <td><?php echo en2fa($it['quantity']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $it['status']; ?>">
                                            <?php echo $statusLabels[$it['status']]; ?>
                                        </span>
                                    </td>
                                    <td><?php echo h($it['location'] ?: '-'); ?></td>
                                    <td>
                                        <div class="actions">
                                            <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                                                <a href="?project_id=<?php echo $projectId; ?>&action=edit&id=<?php echo $it['id']; ?>" 
                                                   class="btn-sm btn-edit">✏️</a>
                                            <?php endif; ?>
                                            <?php if (check_permission('projects', PERMISSION_FULL)): ?>
                                                <a href="?project_id=<?php echo $projectId; ?>&action=delete&id=<?php echo $it['id']; ?>" 
                                                   class="btn-sm btn-delete"
                                                   onclick="return confirm('آیا از حذف این آیتم اطمینان دارید؟')">🗑️</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px; color: #999;">
                        <p>هیچ آیتمی برای این پروژه تعریف نشده است.</p>
                        <?php if (check_permission('projects', PERMISSION_WRITE)): ?>
                            <a href="?project_id=<?php echo $projectId; ?>&action=add" class="btn btn-primary" style="margin-top: 20px;">
                                ➕ اولین آیتم را اضافه کنید
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- فرم افزودن/ویرایش -->
            <div class="form-container">
                <form method="POST" action="" id="itemForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php if ($action === 'add'): ?>
                        <!-- انتخاب قالب -->
                        <div class="template-selector">
                            <h3>📋 انتخاب از قالب‌های پیش‌فرض (اختیاری)</h3>
                            <div class="template-grid">
                                <?php foreach ($templates as $tpl): ?>
                                    <button type="button" class="template-btn" 
                                            onclick="selectTemplate(<?php echo htmlspecialchars(json_encode($tpl)); ?>)">
                                        <?php echo h($tpl['template_name']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="template_id" id="template_id" value="<?php echo $item['template_id'] ?? ''; ?>">
                    <input type="hidden" name="item_type" id="item_type" value="<?php echo $item['item_type'] ?? ''; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>کد آیتم <span>*</span></label>
                            <input type="text" name="item_code" id="item_code" required 
                                   value="<?php echo h($item['item_code'] ?? ''); ?>"
                                   placeholder="مثال: ST-01">
                        </div>
                        
                        <div class="form-group">
                            <label>نام آیتم <span>*</span></label>
                            <input type="text" name="item_name" id="item_name" required 
                                   value="<?php echo h($item['item_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>تعداد <span>*</span></label>
                            <input type="number" name="quantity" min="1" required 
                                   value="<?php echo h($item['quantity'] ?? 1); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>وضعیت <span>*</span></label>
                            <select name="status" required>
                                <option value="planning" <?php echo ($item['status'] ?? 'planning') === 'planning' ? 'selected' : ''; ?>>برنامه‌ریزی</option>
                                <option value="design" <?php echo ($item['status'] ?? '') === 'design' ? 'selected' : ''; ?>>طراحی</option>
                                <option value="procurement" <?php echo ($item['status'] ?? '') === 'procurement' ? 'selected' : ''; ?>>تدارکات</option>
                                <option value="production" <?php echo ($item['status'] ?? '') === 'production' ? 'selected' : ''; ?>>تولید</option>
                                <option value="testing" <?php echo ($item['status'] ?? '') === 'testing' ? 'selected' : ''; ?>>تست</option>
                                <option value="installed" <?php echo ($item['status'] ?? '') === 'installed' ? 'selected' : ''; ?>>نصب شده</option>
                                <option value="completed" <?php echo ($item['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>تکمیل</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>محل نصب</label>
                            <input type="text" name="location" 
                                   value="<?php echo h($item['location'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>اولویت نمایش</label>
                            <input type="number" name="sort_order" 
                                   value="<?php echo h($item['sort_order'] ?? 0); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>یادداشت</label>
                            <textarea name="notes"><?php echo h($item['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- مشخصات فنی -->
                    <div class="specs-container" id="specsContainer">
                        <h4>📝 مشخصات فنی</h4>
                        <div id="specsFields">
                            <?php
                            $currentSpecs = [];
                            if ($item && $item['specifications']) {
                                $currentSpecs = json_decode($item['specifications'], true) ?: [];
                            }
                            
                            if (!empty($currentSpecs)):
                                foreach ($currentSpecs as $key => $value):
                            ?>
                                <div class="spec-field">
                                    <label><?php echo h(ucfirst(str_replace('_', ' ', $key))); ?></label>
                                    <input type="text" name="specs[<?php echo h($key); ?>]" 
                                           value="<?php echo h($value); ?>">
                                </div>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                                <p style="color: #666; font-size: 13px;">
                                    برای افزودن مشخصات، از قالب استفاده کنید یا مستقیم وارد کنید.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="?project_id=<?php echo $projectId; ?>" class="btn btn-secondary">↩ بازگشت</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? '➕ افزودن آیتم' : '💾 ذخیره تغییرات'; ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function selectTemplate(template) {
            // پر کردن فیلدها
            document.getElementById('template_id').value = template.id;
            document.getElementById('item_type').value = template.item_type;
            document.getElementById('item_name').value = template.template_name;
            
            // نمایش مشخصات
            const specs = JSON.parse(template.default_specifications || '{}');
            const specsContainer = document.getElementById('specsFields');
            specsContainer.innerHTML = '';
            
            for (const [key, value] of Object.entries(specs)) {
                const div = document.createElement('div');
                div.className = 'spec-field';
                div.innerHTML = `
                    <label>${key.replace(/_/g, ' ')}</label>
                    <input type="text" name="specs[${key}]" value="${value}" placeholder="مقدار را وارد کنید">
                `;
                specsContainer.appendChild(div);
            }
            
            // هایلایت قالب انتخاب شده
            document.querySelectorAll('.template-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.classList.add('selected');
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>