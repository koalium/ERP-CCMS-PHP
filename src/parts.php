<?php
/**
 * ماژول قطعات - لیست قطعات
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('engineering', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$search = sanitize_input($_GET['search'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT p.*, c.name as supplier_name,
        (SELECT COUNT(*) FROM bom WHERE part_id = p.id) as bom_usage
        FROM parts p
        LEFT JOIN contacts c ON c.id = p.supplier_contact_id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (p.part_number LIKE :search OR p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($category) {
    $sql .= " AND p.category = :category";
    $params[':category'] = $category;
}

if ($status) {
    $sql .= " AND p.status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY p.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$parts = $result['data'];
$totalPages = $result['total_pages'];

$categories = db()->select("SELECT DISTINCT category FROM parts WHERE category IS NOT NULL AND category != '' ORDER BY category");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قطعات - <?php echo SITE_TITLE; ?></title>
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
        
        .badge-active { background: #d4edda; color: #155724; }
        .badge-obsolete { background: #f8d7da; color: #721c24; }
        .badge-discontinued { background: #fff3cd; color: #856404; }
        
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
            <h1>🔩 قطعات</h1>
            <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                <a href="part.php?action=add" class="btn btn-primary">
                    ➕ افزودن قطعه جدید
                </a>
            <?php endif; ?>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="شماره، نام، توضیحات..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>دسته‌بندی</label>
                    <select name="category">
                        <option value="">همه</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo h($cat['category']); ?>" 
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo h($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                        <option value="obsolete" <?php echo $status === 'obsolete' ? 'selected' : ''; ?>>منسوخ</option>
                        <option value="discontinued" <?php echo $status === 'discontinued' ? 'selected' : ''; ?>>متوقف شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($parts) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره قطعه</th>
                            <th>نام</th>
                            <th>دسته</th>
                            <th>واحد</th>
                            <th>وزن</th>
                            <th>جنس</th>
                            <th>تامین‌کننده</th>
                            <th>قیمت واحد</th>
                            <th>استفاده در BOM</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parts as $part): ?>
                            <tr>
                                <td><strong><?php echo h($part['part_number']); ?></strong></td>
                                <td><?php echo h($part['name']); ?></td>
                                <td><?php echo h($part['category'] ?: '-'); ?></td>
                                <td><?php echo h($part['unit'] ?: '-'); ?></td>
                                <td><?php echo $part['weight'] ? en2fa($part['weight']) . ' kg' : '-'; ?></td>
                                <td><?php echo h($part['material'] ?: '-'); ?></td>
                                <td><?php echo h($part['supplier_name'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($part['unit_price']): ?>
                                        <?php echo en2fa(number_format($part['unit_price'])); ?>
                                        <small><?php echo h($part['currency']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($part['bom_usage'] > 0): ?>
                                        <span class="badge badge-active"><?php echo en2fa($part['bom_usage']); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = ['active' => 'فعال', 'obsolete' => 'منسوخ', 'discontinued' => 'متوقف'];
                                    $statusClass = 'badge-' . $part['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$part['status']] ?? $part['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="part.php?action=view&id=<?php echo $part['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                                            <a href="part.php?action=edit&id=<?php echo $part['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('engineering', PERMISSION_FULL)): ?>
                                            <a href="part.php?action=delete&id=<?php echo $part['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این قطعه اطمینان دارید؟')">🗑️</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ قطعه‌ای یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>