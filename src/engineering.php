<?php
/**
 * داشبورد بخش مهندسی
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('engineering', PERMISSION_READ)) {
    die('شما مجوز دسترسی به بخش مهندسی را ندارید.');
}

// دریافت آمار مهندسی
$engineeringStats = [
    'total_products' => db()->count('products'),
    'active_products' => db()->count('products', "status = 'active'"),
    'total_parts' => db()->count('parts', "status = 'active'"),
    'total_qc_forms' => db()->count('qc_forms'),
    'pending_qc' => db()->count('qc_forms', "status IN ('open', 'in_progress')"),
    'proposals_draft' => db()->count('proposals', "status = 'draft'"),
];

// محصولات اخیر
$recentProducts = db()->select(
    "SELECT p.*, u.fullname as creator_name,
     (SELECT COUNT(*) FROM bom WHERE product_id = p.id AND is_active = 1) as parts_count
     FROM products p
     LEFT JOIN users u ON u.id = p.created_by
     ORDER BY p.created_at DESC
     LIMIT 6"
);

// قطعات پرمصرف
$topParts = db()->select(
    "SELECT p.*, 
     (SELECT COUNT(*) FROM bom WHERE part_id = p.id AND is_active = 1) as usage_count,
     c.name as supplier_name
     FROM parts p
     LEFT JOIN contacts c ON c.id = p.supplier_contact_id
     WHERE p.status = 'active'
     ORDER BY usage_count DESC
     LIMIT 10"
);

// پیشنهادات اخیر
$recentProposals = db()->select(
    "SELECT p.*, 
     t.title as tender_title,
     pr.title as project_title,
     u.fullname as prepared_by_name
     FROM proposals p
     LEFT JOIN tenders t ON t.id = p.tender_id
     LEFT JOIN projects pr ON pr.id = p.project_id
     LEFT JOIN users u ON u.id = p.prepared_by
     ORDER BY p.created_at DESC
     LIMIT 8"
);

// فرم‌های کنترل کیفیت در حال انجام
$activeQCForms = db()->select(
    "SELECT qc.*, 
     p.title as project_title,
     pr.name as product_name,
     u.fullname as inspector_name
     FROM qc_forms qc
     LEFT JOIN projects p ON p.id = qc.project_id
     LEFT JOIN products pr ON pr.id = qc.product_id
     LEFT JOIN users u ON u.id = qc.inspector_user_id
     WHERE qc.status IN ('open', 'in_progress')
     ORDER BY qc.created_at DESC
     LIMIT 10"
);

// آمار BOM
$bomStats = db()->selectOne(
    "SELECT 
        COUNT(DISTINCT product_id) as products_with_bom,
        COUNT(*) as total_bom_items,
        AVG(quantity) as avg_quantity_per_item
     FROM bom 
     WHERE is_active = 1"
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مهندسی - <?php echo SITE_TITLE; ?></title>
    <style>
        .engineering-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .engineering-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.3);
        }
        
        .engineering-header h1 {
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
            color: #3498db;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .product-icon {
            font-size: 36px;
        }
        
        .product-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-development {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-obsolete {
            background: #f8d7da;
            color: #721c24;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .product-code {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .product-footer {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .product-stat {
            text-align: center;
        }
        
        .product-stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #3498db;
        }
        
        .product-stat-label {
            font-size: 11px;
            color: #7f8c8d;
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: right;
            font-weight: bold;
            font-size: 13px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
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
        .badge-review { background: #fff3cd; color: #856404; }
        .badge-submitted { background: #d1ecf1; color: #0c5460; }
        .badge-accepted { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        
        .badge-open { background: #d1ecf1; color: #0c5460; }
        .badge-in_progress { background: #fff3cd; color: #856404; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-approved { background: #c3e6cb; color: #155724; }
        
        .badge-technical { background: #e3f2fd; color: #1976d2; }
        .badge-financial { background: #e8f5e9; color: #388e3c; }
        .badge-combined { background: #f3e5f5; color: #7b1fa2; }
        .badge-final { background: #fff3e0; color: #f57c00; }
        
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
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* BOM Info */
        .bom-info {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .bom-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .bom-stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .bom-stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #3498db;
        }
        
        .bom-stat-label {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .products-grid {
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
    <div class="engineering-container">
        <!-- Engineering Header -->
        <div class="engineering-header">
            <h1>⚙️ داشبورد مهندسی</h1>
            <p>مدیریت محصولات، قطعات، BOM و کنترل کیفیت</p>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo en2fa($engineeringStats['total_products']); ?></h3>
                <p>کل محصولات</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($engineeringStats['active_products']); ?></h3>
                <p>محصولات فعال</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($engineeringStats['total_parts']); ?></h3>
                <p>قطعات</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($engineeringStats['pending_qc']); ?></h3>
                <p>کنترل کیفیت در حال انجام</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($engineeringStats['proposals_draft']); ?></h3>
                <p>پیشنهادات پیش‌نویس</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo en2fa($bomStats['products_with_bom'] ?? 0); ?></h3>
                <p>محصولات با BOM</p>
            </div>
        </div>
        
        <!-- BOM Statistics -->
        <div class="bom-info">
            <h2 style="margin-bottom: 20px;">📊 آمار BOM (Bill of Materials)</h2>
            <div class="bom-stats">
                <div class="bom-stat-item">
                    <div class="bom-stat-value"><?php echo en2fa($bomStats['products_with_bom'] ?? 0); ?></div>
                    <div class="bom-stat-label">محصولات با BOM</div>
                </div>
                <div class="bom-stat-item">
                    <div class="bom-stat-value"><?php echo en2fa($bomStats['total_bom_items'] ?? 0); ?></div>
                    <div class="bom-stat-label">کل آیتم‌های BOM</div>
                </div>
                <div class="bom-stat-item">
                    <div class="bom-stat-value">
                        <?php echo en2fa(number_format($bomStats['avg_quantity_per_item'] ?? 0, 2)); ?>
                    </div>
                    <div class="bom-stat-label">میانگین مقدار هر آیتم</div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="products.php" class="btn btn-primary">📦 مدیریت محصولات</a>
            <a href="product.php?action=add" class="btn btn-success">➕ محصول جدید</a>
            <a href="parts.php" class="btn btn-primary">🔧 قطعات</a>
            <a href="proposals.php" class="btn btn-primary">📄 پیشنهادات</a>
            <a href="qc.php" class="btn btn-primary">✅ کنترل کیفیت</a>
        </div>
        
        <!-- Recent Products -->
        <h2 style="margin: 30px 0 20px;">📦 محصولات اخیر</h2>
        <div class="products-grid">
            <?php foreach ($recentProducts as $product): ?>
                <div class="product-card">
                    <div class="product-header">
                        <div class="product-icon">⚙️</div>
                        <span class="product-status status-<?php echo $product['status']; ?>">
                            <?php 
                            $statuses = ['development' => 'در حال توسعه', 'active' => 'فعال', 'obsolete' => 'منسوخ'];
                            echo $statuses[$product['status']] ?? $product['status'];
                            ?>
                        </span>
                    </div>
                    
                    <div class="product-name"><?php echo h($product['name']); ?></div>
                    <div class="product-code">کد: <?php echo h($product['code']); ?></div>
                    
                    <?php if ($product['description']): ?>
                        <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 15px;">
                            <?php echo h(mb_substr($product['description'], 0, 100)) . '...'; ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="product-footer">
                        <div class="product-stat">
                            <div class="product-stat-value"><?php echo en2fa($product['parts_count']); ?></div>
                            <div class="product-stat-label">قطعات</div>
                        </div>
                        <div class="product-stat">
                            <div class="product-stat-value">
                                <?php echo $product['version'] ? en2fa($product['version']) : '-'; ?>
                            </div>
                            <div class="product-stat-label">نسخه</div>
                        </div>
                        <div class="product-stat">
                            <div class="product-stat-label" style="margin-top: 5px;">
                                <a href="product.php?action=view&id=<?php echo $product['id']; ?>" 
                                   style="color: #3498db; text-decoration: none; font-weight: bold;">
                                    مشاهده →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Top Parts -->
        <div class="table-container">
            <div class="table-header">
                🔧 قطعات پرمصرف
                <a href="parts.php" style="color: white; text-decoration: none; font-size: 13px;">
                    مشاهده همه
                </a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>شماره قطعه</th>
                        <th>نام</th>
                        <th>دسته‌بندی</th>
                        <th>تامین‌کننده</th>
                        <th>قیمت واحد</th>
                        <th>تعداد استفاده</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topParts as $part): ?>
                        <tr>
                            <td><strong><?php echo h($part['part_number']); ?></strong></td>
                            <td><?php echo h($part['name']); ?></td>
                            <td><?php echo h($part['category'] ?: '-'); ?></td>
                            <td><?php echo h($part['supplier_name'] ?: '-'); ?></td>
                            <td>
                                <?php echo en2fa(number_format($part['unit_price'])); ?>
                                <?php echo $part['currency']; ?>
                            </td>
                            <td>
                                <span style="color: #3498db; font-weight: bold;">
                                    <?php echo en2fa($part['usage_count']); ?> محصول
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Active QC Forms -->
        <?php if (count($activeQCForms) > 0): ?>
        <div class="table-container">
            <div class="table-header">
                ✅ فرم‌های کنترل کیفیت در حال انجام
                <span><?php echo en2fa(count($activeQCForms)); ?> مورد</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>شماره فرم</th>
                        <th>نوع</th>
                        <th>پروژه</th>
                        <th>محصول</th>
                        <th>بازرس</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeQCForms as $qc): ?>
                        <tr>
                            <td><strong><?php echo h($qc['form_number']); ?></strong></td>
                            <td><?php echo h($qc['type']); ?></td>
                            <td><?php echo h($qc['project_title'] ?: '-'); ?></td>
                            <td><?php echo h($qc['product_name'] ?: '-'); ?></td>
                            <td><?php echo h($qc['inspector_name'] ?: 'تعیین نشده'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $qc['status']; ?>">
                                    <?php 
                                    $statuses = [
                                        'open' => 'باز', 
                                        'in_progress' => 'در حال انجام', 
                                        'completed' => 'تکمیل شده', 
                                        'approved' => 'تایید شده'
                                    ];
                                    echo $statuses[$qc['status']] ?? $qc['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo en2fa(date('Y/m/d', strtotime($qc['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Recent Proposals -->
        <div class="table-container">
            <div class="table-header">
                📄 پیشنهادات اخیر
                <a href="proposals.php" style="color: white; text-decoration: none; font-size: 13px;">
                    مشاهده همه
                </a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>نوع</th>
                        <th>عنوان</th>
                        <th>مناقصه/پروژه</th>
                        <th>وضعیت</th>
                        <th>تهیه‌کننده</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentProposals as $prop): ?>
                        <tr>
                            <td><strong><?php echo h($prop['proposal_number']); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $prop['type']; ?>">
                                    <?php 
                                    $types = [
                                        'technical' => 'فنی', 
                                        'financial' => 'مالی', 
                                        'combined' => 'ترکیبی', 
                                        'final' => 'نهایی'
                                    ];
                                    echo $types[$prop['type']] ?? $prop['type'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo h($prop['title']); ?></td>
                            <td><?php echo h($prop['tender_title'] ?: $prop['project_title'] ?: '-'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $prop['status']; ?>">
                                    <?php 
                                    $statuses = [
                                        'draft' => 'پیش‌نویس', 
                                        'review' => 'در حال بررسی', 
                                        'submitted' => 'ارسال شده', 
                                        'accepted' => 'پذیرفته شده', 
                                        'rejected' => 'رد شده'
                                    ];
                                    echo $statuses[$prop['status']] ?? $prop['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo h($prop['prepared_by_name']); ?></td>
                            <td><?php echo en2fa(date('Y/m/d', strtotime($prop['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>