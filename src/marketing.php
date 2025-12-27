<?php
/**
 * داشبورد بازرگانی و فروش
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('marketing', PERMISSION_READ)) {
    die('شما مجوز دسترسی به بخش بازرگانی را ندارید.');
}

// دریافت آمار بازرگانی
$marketingStats = [
    'active_tenders' => db()->count('tenders', "status IN ('identified', 'reviewing', 'proposal_sent')"),
    'won_tenders' => db()->count('tenders', "status = 'won'"),
    'lost_tenders' => db()->count('tenders', "status = 'lost'"),
    'total_proposals' => db()->count('proposals'),
    'pending_proposals' => db()->count('proposals', "status IN ('draft', 'review')"),
];

// مناقصات فعال
$activeTenders = db()->select(
    "SELECT t.*, u.fullname as creator_name
     FROM tenders t
     LEFT JOIN users u ON u.id = t.created_by
     WHERE t.status IN ('identified', 'reviewing', 'proposal_sent')
     ORDER BY t.deadline_date ASC
     LIMIT 8"
);

// پیشنهادات اخیر
$recentProposals = db()->select(
    "SELECT p.*, 
     t.title as tender_title,
     t.tender_number,
     u.fullname as prepared_by_name
     FROM proposals p
     LEFT JOIN tenders t ON t.id = p.tender_id
     LEFT JOIN users u ON u.id = p.prepared_by
     ORDER BY p.created_at DESC
     LIMIT 8"
);

// نرخ موفقیت در مناقصات
$tenderSuccess = db()->selectOne(
    "SELECT 
        COUNT(CASE WHEN status = 'won' THEN 1 END) as won,
        COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost,
        COUNT(CASE WHEN status IN ('won', 'lost') THEN 1 END) as total
     FROM tenders"
);

$successRate = $tenderSuccess['total'] > 0 
    ? round(($tenderSuccess['won'] / $tenderSuccess['total']) * 100) 
    : 0;

// مناقصات به تفکیک وضعیت
$tendersByStatus = db()->select(
    "SELECT status, COUNT(*) as count 
     FROM tenders 
     GROUP BY status"
);

// ارزش کل مناقصات برنده شده
$wonValue = db()->selectOne(
    "SELECT SUM(estimated_value) as total_value
     FROM tenders
     WHERE status = 'won'"
);

// مناقصاتی که deadline نزدیک است
$upcomingDeadlines = db()->select(
    "SELECT * FROM tenders 
     WHERE status IN ('identified', 'reviewing')
     AND deadline_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY deadline_date ASC"
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بازرگانی و فروش - <?php echo SITE_TITLE; ?></title>
    <style>
        .marketing-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .marketing-header {
            background: linear-gradient(135deg, #d35400 0%, #e67e22 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(211, 84, 0, 0.3);
        }
        
        .marketing-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 10px;
            color: #d35400;
        }
        
        .stat-card.success h3 {
            color: #27ae60;
        }
        
        .stat-card.danger h3 {
            color: #e74c3c;
        }
        
        /* Success Rate */
        .success-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .success-rate {
            font-size: 72px;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .success-details {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 20px;
        }
        
        .success-item {
            text-align: center;
        }
        
        .success-value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .success-label {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #d35400 0%, #e67e22 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Alert Bar */
        .alert-bar {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .alert-icon {
            font-size: 36px;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: bold;
            color: #856404;
            margin-bottom: 5px;
        }
        
        .alert-text {
            color: #856404;
            font-size: 14px;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #d35400 0%, #e67e22 100%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-body.no-padding {
            padding: 0;
        }
        
        /* Tender Item */
        .tender-item {
            padding: 15px;
            border-right: 4px solid #d35400;
            background: #f8f9fa;
            margin-bottom: 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tender-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .tender-item.urgent {
            border-right-color: #e74c3c;
            background: #ffebee;
        }
        
        .tender-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .tender-number {
            font-size: 11px;
            color: #7f8c8d;
            font-weight: bold;
        }
        
        .tender-status {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-identified {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .status-reviewing {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .status-proposal_sent {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .status-won {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-lost {
            background: #ffebee;
            color: #c62828;
        }
        
        .tender-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .tender-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .tender-deadline {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .tender-deadline.urgent {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .tender-value {
            font-weight: bold;
            color: #27ae60;
        }
        
        /* Proposal Item */
        .proposal-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        .proposal-item:hover {
            background: #f8f9fa;
        }
        
        .proposal-item:last-child {
            border-bottom: none;
        }
        
        .proposal-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .proposal-number {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .proposal-type {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .type-technical {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .type-financial {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .type-combined {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .type-final {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .proposal-tender {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .proposal-meta {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #7f8c8d;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .success-details {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="marketing-container">
        <!-- Marketing Header -->
        <div class="marketing-header">
            <h1>💼 بازرگانی و فروش</h1>
            <p>مدیریت مناقصات، پیشنهادات و فرآیند فروش</p>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo en2fa($marketingStats['active_tenders']); ?></h3>
                <p>مناقصات فعال</p>
            </div>
            
            <div class="stat-card success">
                <h3><?php echo en2fa($marketingStats['won_tenders']); ?></h3>
                <p>برنده شده</p>
            </div>
            
            <div class="stat-card danger">
                <h3><?php echo en2fa($marketingStats['lost_tenders']); ?></h3>
                <p>از دست رفته</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($marketingStats['pending_proposals']); ?></h3>
                <p>پیشنهادات در حال تهیه</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa(number_format(($wonValue['total_value'] ?? 0) / 1000000)); ?>M</h3>
                <p>ارزش قراردادهای برنده (ریال)</p>
            </div>
        </div>
        
        <!-- Success Rate -->
        <div class="success-card">
            <h3 style="margin-bottom: 10px;">نرخ موفقیت در مناقصات</h3>
            <div class="success-rate"><?php echo en2fa($successRate); ?>%</div>
            <div class="success-details">
                <div class="success-item">
                    <div class="success-value" style="color: #4caf50;">
                        <?php echo en2fa($tenderSuccess['won']); ?>
                    </div>
                    <div class="success-label">برنده شده</div>
                </div>
                <div class="success-item">
                    <div class="success-value" style="color: #f44336;">
                        <?php echo en2fa($tenderSuccess['lost']); ?>
                    </div>
                    <div class="success-label">از دست رفته</div>
                </div>
                <div class="success-item">
                    <div class="success-value">
                        <?php echo en2fa($tenderSuccess['total']); ?>
                    </div>
                    <div class="success-label">کل مناقصات بسته شده</div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Deadlines Alert -->
        <?php if (count($upcomingDeadlines) > 0): ?>
        <div class="alert-bar">
            <div class="alert-icon">⏰</div>
            <div class="alert-content">
                <div class="alert-title">هشدار: مهلت‌های نزدیک!</div>
                <div class="alert-text">
                    <?php echo en2fa(count($upcomingDeadlines)); ?> مناقصه تا ۷ روز آینده به پایان مهلت می‌رسد
                </div>
            </div>
            <a href="tenders.php?filter=upcoming" class="btn btn-primary">مشاهده</a>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="tenders.php" class="btn btn-primary">📋 لیست مناقصات</a>
            <a href="tender.php?action=add" class="btn btn-success">➕ مناقصه جدید</a>
            <a href="proposals.php" class="btn btn-primary">📄 پیشنهادات</a>
            <a href="proposal.php?action=add" class="btn btn-success">➕ پیشنهاد جدید</a>
            <a href="marketing_reports.php" class="btn btn-primary">📊 گزارشات</a>
        </div>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Active Tenders -->
            <div class="dashboard-card">
                <div class="card-header">
                    📋 مناقصات فعال
                    <a href="tenders.php" style="color: white; text-decoration: none; font-size: 13px;">
                        مشاهده همه
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($activeTenders) > 0): ?>
                        <?php foreach ($activeTenders as $tender): 
                            $daysUntilDeadline = $tender['deadline_date'] 
                                ? round((strtotime($tender['deadline_date']) - time()) / (60 * 60 * 24))
                                : null;
                            $isUrgent = $daysUntilDeadline !== null && $daysUntilDeadline <= 3;
                            
                            $statusLabels = [
                                'identified' => 'شناسایی شده',
                                'reviewing' => 'در حال بررسی',
                                'proposal_sent' => 'پیشنهاد ارسال شده',
                                'won' => 'برنده',
                                'lost' => 'باخت',
                                'cancelled' => 'لغو شده'
                            ];
                        ?>
                            <div class="tender-item <?php echo $isUrgent ? 'urgent' : ''; ?>" 
                                 onclick="window.location.href='tender.php?action=view&id=<?php echo $tender['id']; ?>'">
                                
                                <div class="tender-header">
                                    <div class="tender-number"><?php echo h($tender['tender_number']); ?></div>
                                    <div class="tender-status status-<?php echo $tender['status']; ?>">
                                        <?php echo $statusLabels[$tender['status']] ?? $tender['status']; ?>
                                    </div>
                                </div>
                                
                                <div class="tender-title"><?php echo h($tender['title']); ?></div>
                                
                                <?php if ($tender['client']): ?>
                                    <div style="font-size: 12px; color: #7f8c8d; margin-bottom: 8px;">
                                        👤 <?php echo h($tender['client']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="tender-info">
                                    <?php if ($tender['deadline_date']): ?>
                                        <div class="tender-deadline <?php echo $isUrgent ? 'urgent' : ''; ?>">
                                            ⏰ مهلت: <?php echo en2fa(date('Y/m/d', strtotime($tender['deadline_date']))); ?>
                                            <?php if ($daysUntilDeadline !== null): ?>
                                                (<?php echo en2fa(abs($daysUntilDeadline)); ?> روز)
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($tender['estimated_value']): ?>
                                        <div class="tender-value">
                                            💰 <?php echo en2fa(number_format($tender['estimated_value'])); ?> ریال
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            📋 مناقصه فعالی وجود ندارد
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Proposals -->
            <div class="dashboard-card">
                <div class="card-header">
                    📄 پیشنهادات اخیر
                    <a href="proposals.php" style="color: white; text-decoration: none; font-size: 13px;">
                        مشاهده همه
                    </a>
                </div>
                <div class="card-body no-padding">
                    <?php if (count($recentProposals) > 0): ?>
                        <?php foreach ($recentProposals as $proposal): 
                            $typeLabels = [
                                'technical' => 'فنی',
                                'financial' => 'مالی',
                                'combined' => 'ترکیبی',
                                'final' => 'نهایی'
                            ];
                            
                            $statusLabels = [
                                'draft' => 'پیش‌نویس',
                                'review' => 'بررسی',
                                'submitted' => 'ارسال شده',
                                'accepted' => 'پذیرفته شده',
                                'rejected' => 'رد شده'
                            ];
                        ?>
                            <div class="proposal-item">
                                <div class="proposal-header">
                                    <div class="proposal-number">
                                        <?php echo h($proposal['proposal_number']); ?>
                                    </div>
                                    <div class="proposal-type type-<?php echo $proposal['type']; ?>">
                                        <?php echo $typeLabels[$proposal['type']] ?? $proposal['type']; ?>
                                    </div>
                                </div>
                                
                                <?php if ($proposal['tender_title']): ?>
                                    <div class="proposal-tender">
                                        📋 <?php echo h($proposal['tender_number'] . ' - ' . $proposal['tender_title']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="proposal-meta">
                                    <span>
                                        👤 <?php echo h($proposal['prepared_by_name']); ?>
                                    </span>
                                    <span style="font-weight: bold; color: #d35400;">
                                        <?php echo $statusLabels[$proposal['status']] ?? $proposal['status']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            📄 پیشنهادی ثبت نشده است
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>