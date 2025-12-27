<?php
/**
 * ماژول کاربران - مدیریت کاربران سیستم
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('admin', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$is_active = isset($_GET['is_active']) ? (int)$_GET['is_active'] : -1;
$is_admin = isset($_GET['is_admin']) ? (int)$_GET['is_admin'] : -1;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM logs WHERE user_id = u.id) as activity_count,
        (SELECT COUNT(DISTINCT module) FROM user_permissions up 
         JOIN permissions p ON p.id = up.permission_id 
         WHERE up.user_id = u.id) as permission_count
        FROM users u
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (u.username LIKE :search OR u.fullname LIKE :search OR u.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($is_active >= 0) {
    $sql .= " AND u.is_active = :is_active";
    $params[':is_active'] = $is_active;
}

if ($is_admin >= 0) {
    $sql .= " AND u.is_admin = :is_admin";
    $params[':is_admin'] = $is_admin;
}

$sql .= " ORDER BY u.is_admin DESC, u.created_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$users = $result['data'];
$totalPages = $result['total_pages'];

// آمار کلی
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admin_users,
        SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_active
    FROM users
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - <?php echo SITE_TITLE; ?></title>
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
        
        .stats-container {
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
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-icon.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.success { background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); }
        .stat-icon.warning { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); }
        .stat-icon.info { background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%); }
        
        .stat-content h3 {
            color: #666;
            font-size: 14px;
            font-weight: normal;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #2c3e50;
            font-size: 24px;
            font-weight: bold;
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
        
        .badge-active {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-inactive {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-admin {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-user {
            background: #e3f2fd;
            color: #1976d2;
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
        
        .btn-permissions {
            background: #ff9800;
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .user-details strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .user-details small {
            color: #666;
            font-size: 12px;
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
            <h1>👥 مدیریت کاربران</h1>
            <?php if (check_permission('admin', PERMISSION_WRITE)): ?>
                <a href="user.php?action=add" class="btn btn-primary">
                    ➕ کاربر جدید
                </a>
            <?php endif; ?>
        </div>
        
        <!-- آمار -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon primary">👥</div>
                <div class="stat-content">
                    <h3>کل کاربران</h3>
                    <p><?php echo en2fa($stats['total_users'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">✅</div>
                <div class="stat-content">
                    <h3>کاربران فعال</h3>
                    <p><?php echo en2fa($stats['active_users'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">🔑</div>
                <div class="stat-content">
                    <h3>مدیران</h3>
                    <p><?php echo en2fa($stats['admin_users'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">🕐</div>
                <div class="stat-content">
                    <h3>فعال در ۳۰ روز اخیر</h3>
                    <p><?php echo en2fa($stats['recent_active'] ?? 0); ?></p>
                </div>
            </div>
        </div>
        
        <!-- فیلترها -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="نام کاربری، نام، ایمیل..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="is_active">
                        <option value="-1">همه</option>
                        <option value="1" <?php echo $is_active === 1 ? 'selected' : ''; ?>>فعال</option>
                        <option value="0" <?php echo $is_active === 0 ? 'selected' : ''; ?>>غیرفعال</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>نوع کاربر</label>
                    <select name="is_admin">
                        <option value="-1">همه</option>
                        <option value="1" <?php echo $is_admin === 1 ? 'selected' : ''; ?>>مدیر</option>
                        <option value="0" <?php echo $is_admin === 0 ? 'selected' : ''; ?>>کاربر عادی</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <!-- لیست کاربران -->
        <div class="table-container">
            <?php if (count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>نام کاربری</th>
                            <th>نوع</th>
                            <th>وضعیت</th>
                            <th>مجوزها</th>
                            <th>آخرین ورود</th>
                            <th>فعالیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo mb_substr($user['fullname'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <div class="user-details">
                                            <strong><?php echo h($user['fullname']); ?></strong>
                                            <small><?php echo h($user['email'] ?: $user['mobile'] ?: '-'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo h($user['username']); ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge badge-admin">🔑 مدیر</span>
                                    <?php else: ?>
                                        <span class="badge badge-user">👤 کاربر</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge badge-active">✅ فعال</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">❌ غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo en2fa($user['permission_count']); ?> ماژول</td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <?php echo en2fa(date('Y/m/d H:i', strtotime($user['last_login']))); ?>
                                    <?php else: ?>
                                        <small style="color: #999;">هرگز</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo en2fa($user['activity_count']); ?> لاگ</td>
                                <td>
                                    <div class="actions">
                                        <a href="user.php?action=view&id=<?php echo $user['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁</a>
                                        
                                        <?php if (check_permission('admin', PERMISSION_WRITE)): ?>
                                            <a href="user.php?action=edit&id=<?php echo $user['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                            
                                            <a href="permissions.php?user_id=<?php echo $user['id']; ?>" 
                                               class="btn-sm btn-permissions" title="مجوزها">🔐</a>
                                        <?php endif; ?>
                                        
                                        <?php if (check_permission('admin', PERMISSION_FULL) && $user['id'] != $_SESSION['user_id']): ?>
                                            <a href="user.php?action=delete&id=<?php echo $user['id']; ?>" 
                                               class="btn-sm btn-delete" title="حذف"
                                               onclick="return confirm('آیا از حذف این کاربر اطمینان دارید؟')">🗑️</a>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&is_active=<?php echo $is_active; ?>&is_admin=<?php echo $is_admin; ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&is_active=<?php echo $is_active; ?>&is_admin=<?php echo $is_admin; ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&is_active=<?php echo $is_active; ?>&is_admin=<?php echo $is_admin; ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>هیچ کاربری یافت نشد.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>