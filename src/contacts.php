<?php
/**
 * ماژول مخاطبین - لیست مخاطبین
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('contacts', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$type = sanitize_input($_GET['type'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT c.*, u.fullname as creator_name,
        (SELECT GROUP_CONCAT(cd.value SEPARATOR ', ') 
         FROM contact_details cd 
         WHERE cd.contact_id = c.id AND cd.type = 'mobile' 
         LIMIT 3) as mobiles,
        (SELECT GROUP_CONCAT(cd.value SEPARATOR ', ') 
         FROM contact_details cd 
         WHERE cd.contact_id = c.id AND cd.type = 'email' 
         LIMIT 3) as emails
        FROM contacts c
        LEFT JOIN users u ON u.id = c.created_by
        WHERE c.is_active = 1";

$params = [];

if ($search) {
    $sql .= " AND (c.name LIKE :search OR c.company_name LIKE :search OR c.national_id LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($type) {
    $sql .= " AND c.type = :type";
    $params[':type'] = $type;
}

if ($category) {
    $sql .= " AND c.category = :category";
    $params[':category'] = $category;
}

$sql .= " ORDER BY c.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$contacts = $result['data'];
$totalPages = $result['total_pages'];

// دریافت دسته‌بندی‌ها برای فیلتر
$categories = db()->select(
    "SELECT DISTINCT category FROM contacts WHERE category IS NOT NULL AND category != '' ORDER BY category"
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مخاطبین - <?php echo SITE_TITLE; ?></title>
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
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            color: #555;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: right;
            font-weight: bold;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-person {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-company {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-organization {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-customer {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-vendor {
            background: #fce4ec;
            color: #c2185b;
        }
        
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
            transition: all 0.2s;
        }
        
        .btn-view {
            background: #4caf50;
            color: white;
        }
        
        .btn-edit {
            background: #2196f3;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
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
        
        .page-link:hover,
        .page-link.active {
            background: #667eea;
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 مخاطبین</h1>
            <?php if (check_permission('contacts', PERMISSION_WRITE)): ?>
                <a href="contact.php?action=add" class="btn btn-primary">
                    ➕ افزودن مخاطب جدید
                </a>
            <?php endif; ?>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="نام، شرکت، کد ملی..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>نوع</label>
                    <select name="type">
                        <option value="">همه</option>
                        <option value="person" <?php echo $type === 'person' ? 'selected' : ''; ?>>شخص</option>
                        <option value="company" <?php echo $type === 'company' ? 'selected' : ''; ?>>شرکت</option>
                        <option value="organization" <?php echo $type === 'organization' ? 'selected' : ''; ?>>سازمان</option>
                    </select>
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
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($contacts) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>نوع</th>
                            <th>موبایل</th>
                            <th>ایمیل</th>
                            <th>دسته</th>
                            <th>نقش</th>
                            <th>ایجادکننده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($contact['name']); ?></strong>
                                    <?php if ($contact['company_name']): ?>
                                        <br><small style="color: #999;"><?php echo h($contact['company_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeLabels = ['person' => 'شخص', 'company' => 'شرکت', 'organization' => 'سازمان'];
                                    $typeClass = 'badge-' . $contact['type'];
                                    ?>
                                    <span class="badge <?php echo $typeClass; ?>">
                                        <?php echo $typeLabels[$contact['type']] ?? $contact['type']; ?>
                                    </span>
                                </td>
                                <td><?php echo h($contact['mobiles'] ?: '-'); ?></td>
                                <td><?php echo h($contact['emails'] ?: '-'); ?></td>
                                <td><?php echo h($contact['category'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($contact['is_customer']): ?>
                                        <span class="badge badge-customer">مشتری</span>
                                    <?php endif; ?>
                                    <?php if ($contact['is_vendor']): ?>
                                        <span class="badge badge-vendor">تامین‌کننده</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($contact['creator_name'] ?: '-'); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="contact.php?action=view&id=<?php echo $contact['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('contacts', PERMISSION_WRITE)): ?>
                                            <a href="contact.php?action=edit&id=<?php echo $contact['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('contacts', PERMISSION_FULL)): ?>
                                            <a href="contact.php?action=delete&id=<?php echo $contact['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این مخاطب اطمینان دارید؟')">🗑️</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&category=<?php echo urlencode($category); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&category=<?php echo urlencode($category); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&category=<?php echo urlencode($category); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ مخاطبی یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>