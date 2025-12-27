<?php
/**
 * لیست قراردادها
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('contracts', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پیام‌ها
$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = show_message('قرارداد با موفقیت افزوده شد.', 'success');
            break;
        case 'deleted':
            $message = show_message('قرارداد با موفقیت حذف شد.', 'success');
            break;
    }
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$type = sanitize_input($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT c.*,
        cnt.name as party_name,
        p.title as project_title,
        p.code as project_code,
        u.fullname as creator_name
        FROM contracts c
        LEFT JOIN contacts cnt ON cnt.id = c.party_contact_id
        LEFT JOIN projects p ON p.id = c.project_id
        LEFT JOIN users u ON u.id = c.created_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (c.contract_number LIKE :search OR c.title LIKE :search OR cnt.name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $status;
}

if ($type) {
    $sql .= " AND c.type LIKE :type";
    $params[':type'] = '%' . $type . '%';
}

$sql .= " ORDER BY c.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$contracts = $result['data'];
$totalPages = $result['total_pages'];

// آمار
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(amount) as total_amount
    FROM contracts
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قراردادها - <?php echo SITE_TITLE; ?></title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            font-size: 36px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        .stat-icon.total { background: #e3f2fd; }
        .stat-icon.active { background: #e8f5e9; }
        .stat-icon.pending { background: #fff3e0; }
        .stat-icon.draft { background: #f3e5f5; }
        
        .stat-content h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #666;
            font-size: 14px;
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
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-draft { background: #e0e0e0; color: #666; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-completed { background: #cce5ff; color: #004085; }
        .badge-terminated { background: #f8d7da; color: #721c24; }
        
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
        
        .amount {
            font-weight: bold;
            color: #2c3e50;
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
                min-width: 1000px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 قراردادها</h1>
            <?php if (check_permission('contracts', PERMISSION_WRITE)): ?>
                <a href="contract.php?action=add" class="btn btn-primary">
                    ➕ افزودن قرارداد جدید
                </a>
            <?php endif; ?>
        </div>
        
        <?php echo $message; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">📊</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['total'] ?? 0); ?></h3>
                    <p>کل قراردادها</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon active">✅</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['active'] ?? 0); ?></h3>
                    <p>فعال</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">⏳</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['pending'] ?? 0); ?></h3>
                    <p>در انتظار</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon draft">📝</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['draft'] ?? 0); ?></h3>
                    <p>پیش‌نویس</p>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="شماره، عنوان، طرف قرارداد..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>فعال</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                        <option value="terminated" <?php echo $status === 'terminated' ? 'selected' : ''; ?>>فسخ شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>نوع</label>
                    <input type="text" name="type" placeholder="خرید، فروش، مشاوره..." 
                           value="<?php echo h($type); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($contracts) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>عنوان</th>
                            <th>نوع</th>
                            <th>طرف قرارداد</th>
                            <th>پروژه</th>
                            <th>مبلغ</th>
                            <th>تاریخ شروع</th>
                            <th>تاریخ پایان</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                            <tr>
                                <td><strong><?php echo h($contract['contract_number']); ?></strong></td>
                                <td><?php echo h($contract['title']); ?></td>
                                <td><?php echo h($contract['type'] ?: '-'); ?></td>
                                <td><?php echo h($contract['party_name'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($contract['project_code']): ?>
                                        <?php echo h($contract['project_code']); ?><br>
                                        <small style="color: #999;"><?php echo h($contract['project_title']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($contract['amount']): ?>
                                        <span class="amount">
                                            <?php echo en2fa(number_format($contract['amount'], 0)); ?>
                                            <?php echo h($contract['currency']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $contract['start_date'] ? en2fa($contract['start_date']) : '-'; ?></td>
                                <td><?php echo $contract['end_date'] ? en2fa($contract['end_date']) : '-'; ?></td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'draft' => 'پیش‌نویس',
                                        'pending' => 'در انتظار',
                                        'active' => 'فعال',
                                        'completed' => 'تکمیل شده',
                                        'terminated' => 'فسخ شده'
                                    ];
                                    $statusClass = 'badge-' . $contract['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$contract['status']] ?? $contract['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="contract.php?action=view&id=<?php echo $contract['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        <?php if (check_permission('contracts', PERMISSION_WRITE)): ?>
                                            <a href="contract.php?action=edit&id=<?php echo $contract['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
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
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ قراردادی یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>