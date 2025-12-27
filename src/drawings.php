<?php
/**
 * ماژول نقشه‌های فنی - لیست نقشه‌ها
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('engineering', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// ایجاد جدول نقشه‌ها اگر وجود ندارد
$createTableSQL = "CREATE TABLE IF NOT EXISTS drawings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drawing_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    product_id INT,
    part_id INT,
    project_id INT,
    drawing_type ENUM('assembly', 'detail', 'schematic', 'layout', 'fabrication', 'isometric', 'other') NOT NULL,
    version VARCHAR(20) DEFAULT 'A',
    revision INT DEFAULT 0,
    scale VARCHAR(20),
    sheet_size VARCHAR(10),
    drawn_by INT,
    checked_by INT,
    approved_by INT,
    issue_date DATE,
    revision_date DATE,
    status ENUM('draft', 'review', 'approved', 'released', 'obsolete') DEFAULT 'draft',
    file_path VARCHAR(500),
    file_size INT,
    file_type VARCHAR(50),
    notes TEXT,
    tags VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (drawn_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_number (drawing_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSQL);

$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$type = sanitize_input($_GET['type'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT d.*, 
        prod.code as product_code, prod.name as product_name,
        pt.part_number, pt.name as part_name,
        proj.code as project_code, proj.title as project_title,
        u1.fullname as drawn_by_name,
        u2.fullname as checked_by_name,
        u3.fullname as approved_by_name
        FROM drawings d
        LEFT JOIN products prod ON prod.id = d.product_id
        LEFT JOIN parts pt ON pt.id = d.part_id
        LEFT JOIN projects proj ON proj.id = d.project_id
        LEFT JOIN users u1 ON u1.id = d.drawn_by
        LEFT JOIN users u2 ON u2.id = d.checked_by
        LEFT JOIN users u3 ON u3.id = d.approved_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (d.drawing_number LIKE :search OR d.title LIKE :search OR d.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND d.status = :status";
    $params[':status'] = $status;
}

if ($type) {
    $sql .= " AND d.drawing_type = :type";
    $params[':type'] = $type;
}

if ($project_id > 0) {
    $sql .= " AND d.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

$sql .= " ORDER BY d.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$drawings = $result['data'];
$totalPages = $result['total_pages'];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نقشه‌های فنی - <?php echo SITE_TITLE; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, 'Iranian Sans', Arial, sans-serif; background: #f5f7fa; direction: rtl; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
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
        
        .header h1 { color: #2c3e50; font-size: 24px; }
        
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
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; color: #555; font-size: 14px; }
        .form-group input, .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        th { padding: 15px; text-align: right; font-weight: bold; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        tbody tr { transition: background 0.2s; }
        tbody tr:hover { background: #f8f9fa; }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-draft { background: #e0e0e0; color: #616161; }
        .badge-review { background: #fff3cd; color: #856404; }
        .badge-approved { background: #cce5ff; color: #004085; }
        .badge-released { background: #d4edda; color: #155724; }
        .badge-obsolete { background: #f8d7da; color: #721c24; }
        
        .actions { display: flex; gap: 8px; }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-view { background: #4caf50; color: white; }
        .btn-edit { background: #2196f3; color: white; }
        .btn-download { background: #ff9800; color: white; }
        .btn-delete { background: #f44336; color: white; }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
        }
        
        .page-link {
            padding: 8px 15px;
            border: 2px solid #667eea;
            border-radius: 6px;
            color: #667eea;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .page-link:hover, .page-link.active {
            background: #667eea;
            color: white;
        }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .filters form { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
            table { min-width: 1200px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 نقشه‌های فنی</h1>
            <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                <a href="drawing.php?action=add" class="btn btn-primary">
                    ➕ افزودن نقشه جدید
                </a>
            <?php endif; ?>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="شماره، عنوان..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>نوع نقشه</label>
                    <select name="type">
                        <option value="">همه</option>
                        <option value="assembly" <?php echo $type === 'assembly' ? 'selected' : ''; ?>>مونتاژ</option>
                        <option value="detail" <?php echo $type === 'detail' ? 'selected' : ''; ?>>جزئیات</option>
                        <option value="schematic" <?php echo $type === 'schematic' ? 'selected' : ''; ?>>شماتیک</option>
                        <option value="layout" <?php echo $type === 'layout' ? 'selected' : ''; ?>>چیدمان</option>
                        <option value="fabrication" <?php echo $type === 'fabrication' ? 'selected' : ''; ?>>ساخت</option>
                        <option value="isometric" <?php echo $type === 'isometric' ? 'selected' : ''; ?>>ایزومتریک</option>
                        <option value="other" <?php echo $type === 'other' ? 'selected' : ''; ?>>سایر</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="review" <?php echo $status === 'review' ? 'selected' : ''; ?>>بررسی</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="released" <?php echo $status === 'released' ? 'selected' : ''; ?>>منتشر شده</option>
                        <option value="obsolete" <?php echo $status === 'obsolete' ? 'selected' : ''; ?>>منسوخ</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($drawings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره نقشه</th>
                            <th>عنوان</th>
                            <th>نوع</th>
                            <th>محصول/قطعه</th>
                            <th>پروژه</th>
                            <th>نسخه</th>
                            <th>Rev</th>
                            <th>مقیاس</th>
                            <th>ترسیم</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drawings as $drawing): ?>
                            <tr>
                                <td><strong><?php echo h($drawing['drawing_number']); ?></strong></td>
                                <td><?php echo h($drawing['title']); ?></td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'assembly' => 'مونتاژ',
                                        'detail' => 'جزئیات',
                                        'schematic' => 'شماتیک',
                                        'layout' => 'چیدمان',
                                        'fabrication' => 'ساخت',
                                        'isometric' => 'ایزومتریک',
                                        'other' => 'سایر'
                                    ];
                                    echo $typeLabels[$drawing['drawing_type']] ?? $drawing['drawing_type'];
                                    ?>
                                </td>
                                <td>
                                    <?php if ($drawing['product_code']): ?>
                                        <small>محصول:</small> <?php echo h($drawing['product_code']); ?>
                                    <?php elseif ($drawing['part_number']): ?>
                                        <small>قطعه:</small> <?php echo h($drawing['part_number']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($drawing['project_code']): ?>
                                        <?php echo h($drawing['project_code']); ?><br>
                                        <small><?php echo h($drawing['project_title']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($drawing['version']); ?></td>
                                <td><?php echo en2fa($drawing['revision']); ?></td>
                                <td><?php echo h($drawing['scale'] ?: '-'); ?></td>
                                <td><?php echo h($drawing['drawn_by_name'] ?: '-'); ?></td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'draft' => 'پیش‌نویس',
                                        'review' => 'بررسی',
                                        'approved' => 'تایید شده',
                                        'released' => 'منتشر شده',
                                        'obsolete' => 'منسوخ'
                                    ];
                                    $statusClass = 'badge-' . $drawing['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$drawing['status']] ?? $drawing['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="drawing.php?action=view&id=<?php echo $drawing['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if ($drawing['file_path']): ?>
                                            <a href="<?php echo h($drawing['file_path']); ?>" 
                                               class="btn-sm btn-download" title="دانلود" download>⬇️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                                            <a href="drawing.php?action=edit&id=<?php echo $drawing['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('engineering', PERMISSION_FULL)): ?>
                                            <a href="drawing.php?action=delete&id=<?php echo $drawing['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این نقشه اطمینان دارید؟')">🗑️</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ نقشه‌ای یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>