<?php
/**
 * داشبورد تولید
 * Production Dashboard
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('production', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// دریافت آمار
$stats = [];

// تعداد دستورات کار فعال
$stats['active_work_orders'] = db()->count('work_orders', 'status IN ("pending", "in_progress")');

// دستورات کار امروز
$today = date('Y-m-d');
$stats['today_work_orders'] = db()->count('work_orders', 'DATE(start_date) = :date', [':date' => $today]);

// گزارش‌های کار در انتظار تایید
$stats['pending_reports'] = db()->count('work_reports', 'status = "pending"');

// درخواست‌های متریال در انتظار
$stats['pending_materials'] = db()->count('material_requests', 'status = "pending"');

// NCRهای باز
$stats['open_ncrs'] = db()->count('qc_forms', "type = 'ncr' AND status != 'completed'");

// آخرین دستورات کار
$recentWorkOrders = db()->select(
    "SELECT wo.*, p.title as project_title, pr.name as product_name, u.fullname as assigned_name
     FROM work_orders wo
     LEFT JOIN projects p ON p.id = wo.project_id
     LEFT JOIN products pr ON pr.id = wo.product_id
     LEFT JOIN users u ON u.id = wo.assigned_to
     ORDER BY wo.created_at DESC
     LIMIT 10"
);

// دستورات کار با اولویت بالا
$urgentWorkOrders = db()->select(
    "SELECT wo.*, p.title as project_title, pr.name as product_name
     FROM work_orders wo
     LEFT JOIN projects p ON p.id = wo.project_id
     LEFT JOIN products pr ON pr.id = wo.product_id
     WHERE wo.priority = 'urgent' AND wo.status != 'completed'
     ORDER BY wo.due_date ASC
     LIMIT 5"
);

// آمار پیشرفت تولید امروز
$todayProgress = db()->select(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
     FROM work_orders
     WHERE DATE(start_date) = :date",
    [':date' => $today]
);

$progress = $todayProgress[0] ?? ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'pending' => 0];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد تولید - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
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
        
        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.red { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .stat-content h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: normal;
        }
        
        .stat-content .number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .quick-actions h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .btn-action {
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: bold;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .btn-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
            font-size: 18px;
        }
        
        .work-order-item {
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .work-order-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .work-order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .work-order-title {
            font-weight: bold;
            color: #2c3e50;
            font-size: 15px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-in-progress { background: #cce5ff; color: #004085; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .badge-urgent { background: #f8d7da; color: #721c24; }
        .badge-high { background: #fff3cd; color: #856404; }
        .badge-medium { background: #d1ecf1; color: #0c5460; }
        .badge-low { background: #d4edda; color: #155724; }
        
        .work-order-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }
        
        .work-order-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            transition: width 0.5s;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏭 داشبورد تولید</h1>
            <p>مدیریت دستورات کار، گزارش‌ها و درخواست‌های متریال</p>
        </div>
        
        <!-- آمار کلیدی -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📋</div>
                <div class="stat-content">
                    <h3>دستورات کار فعال</h3>
                    <div class="number"><?php echo en2fa($stats['active_work_orders']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">📅</div>
                <div class="stat-content">
                    <h3>دستورات کار امروز</h3>
                    <div class="number"><?php echo en2fa($stats['today_work_orders']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">📊</div>
                <div class="stat-content">
                    <h3>گزارش‌های در انتظار</h3>
                    <div class="number"><?php echo en2fa($stats['pending_reports']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">📦</div>
                <div class="stat-content">
                    <h3>درخواست متریال</h3>
                    <div class="number"><?php echo en2fa($stats['pending_materials']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">⚠️</div>
                <div class="stat-content">
                    <h3>NCR های باز</h3>
                    <div class="number"><?php echo en2fa($stats['open_ncrs']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- عملیات سریع -->
        <?php if (check_permission('production', PERMISSION_WRITE)): ?>
            <div class="quick-actions">
                <h2>⚡ عملیات سریع</h2>
                <div class="action-buttons">
                    <a href="work_order.php?action=add" class="btn-action btn-primary">
                        ➕ دستور کار جدید
                    </a>
                    <a href="work_report.php?action=add" class="btn-action btn-success">
                        📝 ثبت گزارش کار
                    </a>
                    <a href="material_request.php?action=add" class="btn-action btn-warning">
                        📦 درخواست متریال
                    </a>
                    <a href="ncr.php?action=add" class="btn-action btn-info">
                        ⚠️ ثبت NCR
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- پیشرفت تولید امروز -->
        <?php if ($progress['total'] > 0): ?>
            <div class="section" style="margin-bottom: 30px;">
                <h2>📊 پیشرفت تولید امروز</h2>
                <div class="progress-bar">
                    <?php 
                    $percentage = round(($progress['completed'] / $progress['total']) * 100);
                    ?>
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%">
                        <?php echo en2fa($percentage); ?>٪ تکمیل شده
                    </div>
                </div>
                <div style="display: flex; gap: 20px; justify-content: center; margin-top: 15px;">
                    <span>✅ تکمیل: <strong><?php echo en2fa($progress['completed']); ?></strong></span>
                    <span>⏳ در حال انجام: <strong><?php echo en2fa($progress['in_progress']); ?></strong></span>
                    <span>⏸️ در انتظار: <strong><?php echo en2fa($progress['pending']); ?></strong></span>
                    <span>📋 کل: <strong><?php echo en2fa($progress['total']); ?></strong></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- محتوای اصلی -->
        <div class="content-grid">
            <!-- آخرین دستورات کار -->
            <div class="section">
                <h2>📋 آخرین دستورات کار</h2>
                <?php if (count($recentWorkOrders) > 0): ?>
                    <?php foreach ($recentWorkOrders as $wo): ?>
                        <div class="work-order-item">
                            <div class="work-order-header">
                                <div>
                                    <div class="work-order-title">
                                        <?php echo h($wo['work_order_number']); ?> - 
                                        <?php echo h($wo['title']); ?>
                                    </div>
                                    <?php if ($wo['project_title']): ?>
                                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                            پروژه: <?php echo h($wo['project_title']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php
                                    $statusLabels = [
                                        'pending' => 'در انتظار',
                                        'in_progress' => 'در حال انجام',
                                        'completed' => 'تکمیل شده',
                                        'cancelled' => 'لغو شده'
                                    ];
                                    $statusClass = 'badge-' . $wo['status'];
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$wo['status']] ?? $wo['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="work-order-meta">
                                <?php if ($wo['product_name']): ?>
                                    <span>📦 <?php echo h($wo['product_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($wo['quantity']): ?>
                                    <span>🔢 تعداد: <?php echo en2fa($wo['quantity']); ?></span>
                                <?php endif; ?>
                                <?php if ($wo['assigned_name']): ?>
                                    <span>👤 <?php echo h($wo['assigned_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($wo['due_date']): ?>
                                    <span>📅 سررسید: <?php echo en2fa($wo['due_date']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="work_orders.php" style="color: #667eea; text-decoration: none; font-weight: bold;">
                            مشاهده همه دستورات کار ←
                        </a>
                    </div>
                <?php else: ?>
                    <div class="no-data">هیچ دستور کاری یافت نشد</div>
                <?php endif; ?>
            </div>
            
            <!-- دستورات کار فوری -->
            <div class="section">
                <h2>🚨 دستورات کار فوری</h2>
                <?php if (count($urgentWorkOrders) > 0): ?>
                    <?php foreach ($urgentWorkOrders as $wo): ?>
                        <div class="work-order-item">
                            <div class="work-order-header">
                                <div class="work-order-title">
                                    <?php echo h($wo['work_order_number']); ?>
                                </div>
                                <span class="badge badge-urgent">فوری</span>
                            </div>
                            <div style="font-size: 13px; color: #666; margin-top: 8px;">
                                <?php echo h($wo['title']); ?>
                            </div>
                            <?php if ($wo['due_date']): ?>
                                <div style="font-size: 12px; color: #c33; margin-top: 8px; font-weight: bold;">
                                    ⏰ سررسید: <?php echo en2fa($wo['due_date']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data" style="padding: 20px;">
                        ✅ دستور کار فوری وجود ندارد
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>