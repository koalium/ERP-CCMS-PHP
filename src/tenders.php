<?php
/**
 * لیست مناقصات
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('marketing', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// فیلترها
$status = $_GET['status'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$sql = "SELECT t.*, u.fullname as creator_name,
        (SELECT COUNT(*) FROM proposals WHERE tender_id = t.id) as proposals_count
        FROM tenders t
        LEFT JOIN users u ON u.id = t.created_by
        WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $status;
}

if ($search) {
    $sql .= " AND (t.title LIKE :search OR t.tender_number LIKE :search OR t.client LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY t.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$tenders = $result['data'];
$totalPages = $result['total_pages'];

// آمار
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'identified' THEN 1 ELSE 0 END) as identified,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
        SUM(CASE WHEN status = 'proposal_sent' THEN 1 ELSE 0 END) as proposal_sent,
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won,
        SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost
    FROM tenders
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مناقصات - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 52%, #2BFF88 90%);
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
            margin-bottom: 25px;
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
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(250, 139, 255, 0.5);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .stat-card {
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            color: white;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.identified {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.reviewing {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card.proposal_sent {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .stat-card.won {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.lost {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.95;
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
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 100%);
            color: white;
            font-weight: bold;
        }
        
        .search-box {
            display: flex;
            gap: 12px;
        }
        
        .search-box input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .tenders-grid {
            display: grid;
            gap: 25px;
        }
        
        .tender-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-right: 6px solid;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 25px;
            align-items: start;
        }
        
        .tender-card:hover {
            transform: translateX(-8px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
        }
        
        .tender-card.identified {
            border-right-color: #f093fb;
        }
        
        .tender-card.reviewing {
            border-right-color: #4facfe;
        }
        
        .tender-card.proposal_sent {
            border-right-color: #43e97b;
        }
        
        .tender-card.won {
            border-right-color: #38ef7d;
        }
        
        .tender-card.lost {
            border-right-color: #ff6a00;
        }
        
        .tender-icon {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 100%);
            color: white;
            flex-shrink: 0;
        }
        
        .tender-info {
            flex: 1;
        }
        
        .tender-number {
            color: #999;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .tender-title {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: bold;
        }
        
        .tender-client {
            color: #666;
            font-size: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tender-meta {
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
        
        .badge-status {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-deadline {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-value {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-proposals {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .tender-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
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
            white-space: nowrap;
        }
        
        .btn-view {
            background: #4caf50;
            color: white;
        }
        
        .btn-edit {
            background: #2196f3;
            color: white;
        }
        
        .btn-proposal {
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 100%);
            color: white;
        }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 10px 18px;
            border: 2px solid #FA8BFF;
            border-radius: 10px;
            color: #FA8BFF;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .page-link:hover,
        .page-link.active {
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 100%);
            color: white;
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
            .tender-card {
                grid-template-columns: 1fr;
            }
            
            .tender-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
            
            .header-top {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <h1>📋 مدیریت مناقصات</h1>
                <?php if (check_permission('marketing', PERMISSION_WRITE)): ?>
                    <a href="tender.php?action=add" class="btn btn-primary">
                        ➕ شناسایی مناقصه جدید
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card total">
                    <span class="stat-number"><?php echo en2fa($stats['total']); ?></span>
                    <span class="stat-label">کل مناقصات</span>
                </div>
                <div class="stat-card identified">
                    <span class="stat-number"><?php echo en2fa($stats['identified']); ?></span>
                    <span class="stat-label">شناسایی شده</span>
                </div>
                <div class="stat-card reviewing">
                    <span class="stat-number"><?php echo en2fa($stats['reviewing']); ?></span>
                    <span class="stat-label">در حال بررسی</span>
                </div>
                <div class="stat-card proposal_sent">
                    <span class="stat-number"><?php echo en2fa($stats['proposal_sent']); ?></span>
                    <span class="stat-label">ارسال پیشنهاد</span>
                </div>
                <div class="stat-card won">
                    <span class="stat-number"><?php echo en2fa($stats['won']); ?></span>
                    <span class="stat-label">برنده شده</span>
                </div>
                <div class="stat-card lost">
                    <span class="stat-number"><?php echo en2fa($stats['lost']); ?></span>
                    <span class="stat-label">از دست رفته</span>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-tabs">
                <a href="?" class="filter-tab <?php echo !$status ? 'active' : ''; ?>">همه</a>
                <a href="?status=identified" class="filter-tab <?php echo $status === 'identified' ? 'active' : ''; ?>">شناسایی شده</a>
                <a href="?status=reviewing" class="filter-tab <?php echo $status === 'reviewing' ? 'active' : ''; ?>">در حال بررسی</a>
                <a href="?status=proposal_sent" class="filter-tab <?php echo $status === 'proposal_sent' ? 'active' : ''; ?>">ارسال پیشنهاد</a>
                <a href="?status=won" class="filter-tab <?php echo $status === 'won' ? 'active' : ''; ?>">برنده شده</a>
                <a href="?status=lost" class="filter-tab <?php echo $status === 'lost' ? 'active' : ''; ?>">از دست رفته</a>
            </div>
            
            <form method="GET" class="search-box">
                <input type="hidden" name="status" value="<?php echo h($status); ?>">
                <input type="text" name="search" placeholder="جستجو در عنوان، شماره یا کارفرما..." 
                       value="<?php echo h($search); ?>">
                <button type="submit" class="btn btn-primary">🔍 جستجو</button>
            </form>
        </div>
        
        <div class="tenders-grid">
            <?php if (count($tenders) > 0): ?>
                <?php foreach ($tenders as $tender): ?>
                    <div class="tender-card <?php echo $tender['status']; ?>">
                        <div class="tender-icon">📋</div>
                        
                        <div class="tender-info">
                            <div class="tender-number">شماره: <?php echo h($tender['tender_number']); ?></div>
                            <h3 class="tender-title"><?php echo h($tender['title']); ?></h3>
                            
                            <?php if ($tender['client']): ?>
                                <div class="tender-client">
                                    <span>🏢</span>
                                    <span><?php echo h($tender['client']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="tender-meta">
                                <span class="badge badge-status">
                                    <?php 
                                    $statuses = [
                                        'identified' => '🔍 شناسایی شده',
                                        'reviewing' => '📖 در حال بررسی',
                                        'proposal_sent' => '📤 ارسال پیشنهاد',
                                        'won' => '✅ برنده شده',
                                        'lost' => '❌ از دست رفته',
                                        'cancelled' => '⛔ لغو شده'
                                    ];
                                    echo $statuses[$tender['status']] ?? $tender['status'];
                                    ?>
                                </span>
                                
                                <?php if ($tender['deadline_date']): ?>
                                    <span class="badge badge-deadline">
                                        ⏰ مهلت: <?php echo h($tender['deadline_date']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($tender['estimated_value']): ?>
                                    <span class="badge badge-value">
                                        💰 <?php echo number_format($tender['estimated_value']); ?> <?php echo h($tender['currency']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <span class="badge badge-proposals">
                                    📄 <?php echo en2fa($tender['proposals_count']); ?> پیشنهاد
                                </span>
                            </div>
                            
                            <?php if ($tender['description']): ?>
                                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                                    <?php echo h(mb_substr($tender['description'], 0, 150)) . (mb_strlen($tender['description']) > 150 ? '...' : ''); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tender-actions">
                            <a href="tender.php?action=view&id=<?php echo $tender['id']; ?>" 
                               class="btn-sm btn-view">👁 مشاهده</a>
                            
                            <?php if (check_permission('marketing', PERMISSION_WRITE)): ?>
                                <a href="tender.php?action=edit&id=<?php echo $tender['id']; ?>" 
                                   class="btn-sm btn-edit">✏️ ویرایش</a>
                                
                                <a href="proposals.php?tender_id=<?php echo $tender['id']; ?>" 
                                   class="btn-sm btn-proposal">📝 پیشنهادات</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link">قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                               class="page-link">بعدی</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">📋</div>
                    <h3>هیچ مناقصه‌ای یافت نشد</h3>
                    <p>برای شناسایی مناقصه جدید از دکمه بالا استفاده کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>