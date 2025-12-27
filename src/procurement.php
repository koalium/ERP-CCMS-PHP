<?php
/**
 * داشبورد تدارکات و خرید
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('procurement', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// ایجاد جدول درخواست‌های خرید اگر وجود ندارد
$createTableSql = "CREATE TABLE IF NOT EXISTS purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    type ENUM('material', 'service', 'equipment', 'other') DEFAULT 'material',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('draft', 'pending', 'approved', 'rejected', 'price_requested', 'ordered', 'received', 'cancelled') DEFAULT 'draft',
    project_id INT,
    requested_by INT NOT NULL,
    approved_by INT,
    total_amount DECIMAL(20, 2),
    currency VARCHAR(3) DEFAULT 'IRR',
    required_date DATE,
    description TEXT,
    items TEXT COMMENT 'JSON array of items',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_number (request_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSql);

// ایجاد جدول استعلام قیمت
$createPriceRequestSql = "CREATE TABLE IF NOT EXISTS price_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    purchase_request_id INT,
    title VARCHAR(200) NOT NULL,
    status ENUM('sent', 'responded', 'selected', 'cancelled') DEFAULT 'sent',
    vendor_contact_id INT,
    sent_date DATE,
    response_date DATE,
    quoted_amount DECIMAL(20, 2),
    currency VARCHAR(3) DEFAULT 'IRR',
    delivery_time_days INT,
    payment_terms TEXT,
    items TEXT COMMENT 'JSON array',
    notes TEXT,
    attachments TEXT COMMENT 'JSON array',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (vendor_contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createPriceRequestSql);

// پیام‌ها
$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'pr_added':
            $message = show_message('درخواست خرید با موفقیت ثبت شد.', 'success');
            break;
        case 'pr_approved':
            $message = show_message('درخواست خرید تایید شد.', 'success');
            break;
        case 'price_added':
            $message = show_message('استعلام قیمت ثبت شد.', 'success');
            break;
    }
}

// آمار درخواست‌های خرید
$prStats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'price_requested' THEN 1 ELSE 0 END) as price_requested,
        SUM(CASE WHEN status = 'ordered' THEN 1 ELSE 0 END) as ordered,
        SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received
    FROM purchase_requests
");

// آمار استعلام قیمت
$priceStats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) as responded,
        SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) as selected
    FROM price_requests
");

// درخواست‌های خرید اخیر
$recentPRs = db()->select(
    "SELECT pr.*,
            u1.fullname as requester_name,
            u2.fullname as approver_name,
            p.title as project_title
     FROM purchase_requests pr
     LEFT JOIN users u1 ON u1.id = pr.requested_by
     LEFT JOIN users u2 ON u2.id = pr.approved_by
     LEFT JOIN projects p ON p.id = pr.project_id
     ORDER BY pr.created_at DESC
     LIMIT 10"
);

// استعلام‌های قیمت اخیر
$recentPriceRequests = db()->select(
    "SELECT pq.*,
            c.name as vendor_name,
            u.fullname as creator_name
     FROM price_requests pq
     LEFT JOIN contacts c ON c.id = pq.vendor_contact_id
     LEFT JOIN users u ON u.id = pq.created_by
     ORDER BY pq.created_at DESC
     LIMIT 10"
);

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تدارکات و خرید - <?php echo SITE_TITLE; ?></title>
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
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        
        .btn-secondary {
            background: #6c757d;
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
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
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
        .stat-icon.pending { background: #fff3e0; }
        .stat-icon.approved { background: #e8f5e9; }
        .stat-icon.price { background: #f3e5f5; }
        .stat-icon.ordered { background: #e1f5fe; }
        .stat-icon.received { background: #e0f2f1; }
        
        .stat-content h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #666;
            font-size: 14px;
        }
        
        .section-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-link {
            color: white;
            text-decoration: none;
            font-size: 13px;
            opacity: 0.9;
            transition: opacity 0.3s;
        }
        
        .section-link:hover {
            opacity: 1;
        }
        
        .section-content {
            padding: 20px;
        }
        
        .list-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-item:hover {
            background: #f8f9fa;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }
        
        .item-title {
            font-weight: bold;
            color: #2c3e50;
            font-size: 15px;
        }
        
        .item-number {
            color: #667eea;
            font-weight: bold;
            font-size: 13px;
        }
        
        .item-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #999;
            flex-wrap: wrap;
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
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-price_requested { background: #d1ecf1; color: #0c5460; }
        .badge-ordered { background: #cce5ff; color: #004085; }
        .badge-received { background: #d4edda; color: #155724; }
        .badge-sent { background: #fff3cd; color: #856404; }
        .badge-responded { background: #d1ecf1; color: #0c5460; }
        .badge-selected { background: #d4edda; color: #155724; }
        
        .badge-urgent { background: #f8d7da; color: #721c24; }
        .badge-high { background: #fff3cd; color: #856404; }
        .badge-normal { background: #cce5ff; color: #004085; }
        .badge-low { background: #e0e0e0; color: #666; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        @media (max-width: 1200px) {
            .section-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 تدارکات و خرید</h1>
            <div class="header-actions">
                <?php if (check_permission('procurement', PERMISSION_WRITE)): ?>
                    <a href="purchase_request.php?action=add" class="btn btn-primary">
                        ➕ درخواست خرید جدید
                    </a>
                    <a href="price_request.php?action=add" class="btn btn-secondary">
                        📋 استعلام قیمت جدید
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php echo $message; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">📊</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($prStats['total'] ?? 0); ?></h3>
                    <p>کل درخواست‌ها</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">⏳</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($prStats['pending'] ?? 0); ?></h3>
                    <p>در انتظار تایید</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">✅</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($prStats['approved'] ?? 0); ?></h3>
                    <p>تایید شده</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon price">💰</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($prStats['price_requested'] ?? 0); ?></h3>
                    <p>استعلام قیمت</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ordered">🛍️</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($prStats['ordered'] ?? 0); ?></h3>
                    <p>سفارش داده شده</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon received">📦</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($prStats['received'] ?? 0); ?></h3>
                    <p>دریافت شده</p>
                </div>
            </div>
        </div>
        
        <div class="section-grid">
            <!-- درخواست‌های خرید -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">📋 درخواست‌های خرید اخیر</div>
                    <a href="purchase_requests.php" class="section-link">مشاهده همه ←</a>
                </div>
                
                <div class="section-content">
                    <?php if (count($recentPRs) > 0): ?>
                        <?php foreach ($recentPRs as $pr): ?>
                            <div class="list-item" onclick="window.location='purchase_request.php?action=view&id=<?php echo $pr['id']; ?>'">
                                <div class="item-header">
                                    <div>
                                        <div class="item-number"><?php echo h($pr['request_number']); ?></div>
                                        <div class="item-title"><?php echo h($pr['title']); ?></div>
                                    </div>
                                    <div>
                                        <?php
                                        $statusLabels = [
                                            'draft' => 'پیش‌نویس',
                                            'pending' => 'در انتظار',
                                            'approved' => 'تایید شده',
                                            'rejected' => 'رد شده',
                                            'price_requested' => 'استعلام قیمت',
                                            'ordered' => 'سفارش داده شده',
                                            'received' => 'دریافت شده',
                                            'cancelled' => 'لغو شده'
                                        ];
                                        $statusClass = 'badge-' . $pr['status'];
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo $statusLabels[$pr['status']] ?? $pr['status']; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="item-meta">
                                    <span>👤 <?php echo h($pr['requester_name']); ?></span>
                                    <span>📅 <?php echo en2fa(date('Y/m/d', strtotime($pr['created_at']))); ?></span>
                                    <?php if ($pr['project_title']): ?>
                                        <span>📊 <?php echo h($pr['project_title']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($pr['total_amount']): ?>
                                        <span>💰 <?php echo en2fa(number_format($pr['total_amount'], 0)); ?> <?php echo h($pr['currency']); ?></span>
                                    <?php endif; ?>
                                    <?php
                                    $priorityLabels = ['urgent' => 'فوری', 'high' => 'بالا', 'normal' => 'عادی', 'low' => 'پایین'];
                                    $priorityClass = 'badge-' . $pr['priority'];
                                    ?>
                                    <span class="badge <?php echo $priorityClass; ?>">
                                        <?php echo $priorityLabels[$pr['priority']] ?? $pr['priority']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            📋
                            <p>هیچ درخواست خریدی وجود ندارد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- استعلام‌های قیمت -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">💰 استعلام‌های قیمت اخیر</div>
                    <a href="price_requests.php" class="section-link">مشاهده همه ←</a>
                </div>
                
                <div class="section-content">
                    <?php if (count($recentPriceRequests) > 0): ?>
                        <?php foreach ($recentPriceRequests as $pq): ?>
                            <div class="list-item" onclick="window.location='price_request.php?action=view&id=<?php echo $pq['id']; ?>'">
                                <div class="item-header">
                                    <div>
                                        <div class="item-number"><?php echo h($pq['request_number']); ?></div>
                                        <div class="item-title"><?php echo h($pq['title']); ?></div>
                                    </div>
                                    <div>
                                        <?php
                                        $statusLabels = [
                                            'sent' => 'ارسال شده',
                                            'responded' => 'پاسخ داده شده',
                                            'selected' => 'انتخاب شده',
                                            'cancelled' => 'لغو شده'
                                        ];
                                        $statusClass = 'badge-' . $pq['status'];
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo $statusLabels[$pq['status']] ?? $pq['status']; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="item-meta">
                                    <?php if ($pq['vendor_name']): ?>
                                        <span>🏢 <?php echo h($pq['vendor_name']); ?></span>
                                    <?php endif; ?>
                                    <span>👤 <?php echo h($pq['creator_name']); ?></span>
                                    <span>📅 <?php echo en2fa(date('Y/m/d', strtotime($pq['created_at']))); ?></span>
                                    <?php if ($pq['quoted_amount']): ?>
                                        <span>💰 <?php echo en2fa(number_format($pq['quoted_amount'], 0)); ?> <?php echo h($pq['currency']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            💰
                            <p>هیچ استعلام قیمتی وجود ندارد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>