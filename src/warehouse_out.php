<?php
/**
 * ثبت خروج کالا از انبار
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'jalali-converter.php';

check_login();

if (!check_permission('warehouse', PERMISSION_WRITE)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$error = '';
$success = '';
$needApproval = true;

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $warehouse_id = (int)$_POST['warehouse_id'];
        $item_id = (int)$_POST['item_id'];
        $quantity = (float)$_POST['quantity'];
        $transaction_date = sanitize_input($_POST['transaction_date']);
        $reference_number = sanitize_input($_POST['reference_number']);
        $contact_id = (int)($_POST['contact_id'] ?? 0) ?: null;
        $project_id = (int)($_POST['project_id'] ?? 0) ?: null;
        $receiver_name = sanitize_input($_POST['receiver_name']);
        $receiver_id_number = sanitize_input($_POST['receiver_id_number']);
        $reason = sanitize_input($_POST['reason']);
        $notes = sanitize_input($_POST['notes']);
        $request_approval = isset($_POST['request_approval']);
        
        // اعتبارسنجی
        if (empty($warehouse_id) || empty($item_id) || $quantity <= 0 || empty($transaction_date)) {
            $error = 'لطفاً تمام فیلدهای الزامی را پر کنید.';
        } else {
            // چک موجودی
            $currentStock = db()->selectOne("
                SELECT COALESCE(SUM(CASE 
                    WHEN type = 'in' AND status = 'completed' THEN quantity
                    WHEN type = 'out' AND status = 'completed' THEN -quantity
                    ELSE 0
                END), 0) as stock
                FROM warehouse_transactions
                WHERE warehouse_id = :warehouse_id AND item_id = :item_id
            ", [':warehouse_id' => $warehouse_id, ':item_id' => $item_id]);
            
            if ($currentStock['stock'] < $quantity) {
                $error = 'موجودی کافی نیست. موجودی فعلی: ' . en2fa(number_format($currentStock['stock'], 2));
            } else {
                // تبدیل تاریخ جلالی به میلادی
                $transaction_date_g = jalaliToGregorianDate($transaction_date);
                if (!$transaction_date_g) {
                    $error = 'فرمت تاریخ نامعتبر است.';
                } else {
                    db()->beginTransaction();
                    
                    try {
                        // تعیین وضعیت
                        $status = $request_approval ? 'pending' : 'completed';
                        $approved_by = $request_approval ? null : $_SESSION['user_id'];
                        $approved_at = $request_approval ? null : date('Y-m-d H:i:s');
                        
                        $transactionData = [
                            'type' => 'out',
                            'status' => $status,
                            'warehouse_id' => $warehouse_id,
                            'item_id' => $item_id,
                            'quantity' => $quantity,
                            'reference_number' => $reference_number,
                            'contact_id' => $contact_id,
                            'project_id' => $project_id,
                            'reason' => $reason,
                            'notes' => $notes,
                            'requested_by' => $_SESSION['user_id'],
                            'approved_by' => $approved_by,
                            'transaction_date' => $transaction_date_g,
                            'approved_at' => $approved_at
                        ];
                        
                        $transactionId = db()->insert('warehouse_transactions', $transactionData);
                        
                        if (!$transactionId) {
                            throw new Exception('خطا در ثبت تراکنش');
                        }
                        
                        // ثبت اطلاعات تحویل‌گیرنده
                        if ($receiver_name) {
                            db()->insert('warehouse_deliveries', [
                                'transaction_id' => $transactionId,
                                'receiver_name' => $receiver_name,
                                'receiver_id_number' => $receiver_id_number,
                                'delivery_date' => $transaction_date_g,
                                'delivered_by' => $_SESSION['user_id']
                            ]);
                        }
                        
                        // اگر مستقیم تایید شد، موجودی را کم کن
                        if (!$request_approval) {
                            db()->query("
                                UPDATE warehouse_items 
                                SET current_stock = current_stock - :quantity 
                                WHERE id = :item_id
                            ", [':quantity' => $quantity, ':item_id' => $item_id]);
                        }
                        
                        db()->commit();
                        
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'warehouse_out',
                            'module' => 'warehouse',
                            'record_id' => $transactionId,
                            'new_data' => json_encode($transactionData, JSON_UNESCAPED_UNICODE),
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        if ($request_approval) {
                            $success = 'درخواست خروج با موفقیت ثبت شد و در انتظار تایید است. شماره تراکنش: ' . $transactionId;
                        } else {
                            $success = 'خروج کالا با موفقیت ثبت شد. شماره تراکنش: ' . $transactionId;
                        }
                        
                        // پاک کردن فرم
                        $_POST = [];
                        
                    } catch (Exception $e) {
                        db()->rollback();
                        $error = 'خطا در ثبت خروج: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// دریافت انبارها
$warehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");

// دریافت کالاها با موجودی
$items = db()->select("
    SELECT wi.id, wi.code, wi.name, wi.unit, wi.current_stock,
           COALESCE(SUM(CASE 
               WHEN wt.type = 'in' AND wt.status = 'completed' THEN wt.quantity
               WHEN wt.type = 'out' AND wt.status = 'completed' THEN -wt.quantity
               ELSE 0
           END), 0) as total_stock
    FROM warehouse_items wi
    LEFT JOIN warehouse_transactions wt ON wt.item_id = wi.id
    WHERE wi.is_active = 1
    GROUP BY wi.id
    HAVING total_stock > 0
    ORDER BY wi.name
");

// دریافت مخاطبین
$contacts = db()->select("
    SELECT id, name, company_name 
    FROM contacts 
    WHERE is_active = 1
    ORDER BY name
");

// دریافت پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects WHERE status IN ('active', 'planning') ORDER BY title");

// تاریخ امروز به شمسی
$today_jalali = jalaliToday();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت خروج از انبار - <?php echo SITE_TITLE; ?></title>
    <script src="jalali-datepicker.js"></script>
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
            max-width: 1200px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group label .required {
            color: #f44336;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f44336;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
            color: white;
            font-size: 16px;
            padding: 12px 30px;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            font-size: 16px;
            padding: 12px 30px;
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #666;
        }
        
        .info-box {
            background: #fff3e0;
            border-right: 4px solid #ff9800;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #e65100;
            margin-bottom: 10px;
        }
        
        .stock-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        
        .stock-info.low {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .stock-info.danger {
            background: #ffebee;
            color: #c62828;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            font-weight: normal;
            cursor: pointer;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📤 ثبت خروج کالا از انبار</h1>
            <div style="display: flex; gap: 10px;">
                <a href="warehouse_transactions.php" class="btn btn-secondary">لیست تراکنش‌ها</a>
                <a href="warehouse_items.php" class="btn btn-secondary">بازگشت</a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>⚠️ راهنما</h3>
            <p>برای ثبت خروج کالا از انبار، ابتدا انبار مبدا و کالا را انتخاب کرده، سپس مقدار و سایر اطلاعات را وارد کنید.</p>
            <p><strong>توجه:</strong> اگر نیاز به تایید مدیر دارید، گزینه "نیاز به تایید" را فعال کنید. در غیر این صورت خروج مستقیماً ثبت می‌شود.</p>
        </div>
        
        <div class="form-container">
            <form method="POST" action="" id="outgoingForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>انبار مبدا <span class="required">*</span></label>
                        <select name="warehouse_id" id="warehouseSelect" required onchange="updateStockInfo()">
                            <option value="">انتخاب انبار</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse['id']; ?>">
                                    <?php echo h($warehouse['name'] . ' (' . $warehouse['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>کالا <span class="required">*</span></label>
                        <select name="item_id" id="itemSelect" required onchange="updateStockInfo()">
                            <option value="">انتخاب کالا</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>"
                                        data-unit="<?php echo h($item['unit']); ?>"
                                        data-stock="<?php echo $item['total_stock']; ?>">
                                    <?php echo h($item['code'] . ' - ' . $item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="stock-info" id="stockInfo" style="display: none;">
                            واحد: <span id="unitDisplay">-</span> | 
                            موجودی: <span id="stockDisplay">-</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>مقدار/تعداد خروج <span class="required">*</span></label>
                        <input type="number" name="quantity" id="quantityInput" 
                               step="0.01" min="0.01" required
                               oninput="checkQuantity()">
                        <small id="quantityWarning" style="color: #f44336; display: none; margin-top: 5px;">
                            ⚠️ مقدار درخواستی بیشتر از موجودی است!
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ خروج <span class="required">*</span></label>
                        <input type="text" name="transaction_date" class="jalali-date-input"
                               value="<?php echo $today_jalali; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>شماره مرجع (حواله)</label>
                        <input type="text" name="reference_number"
                               placeholder="شماره حواله یا برگه خروج">
                    </div>
                    
                    <div class="form-group">
                        <label>پروژه (اختیاری)</label>
                        <select name="project_id">
                            <option value="">بدون پروژه</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>">
                                    <?php echo h($project['code'] . ' - ' . $project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="section-title">📋 اطلاعات تحویل‌گیرنده</div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>نام تحویل‌گیرنده</label>
                        <input type="text" name="receiver_name"
                               placeholder="نام و نام خانوادگی">
                    </div>
                    
                    <div class="form-group">
                        <label>کد ملی/شماره پرسنلی</label>
                        <input type="text" name="receiver_id_number"
                               placeholder="کد ملی یا شماره پرسنلی">
                    </div>
                    
                    <div class="form-group">
                        <label>مخاطب (اختیاری)</label>
                        <select name="contact_id">
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?php echo $contact['id']; ?>">
                                    <?php echo h($contact['name'] . ($contact['company_name'] ? ' - ' . $contact['company_name'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>دلیل خروج</label>
                        <input type="text" name="reason"
                               placeholder="مثال: مصرف پروژه، انتقال به انبار دیگر، فروش">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>یادداشت</label>
                        <textarea name="notes"></textarea>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="request_approval" id="requestApproval" value="1">
                    <label for="requestApproval">
                        این خروج نیاز به تایید مدیر دارد (در حالت عادی خروج بلافاصله ثبت می‌شود)
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        ✓ ثبت خروج از انبار
                    </button>
                    <button type="submit" name="request_approval" value="1" class="btn btn-warning">
                        📋 ثبت درخواست (نیاز به تایید)
                    </button>
                    <a href="warehouse_items.php" class="btn btn-cancel">انصراف</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updateStockInfo() {
            const itemSelect = document.getElementById('itemSelect');
            const option = itemSelect.options[itemSelect.selectedIndex];
            
            if (option.value) {
                const unit = option.dataset.unit || '-';
                const stock = parseFloat(option.dataset.stock) || 0;
                
                document.getElementById('unitDisplay').textContent = unit;
                document.getElementById('stockDisplay').textContent = stock.toLocaleString('fa-IR');
                document.getElementById('stockInfo').style.display = 'block';
                
                // تغییر رنگ بر اساس موجودی
                const stockInfo = document.getElementById('stockInfo');
                if (stock === 0) {
                    stockInfo.className = 'stock-info danger';
                } else if (stock < 10) {
                    stockInfo.className = 'stock-info low';
                } else {
                    stockInfo.className = 'stock-info';
                }
                
                checkQuantity();
            } else {
                document.getElementById('stockInfo').style.display = 'none';
            }
        }
        
        function checkQuantity() {
            const itemSelect = document.getElementById('itemSelect');
            const option = itemSelect.options[itemSelect.selectedIndex];
            const quantityInput = document.getElementById('quantityInput');
            const warning = document.getElementById('quantityWarning');
            
            if (option.value && quantityInput.value) {
                const stock = parseFloat(option.dataset.stock) || 0;
                const quantity = parseFloat(quantityInput.value) || 0;
                
                if (quantity > stock) {
                    warning.style.display = 'block';
                    quantityInput.style.borderColor = '#f44336';
                } else {
                    warning.style.display = 'none';
                    quantityInput.style.borderColor = '#e0e0e0';
                }
            }
        }
    </script>
</body>
</html>

<?php
// ایجاد جدول تحویل اگر وجود ندارد
$createTableSql = "CREATE TABLE IF NOT EXISTS warehouse_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    receiver_name VARCHAR(200),
    receiver_id_number VARCHAR(20),
    receiver_signature TEXT,
    delivery_date DATETIME,
    delivered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES warehouse_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (delivered_by) REFERENCES users(id),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

db()->query($createTableSql);
?>