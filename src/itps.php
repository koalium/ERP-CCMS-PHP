<?php
/**
 * ماژول ITP (Inspection and Test Plan) - لیست ITP
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('qc', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// ایجاد جدول ITP اگر وجود ندارد
$createTableSQL = "CREATE TABLE IF NOT EXISTS itp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    itp_number VARCHAR(50) UNIQUE NOT NULL,
    project_id INT,
    product_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    prepared_by INT NOT NULL,
    reviewed_by INT,
    approved_by INT,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
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
    INDEX idx_number (itp_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSQL);

// ایجاد جدول آیتم‌های ITP (Test Points)
$createItemsTableSQL = "CREATE TABLE IF NOT EXISTS itp_test_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    itp_id INT NOT NULL,
    point_number INT NOT NULL,
    test_description TEXT NOT NULL,
    acceptance_criteria TEXT,
    test_method VARCHAR(200),
    inspection_stage ENUM('raw_material', 'fabrication', 'assembly', 'final', 'witness') NOT NULL,
    hold_point TINYINT(1) DEFAULT 0,
    witness_point TINYINT(1) DEFAULT 0,
    applicable_standard VARCHAR(200),
    reference_document VARCHAR(200),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (itp_id) REFERENCES itp(id) ON DELETE CASCADE,
    INDEX idx_itp (itp_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createItemsTableSQL);

$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT i.*, 
        proj.code as project_code, proj.title as project_title,
        prod.code as product_code, prod.name as product_name,
        u.fullname as preparer_name,
        (SELECT COUNT(*) FROM itp_test_points WHERE itp_id = i.id) as test_points_count
        FROM itp i
        LEFT JOIN projects proj ON proj.id = i.project_id
        LEFT JOIN products prod ON prod.id = i.product_id
        LEFT JOIN users u ON u.id = i.prepared_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (i.itp_number LIKE :search OR i.title LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND i.status = :status";
    $params[':status'] = $status;
}

if ($project_id > 0) {
    $sql .= " AND i.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

$sql .= " ORDER BY i.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$itps = $result['data'];
$totalPages = $result['total_pages'];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITP - <?php echo SITE_TITLE; ?></title>
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
        .badge-active { background: #d4edda; color: #155724; }
        .badge-completed { background: #cce5ff; color: #004085; }
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
            <h1>✅ Inspection and Test Plan (ITP)</h1>
            <?php if (check_permission('qc', PERMISSION_WRITE)): ?>
                <a href="itp.php?action=add" class="btn btn-primary">
                    ➕ افزودن ITP جدید
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
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($itps) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره ITP</th>
                            <th>عنوان</th>
                            <th>پروژه</th>
                            <th>محصول</th>
                            <th>نسخه</th>
                            <th>تعداد نقاط آزمون</th>
                            <th>تهیه‌کننده</th>
                            <th>وضعیت</th>
                            <th>تاریخ صدور</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itps as $itp): ?>
                            <tr>
                                <td><strong><?php echo h($itp['itp_number']); ?></strong></td>
                                <td><?php echo h($itp['title']); ?></td>
                                <td>
                                    <?php if ($itp['project_code']): ?>
                                        <?php echo h($itp['project_code']); ?><br>
                                        <small><?php echo h($itp['project_title']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($itp['product_code']): ?>
                                        <?php echo h($itp['product_code']); ?><br>
                                        <small><?php echo h($itp['product_name']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($itp['version']); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-active"><?php echo en2fa($itp['test_points_count']); ?></span>
                                </td>
                                <td><?php echo h($itp['preparer_name']); ?></td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'draft' => 'پیش‌نویس',
                                        'active' => 'فعال',
                                        'completed' => 'تکمیل شده',
                                        'cancelled' => 'لغو شده'
                                    ];
                                    $statusClass = 'badge-' . $itp['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$itp['status']] ?? $itp['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $itp['issue_date'] ? en2fa($itp['issue_date']) : '-'; ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="itp.php?action=view&id=<?php echo $itp['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('qc', PERMISSION_WRITE)): ?>
                                            <a href="itp.php?action=edit&id=<?php echo $itp['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('qc', PERMISSION_FULL)): ?>
                                            <a href="itp.php?action=delete&id=<?php echo $itp['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این ITP اطمینان دارید؟')">🗑️</a>
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
                    <p>هیچ ITP یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>