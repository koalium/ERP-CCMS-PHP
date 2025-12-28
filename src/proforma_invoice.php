<?php
/**
 * مدیریت پیش‌فاکتور و فاکتور
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'jalali-converter.php';

check_login();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'proforma'; // proforma or invoice
$error = '';
$success = '';

// چک مجوز
if (!check_permission('marketing', PERMISSION_READ) && !check_permission('sell', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// لیست پیش‌فاکتورها/فاکتورها
if ($action === 'list') {
    $filter_type = $_GET['filter_type'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    
    $sql = "SELECT i.*, c.name as customer_name, c.company_name, u.fullname as created_by_name
            FROM invoices i
            LEFT JOIN contacts c ON c.id = i.customer_id
            LEFT JOIN users u ON u.id = i.created_by
            WHERE 1=1";
    
    $params = [];
    
    if ($filter_type) {
        $sql .= " AND i.type = :type";
        $params[':type'] = $filter_type;
    }
    
    if ($status) {
        $sql .= " AND i.status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY i.created_at DESC";
    
    $result = db()->paginate($sql, $params, $page, $perPage);
    $invoices = $result['data'];
    $totalPages = $result['total_pages'];
    
    // آمار
    $stats = db()->selectOne("
        SELECT 
            COUNT(CASE WHEN type = 'proforma' THEN 1 END) as total_proforma,
            COUNT(CASE WHEN type = 'invoice' THEN 1 END) as total_invoice,
            SUM(CASE WHEN type = 'proforma' AND status = 'approved' THEN total_amount ELSE 0 END) as proforma_value,
            SUM(CASE WHEN type = 'invoice' AND status = 'paid' THEN total_amount ELSE 0 END) as invoice_value
        FROM invoices
    ");
    ?>
    
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>مدیریت پیش‌فاکتور و فاکتور - <?php echo SITE_TITLE; ?></title>
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
            
            .btn-success {
                background: #4caf50;
                color: white;
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            }
            
            .stats {
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
            }
            
            .stat-card h3 {
                font-size: 14px;
                color: #666;
                margin-bottom: 10px;
            }
            
            .stat-card .value {
                font-size: 28px;
                font-weight: bold;
                color: #2c3e50;
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
            
            .badge-proforma {
                background: #e3f2fd;
                color: #1976d2;
            }
            
            .badge-invoice {
                background: #e8f5e9;
                color: #388e3c;
            }
            
            .badge-draft {
                background: #f5f5f5;
                color: #666;
            }
            
            .badge-pending {
                background: #fff3e0;
                color: #f57c00;
            }
            
            .badge-approved {
                background: #e8f5e9;
                color: #388e3c;
            }
            
            .badge-paid {
                background: #c8e6c9;
                color: #2e7d32;
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
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📄 مدیریت پیش‌فاکتور و فاکتور</h1>
                <div style="display: flex; gap: 10px;">
                    <?php if (check_permission('marketing', PERMISSION_WRITE) || check_permission('sell', PERMISSION_WRITE)): ?>
                        <a href="?action=add&type=proforma" class="btn btn-primary">➕ پیش‌فاکتور جدید</a>
                        <a href="?action=add&type=invoice" class="btn btn-success">➕ فاکتور جدید</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <h3>تعداد پیش‌فاکتورها</h3>
                    <div class="value"><?php echo en2fa(number_format($stats['total_proforma'])); ?></div>
                </div>
                <div class="stat-card">
                    <h3>ارزش پیش‌فاکتورهای تایید شده</h3>
                    <div class="value"><?php echo en2fa(number_format($stats['proforma_value'], 0)); ?></div>
                </div>
                <div class="stat-card">
                    <h3>تعداد فاکتورها</h3>
                    <div class="value"><?php echo en2fa(number_format($stats['total_invoice'])); ?></div>
                </div>
                <div class="stat-card">
                    <h3>ارزش فاکتورهای پرداخت شده</h3>
                    <div class="value"><?php echo en2fa(number_format($stats['invoice_value'], 0)); ?></div>
                </div>
            </div>
            
            <div class="filters">
                <form method="GET">
                    <input type="hidden" name="action" value="list">
                    <div class="form-group">
                        <label>نوع</label>
                        <select name="filter_type">
                            <option value="">همه</option>
                            <option value="proforma" <?php echo $filter_type === 'proforma' ? 'selected' : ''; ?>>پیش‌فاکتور</option>
                            <option value="invoice" <?php echo $filter_type === 'invoice' ? 'selected' : ''; ?>>فاکتور</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status">
                            <option value="">همه</option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>در انتظار</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                            <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>پرداخت شده</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>تاریخ</th>
                            <th>نوع</th>
                            <th>مشتری</th>
                            <th>مبلغ کل</th>
                            <th>وضعیت</th>
                            <th>ایجادکننده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?php echo h($inv['invoice_number']); ?></strong></td>
                            <td><?php echo en2fa(formatJalaliDate($inv['invoice_date'], 'Y/m/d')); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $inv['type']; ?>">
                                    <?php echo $inv['type'] === 'proforma' ? 'پیش‌فاکتور' : 'فاکتور'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo h($inv['customer_name']); ?>
                                <?php if ($inv['company_name']): ?>
                                    <br><small style="color: #999;"><?php echo h($inv['company_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo en2fa(number_format($inv['total_amount'], 0)); ?></strong></td>
                            <td>
                                <?php
                                $statusLabels = [
                                    'draft' => 'پیش‌نویس',
                                    'pending' => 'در انتظار',
                                    'approved' => 'تایید شده',
                                    'paid' => 'پرداخت شده'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $inv['status']; ?>">
                                    <?php echo $statusLabels[$inv['status']] ?? $inv['status']; ?>
                                </span>
                            </td>
                            <td><?php echo h($inv['created_by_name']); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?action=view&id=<?php echo $inv['id']; ?>" 
                                       class="btn-sm btn-view" title="مشاهده">👁</a>
                                    <?php if ($inv['status'] === 'draft'): ?>
                                        <a href="?action=edit&id=<?php echo $inv['id']; ?>" 
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
                        <!-- کد صفحه‌بندی -->
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    
    <?php
}

// ایجاد جدول فاکتورها اگر وجود ندارد
$createInvoicesTable = "CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('proforma', 'invoice') NOT NULL,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(20, 2) NOT NULL,
    tax_amount DECIMAL(20, 2) DEFAULT 0,
    discount_amount DECIMAL(20, 2) DEFAULT 0,
    total_amount DECIMAL(20, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'IRR',
    status ENUM('draft', 'pending', 'approved', 'rejected', 'paid', 'cancelled') DEFAULT 'draft',
    notes TEXT,
    terms TEXT,
    created_by INT NOT NULL,
    approved_by INT,
    approved_at DATETIME,
    qc_approved TINYINT(1) DEFAULT 0,
    qc_approved_by INT,
    qc_approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES contacts(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (qc_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_number (invoice_number),
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createInvoicesTable);

$createInvoiceItemsTable = "CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_id INT,
    description TEXT NOT NULL,
    quantity DECIMAL(15, 3) NOT NULL,
    unit VARCHAR(20),
    unit_price DECIMAL(20, 2) NOT NULL,
    total_price DECIMAL(20, 2) NOT NULL,
    notes TEXT,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES warehouse_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createInvoiceItemsTable);
?>