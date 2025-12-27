<?php
/**
 * لیست پیشنهادات
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('marketing', PERMISSION_READ)) {
    die('شما مجوز دسترسی ندارید.');
}

$tenderId = $_GET['tender_id'] ?? null;
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');

$sql = "SELECT p.*, t.title as tender_title, t.tender_number,
        u1.fullname as prepared_by_name,
        u2.fullname as approved_by_name
        FROM proposals p
        LEFT JOIN tenders t ON t.id = p.tender_id
        LEFT JOIN users u1 ON u1.id = p.prepared_by
        LEFT JOIN users u2 ON u2.id = p.approved_by
        WHERE 1=1";
$params = [];

if ($tenderId) {
    $sql .= " AND p.tender_id = :tender_id";
    $params[':tender_id'] = $tenderId;
}

if ($type) {
    $sql .= " AND p.type = :type";
    $params[':type'] = $type;
}

if ($status) {
    $sql .= " AND p.status = :status";
    $params[':status'] = $status;
}

if ($search) {
    $sql .= " AND (p.title LIKE :search OR p.proposal_number LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY p.created_at DESC";

$proposals = db()->select($sql, $params);

// دریافت اطلاعات مناقصه اگر فیلتر شده
$tender = $tenderId ? db()->selectOne("SELECT * FROM tenders WHERE id = :id", [':id' => $tenderId]) : null;
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیشنهادات - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            font-weight: bold;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
        }
        
        .tender-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .tender-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .filters {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 12px 24px;
            background: #f5f5f5;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
        }
        
        .proposals-grid {
            display: grid;
            gap: 25px;
        }
        
        .proposal-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-right: 6px solid;
        }
        
        .proposal-card.technical {
            border-right-color: #2196f3;
        }
        
        .proposal-card.financial {
            border-right-color: #4caf50;
        }
        
        .proposal-card.combined {
            border-right-color: #ff9800;
        }
        
        .proposal-card.final {
            border-right-color: #9c27b0;
        }
        
        .proposal-card:hover {
            transform: translateX(-8px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
        }
        
        .proposal-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }
        
        .proposal-number {
            color: #999;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .proposal-title {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: bold;
        }
        
        .proposal-tender {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .proposal-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        
        .badge-type {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-status {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-price {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .proposal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-sm {
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
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
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        
        .no-data {
            background: white;
            padding: 80px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
            color: #999;
        }
        
        .no-data-icon {
            font-size: 80px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <h1>📝 پیشنهادات</h1>
                <div class="btn-group">
                    <?php if ($tender): ?>
                        <a href="tenders.php" class="btn btn-secondary">↩️ بازگشت</a>
                    <?php endif; ?>
                    <?php if (check_permission('marketing', PERMISSION_WRITE)): ?>
                        <a href="proposal.php?action=add<?php echo $tenderId ? '&tender_id=' . $tenderId : ''; ?>" 
                           class="btn btn-primary">
                            ➕ پیشنهاد جدید
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($tender): ?>
                <div class="tender-info">
                    <h3>📋 مناقصه: <?php echo h($tender['title']); ?></h3>
                    <p>شماره: <?php echo h($tender['tender_number']); ?> | 
                       کارفرما: <?php echo h($tender['client'] ?: '-'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="filters">
            <div class="filter-tabs">
                <a href="?<?php echo $tenderId ? 'tender_id=' . $tenderId : ''; ?>" 
                   class="filter-tab <?php echo !$type && !$status ? 'active' : ''; ?>">همه</a>
                <a href="?type=technical<?php echo $tenderId ? '&tender_id=' . $tenderId : ''; ?>" 
                   class="filter-tab <?php echo $type === 'technical' ? 'active' : ''; ?>">فنی</a>
                <a href="?type=financial<?php echo $tenderId ? '&tender_id=' . $tenderId : ''; ?>" 
                   class="filter-tab <?php echo $type === 'financial' ? 'active' : ''; ?>">مالی</a>
                <a href="?type=combined<?php echo $tenderId ? '&tender_id=' . $tenderId : ''; ?>" 
                   class="filter-tab <?php echo $type === 'combined' ? 'active' : ''; ?>">ترکیبی</a>
                <a href="?type=final<?php echo $tenderId ? '&tender_id=' . $tenderId : ''; ?>" 
                   class="filter-tab <?php echo $type === 'final' ? 'active' : ''; ?>">نهایی</a>
            </div>
        </div>
        
        <div class="proposals-grid">
            <?php if (count($proposals) > 0): ?>
                <?php foreach ($proposals as $proposal): ?>
                    <div class="proposal-card <?php echo $proposal['type']; ?>">
                        <div class="proposal-header">
                            <div>
                                <div class="proposal-number">شماره: <?php echo h($proposal['proposal_number']); ?></div>
                                <h3 class="proposal-title"><?php echo h($proposal['title']); ?></h3>
                                <?php if (!$tenderId): ?>
                                    <div class="proposal-tender">
                                        مناقصه: <?php echo h($proposal['tender_title']); ?> 
                                        (<?php echo h($proposal['tender_number']); ?>)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="proposal-meta">
                            <span class="badge badge-type">
                                <?php 
                                $types = [
                                    'technical' => '🔧 فنی',
                                    'financial' => '💰 مالی',
                                    'combined' => '📋 ترکیبی',
                                    'final' => '✅ نهایی'
                                ];
                                echo $types[$proposal['type']] ?? $proposal['type'];
                                ?>
                            </span>
                            
                            <span class="badge badge-status">
                                <?php 
                                $statuses = [
                                    'draft' => '📝 پیش‌نویس',
                                    'review' => '👀 در حال بررسی',
                                    'submitted' => '📤 ارسال شده',
                                    'accepted' => '✅ پذیرفته شده',
                                    'rejected' => '❌ رد شده'
                                ];
                                echo $statuses[$proposal['status']] ?? $proposal['status'];
                                ?>
                            </span>
                            
                            <?php if ($proposal['total_price']): ?>
                                <span class="badge badge-price">
                                    💰 <?php echo number_format($proposal['total_price']); ?> 
                                    <?php echo h($proposal['currency']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="color: #666; font-size: 14px; margin-top: 10px;">
                            تهیه‌کننده: <?php echo h($proposal['prepared_by_name']); ?> |
                            تاریخ: <?php echo date('Y/m/d', strtotime($proposal['created_at'])); ?>
                        </div>
                        
                        <div class="proposal-actions">
                            <a href="proposal.php?action=view&id=<?php echo $proposal['id']; ?>" 
                               class="btn-sm btn-view">👁 مشاهده</a>
                            
                            <?php if (check_permission('marketing', PERMISSION_WRITE)): ?>
                                <a href="proposal.php?action=edit&id=<?php echo $proposal['id']; ?>" 
                                   class="btn-sm btn-edit">✏️ ویرایش</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">📝</div>
                    <h3>هیچ پیشنهادی یافت نشد</h3>
                    <p>برای افزودن پیشنهاد جدید از دکمه بالا استفاده کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>