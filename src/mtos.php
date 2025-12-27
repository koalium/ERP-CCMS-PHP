<?php
/**
 * ماژول MTO (Material Take-Off) - لیست MTO
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('engineering', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// ایجاد جدول MTO اگر وجود ندارد
$createTableSQL = "CREATE TABLE IF NOT EXISTS mto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mto_number VARCHAR(50) UNIQUE NOT NULL,
    project_id INT,
    product_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    prepared_by INT NOT NULL,
    reviewed_by INT,
    approved_by INT,
    status ENUM('draft', 'in_progress', 'review', 'approved', 'cancelled') DEFAULT 'draft',
    version VARCHAR(20) DEFAULT '1.0',
    issue_date DATE,
    revision_date DATE,
    notes TEXT,
    attachments TEXT COMMENT 'JSON array',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_number (mto_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSQL);

// ایجاد جدول آیتم‌های MTO
$createItemsTableSQL = "CREATE TABLE IF NOT EXISTS mto_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mto_id INT NOT NULL,
    item_number INT NOT NULL,
    description TEXT NOT NULL,
    specification TEXT,
    quantity DECIMAL(15, 3) NOT NULL,
    unit VARCHAR(20),
    material VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mto_id) REFERENCES mto(id) ON DELETE CASCADE,
    INDEX idx_mto (mto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createItemsTableSQL);

$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT m.*, 
        proj.code as project_code, proj.title as project_title,
        prod.code as product_code, prod.name as product_name,
        u.fullname as preparer_name,
        (SELECT COUNT(*) FROM mto_items WHERE mto_id = m.id) as items_count
        FROM mto m
        LEFT JOIN projects proj ON proj.id = m.project_id
        LEFT JOIN products prod ON prod.id = m.product_id
        LEFT JOIN users u ON u.id = m.prepared_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (m.mto_number LIKE :search OR m.title LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND m.status = :status";
    $params[':status'] = $status;
}

if ($project_id > 0) {
    $sql .= " AND m.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

$sql .= " ORDER BY m.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$mtos = $result['data'];
$totalPages = $result['total_pages'];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTO - <?php echo SITE_TITLE; ?></title>
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
        .badge-in_progress { background: #fff3cd; color: #856404; }
        .badge-review { background: #cce5ff; color: #004085; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
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
            table { min-width: 1000px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📐 Material Take-Off (MTO)</h1>
            <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                <a href="mto.php?action=add" class="btn btn-primary">
                    ➕ افزودن MTO جدید
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
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                        <option value="review" <?php echo $status === 'review' ? 'selected' : ''; ?>>بررسی</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($mtos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره MTO</th>
                            <th>عنوان</th>
                            <th>پروژه</th>
                            <th>محصول</th>
                            <th>نسخه</th>
                            <th>تعداد آیتم</th>
                            <th>تهیه‌کننده</th>
                            <th>وضعیت</th>
                            <th>تاریخ صدور</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mtos as $mto): ?>
                            <tr>
                                <td><strong><?php echo h($mto['mto_number']); ?></strong></td>
                                <td><?php echo h($mto['title']); ?></td>
                                <td>
                                    <?php if ($mto['project_code']): ?>
                                        <?php echo h($mto['project_code']); ?><br>
                                        <small><?php echo h($mto['project_title']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mto['product_code']): ?>
                                        <?php echo h($mto['product_code']); ?><br>
                                        <small><?php echo h($mto['product_name']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($mto['version']); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-approved"><?php echo en2fa($mto['items_count']); ?></span>
                                </td>
                                <td><?php echo h($mto['preparer_name']); ?></td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'draft' => 'پیش‌نویس',
                                        'in_progress' => 'در حال انجام',
                                        'review' => 'بررسی',
                                        'approved' => 'تایید شده',
                                        'cancelled' => 'لغو شده'
                                    ];
                                    $statusClass = 'badge-' . $mto['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$mto['status']] ?? $mto['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($mto['issue_date']) {
                                        list($y, $m, $d) = explode('-', $mto['issue_date']);
                                        require_once 'jalali-converter.php';
                                        list($jy, $jm, $jd) = gregorian_to_jalali($y, $m, $d);
                                        echo en2fa("$jy/$jm/$jd");
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="mto.php?action=view&id=<?php echo $mto['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                                            <a href="mto.php?action=edit&id=<?php echo $mto['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('engineering', PERMISSION_FULL)): ?>
                                            <a href="mto.php?action=delete&id=<?php echo $mto['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این MTO اطمینان دارید؟')">🗑️</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ MTO یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>