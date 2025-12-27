<?php
/**
 * ماژول BOM (Bill of Materials) - لیست BOM
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('engineering', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$product_id = (int)($_GET['product_id'] ?? 0);
$search = sanitize_input($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT b.*, 
        p.code as product_code, p.name as product_name,
        pt.part_number, pt.name as part_name, pt.unit as part_unit,
        pt.unit_price, pt.currency
        FROM bom b
        JOIN products p ON p.id = b.product_id
        JOIN parts pt ON pt.id = b.part_id
        WHERE b.is_active = 1";

$params = [];

if ($product_id > 0) {
    $sql .= " AND b.product_id = :product_id";
    $params[':product_id'] = $product_id;
}

if ($search) {
    $sql .= " AND (p.name LIKE :search OR pt.part_number LIKE :search OR pt.name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY p.code, pt.part_number";

$result = db()->paginate($sql, $params, $page, $perPage);
$boms = $result['data'];
$totalPages = $result['total_pages'];

// اگر محصول خاصی انتخاب شده، اطلاعات آن را بگیر
$selectedProduct = null;
if ($product_id > 0) {
    $selectedProduct = db()->selectOne("SELECT * FROM products WHERE id = :id", [':id' => $product_id]);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOM - <?php echo SITE_TITLE; ?></title>
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
        
        .product-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-right: 4px solid #2196f3;
        }
        
        .product-info h3 { color: #1976d2; margin-bottom: 5px; }
        
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
        .form-group input {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        .form-group input:focus { outline: none; border-color: #667eea; }
        
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
            <h1>📋 Bill of Materials (BOM)</h1>
            <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                <a href="bom.php?action=add<?php echo $product_id > 0 ? '&product_id=' . $product_id : ''; ?>" class="btn btn-primary">
                    ➕ افزودن BOM جدید
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($selectedProduct): ?>
            <div class="product-info">
                <h3>محصول: <?php echo h($selectedProduct['code'] . ' - ' . $selectedProduct['name']); ?></h3>
                <p>نسخه: <?php echo h($selectedProduct['version']); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="filters">
            <form method="GET" action="">
                <?php if ($product_id > 0): ?>
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="محصول، قطعه..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($boms) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>محصول</th>
                            <th>شماره قطعه</th>
                            <th>نام قطعه</th>
                            <th>تعداد</th>
                            <th>واحد</th>
                            <th>Reference</th>
                            <th>قیمت واحد</th>
                            <th>قیمت کل</th>
                            <th>نسخه</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($boms as $bom): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($bom['product_code']); ?></strong><br>
                                    <small><?php echo h($bom['product_name']); ?></small>
                                </td>
                                <td><strong><?php echo h($bom['part_number']); ?></strong></td>
                                <td><?php echo h($bom['part_name']); ?></td>
                                <td style="text-align: center;"><?php echo en2fa($bom['quantity']); ?></td>
                                <td><?php echo h($bom['part_unit']); ?></td>
                                <td><?php echo h($bom['reference_designator'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($bom['unit_price']): ?>
                                        <?php echo en2fa(number_format($bom['unit_price'])); ?>
                                        <small><?php echo h($bom['currency']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($bom['unit_price']): ?>
                                        <?php echo en2fa(number_format($bom['unit_price'] * $bom['quantity'])); ?>
                                        <small><?php echo h($bom['currency']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($bom['version'] ?: '-'); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="bom.php?action=view&id=<?php echo $bom['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('engineering', PERMISSION_WRITE)): ?>
                                            <a href="bom.php?action=edit&id=<?php echo $bom['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('engineering', PERMISSION_FULL)): ?>
                                            <a href="bom.php?action=delete&id=<?php echo $bom['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این آیتم BOM اطمینان دارید؟')">🗑️</a>
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
                            <a href="?page=<?php echo $page - 1; ?><?php echo $product_id > 0 ? '&product_id=' . $product_id : ''; ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $product_id > 0 ? '&product_id=' . $product_id : ''; ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $product_id > 0 ? '&product_id=' . $product_id : ''; ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ آیتم BOM یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>