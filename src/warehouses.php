<?php
/**
 * لیست انبارها
 * Warehouses List
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('warehouse', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// دریافت لیست انبارها با آمار
$warehouses = db()->select(
    "SELECT w.*, 
     u.fullname as manager_name,
     COUNT(DISTINCT wt.item_id) as items_count,
     COUNT(DISTINCT wu.user_id) as users_count
     FROM warehouses w
     LEFT JOIN users u ON u.id = w.manager_user_id
     LEFT JOIN warehouse_transactions wt ON wt.warehouse_id = w.id
     LEFT JOIN warehouse_users wu ON wu.warehouse_id = w.id
     GROUP BY w.id
     ORDER BY w.created_at DESC"
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>انبارها - <?php echo SITE_TITLE; ?></title>
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
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .warehouses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .warehouse-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .warehouse-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .warehouse-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .warehouse-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .warehouse-code {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .warehouse-body {
            padding: 20px;
        }
        
        .warehouse-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
        }
        
        .info-value {
            font-weight: bold;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .warehouse-location {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #666;
        }
        
        .warehouse-actions {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 13px;
            border-radius: 6px;
            flex: 1;
            text-align: center;
            text-decoration: none;
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
        
        .btn-users {
            background: #ff9800;
            color: white;
        }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-main { background: #667eea; color: white; }
        .badge-site { background: #11998e; color: white; }
        .badge-waste { background: #f093fb; color: white; }
        .badge-project { background: #4facfe; color: white; }
        .badge-electronic { background: #fa709a; color: white; }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .no-data p {
            font-size: 18px;
            color: #999;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .warehouses-grid {
                grid-template-columns: 1fr;
            }
            
            .warehouse-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 مدیریت انبارها</h1>
            <div style="display: flex; gap: 10px;">
                <a href="warehouse.php" class="btn btn-back">⬅ بازگشت</a>
                <?php if (check_permission('warehouse', PERMISSION_FULL)): ?>
                    <a href="warehouse_form.php?action=add" class="btn btn-primary">
                        ➕ انبار جدید
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (count($warehouses) > 0): ?>
            <div class="warehouses-grid">
                <?php foreach ($warehouses as $wh): ?>
                    <div class="warehouse-card">
                        <div class="warehouse-header">
                            <div class="warehouse-name">
                                <span><?php echo h($wh['name']); ?></span>
                                <span class="badge badge-<?php echo $wh['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $wh['is_active'] ? '✓ فعال' : '✗ غیرفعال'; ?>
                                </span>
                            </div>
                            <div class="warehouse-code">
                                کد: <?php echo h($wh['code']); ?>
                                <span class="badge badge-<?php echo $wh['type']; ?>" style="margin-right: 10px;">
                                    <?php 
                                    $types = [
                                        'main' => 'اصلی',
                                        'site' => 'پای کار',
                                        'waste' => 'زایعات',
                                        'project' => 'پروژه',
                                        'electronic' => 'الکترونیک'
                                    ];
                                    echo $types[$wh['type']] ?? $wh['type'];
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="warehouse-body">
                            <?php if ($wh['location']): ?>
                                <div class="warehouse-location">
                                    📍 <?php echo h($wh['location']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="warehouse-info">
                                <div class="info-item">
                                    <div class="info-label">تعداد اقلام</div>
                                    <div class="info-value">
                                        <?php echo en2fa($wh['items_count'] ?? 0); ?> قلم
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">تعداد کاربران</div>
                                    <div class="info-value">
                                        <?php echo en2fa($wh['users_count'] ?? 0); ?> نفر
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">مدیر انبار</div>
                                    <div class="info-value" style="font-size: 14px;">
                                        <?php echo h($wh['manager_name'] ?: '-'); ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">تاریخ ایجاد</div>
                                    <div class="info-value" style="font-size: 14px;">
                                        <?php echo en2fa(date('Y/m/d', strtotime($wh['created_at']))); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="warehouse-actions">
                                <a href="warehouse_inventory.php?warehouse_id=<?php echo $wh['id']; ?>" 
                                   class="btn-sm btn-view" title="موجودی">
                                    📊 موجودی
                                </a>
                                <?php if (check_permission('warehouse', PERMISSION_FULL)): ?>
                                    <a href="warehouse_form.php?action=edit&id=<?php echo $wh['id']; ?>" 
                                       class="btn-sm btn-edit" title="ویرایش">
                                        ✏️ ویرایش
                                    </a>
                                    <a href="warehouse_users.php?warehouse_id=<?php echo $wh['id']; ?>" 
                                       class="btn-sm btn-users" title="کاربران">
                                        👥 کاربران
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">
                <p>🏢 هیچ انباری ثبت نشده است</p>
                <?php if (check_permission('warehouse', PERMISSION_FULL)): ?>
                    <a href="warehouse_form.php?action=add" class="btn btn-primary">
                        ➕ اولین انبار را ایجاد کنید
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>