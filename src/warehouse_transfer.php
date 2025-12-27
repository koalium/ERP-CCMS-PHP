<?php
/**
 * جابجایی کالا بین انبارها
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

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $from_warehouse_id = (int)$_POST['from_warehouse_id'];
        $to_warehouse_id = (int)$_POST['to_warehouse_id'];
        $item_id = (int)$_POST['item_id'];
        $quantity = (float)$_POST['quantity'];
        $transaction_date = sanitize_input($_POST['transaction_date']);
        $reference_number = sanitize_input($_POST['reference_number']);
        $reason = sanitize_input($_POST['reason']);
        $notes = sanitize_input($_POST['notes']);
        
        // اعتبارسنجی
        if (empty($from_warehouse_id) || empty($to_warehouse_id) || empty($item_id) || $quantity <= 0 || empty($transaction_date)) {
            $error = 'لطفاً تمام فیلدهای الزامی را پر کنید.';
        } elseif ($from_warehouse_id === $to_warehouse_id) {
            $error = 'انبار مبدا و مقصد نمی‌توانند یکسان باشند.';
        } else {
            // چک موجودی
            $currentStock = db()->selectOne("
                SELECT COALESCE(SUM(CASE 
                    WHEN type = 'in' AND status = 'completed' THEN quantity
                    WHEN type = 'out' AND status = 'completed' THEN -quantity
                    WHEN type = 'transfer' AND status = 'completed' AND from_warehouse_id = :warehouse_id THEN -quantity
                    WHEN type = 'transfer' AND status = 'completed' AND to_warehouse_id = :warehouse_id THEN quantity
                    ELSE 0
                END), 0) as stock
                FROM warehouse_transactions
                WHERE (warehouse_id = :warehouse_id OR from_warehouse_id = :warehouse_id OR to_warehouse_id = :warehouse_id) 
                AND item_id = :item_id
            ", [':warehouse_id' => $from_warehouse_id, ':item_id' => $item_id]);
            
            if ($currentStock['stock'] < $quantity) {
                $error = 'موجودی کافی در انبار مبدا نیست. موجودی فعلی: ' . en2fa(number_format($currentStock['stock'], 2));
            } else {
                // تبدیل تاریخ جلالی به میلادی
                $transaction_date_g = jalaliToGregorianDate($transaction_date);
                if (!$transaction_date_g) {
                    $error = 'فرمت تاریخ نامعتبر است.';
                } else {
                    db()->beginTransaction();
                    
                    try {
                        $transactionData = [
                            'type' => 'transfer',
                            'status' => 'completed',
                            'warehouse_id' => $from_warehouse_id, // برای ثبت در لیست تراکنش‌های انبار مبدا
                            'item_id' => $item_id,
                            'quantity' => $quantity,
                            'reference_number' => $reference_number,
                            'from_warehouse_id' => $from_warehouse_id,
                            'to_warehouse_id' => $to_warehouse_id,
                            'reason' => $reason,
                            'notes' => $notes,
                            'requested_by' => $_SESSION['user_id'],
                            'approved_by' => $_SESSION['user_id'],
                            'transaction_date' => $transaction_date_g,
                            'approved_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $transactionId = db()->insert('warehouse_transactions', $transactionData);
                        
                        if (!$transactionId) {
                            throw new Exception('خطا در ثبت تراکنش');
                        }
                        
                        db()->commit();
                        
                        // ثبت لاگ
                        db()->insert('logs', [
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'warehouse_transfer',
                            'module' => 'warehouse',
                            'record_id' => $transactionId,
                            'new_data' => json_encode($transactionData, JSON_UNESCAPED_UNICODE),
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        $success = 'جابجایی کالا با موفقیت ثبت شد. شماره تراکنش: ' . $transactionId;
                        
                        // پاک کردن فرم
                        $_POST = [];
                        
                    } catch (Exception $e) {
                        db()->rollback();
                        $error = 'خطا در ثبت جابجایی: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// دریافت انبارها
$warehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");

// دریافت کالاها
$items = db()->select("
    SELECT wi.id, wi.code, wi.name, wi.unit
    FROM warehouse_items wi
    WHERE wi.is_active = 1
    ORDER BY wi.name
");

// تاریخ امروز به شمسی
$today_jalali = jalaliToday();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جابجایی بین انبارها - <?php echo SITE_TITLE; ?></title>
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
        
        .transfer-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 12px;
        }
        
        .warehouse-box {
            flex: 1;
            padding: 20px;
            background: white;
            border: 2px solid #2196f3;
            border-radius: 12px;
            text-align: center;
        }
        
        .warehouse-box.from {
            border-color: #f44336;
        }
        
        .warehouse-box.to {
            border-color: #4caf50;
        }
        
        .warehouse-box h3 {
            margin-bottom: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .warehouse-box .name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .arrow {
            font-size: 48px;
            color: #2196f3;
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
        .form-group select:focus {
            outline: none;
            border-color: #2196f3;
        }
        
        .stock-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 6px;
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
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            color: white;
            font-size: 16px;
            padding: 12px 30px;
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #666;
        }
        
        .info-box {
            background: #e3f2fd;
            border-right: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .transfer-visual {
                flex-direction: column;
            }
            
            .arrow {
                transform: rotate(90deg);
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 جابجایی کالا بین انبارها</h1>
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
            <h3>ℹ️ راهنما</h3>
            <p>برای جابجایی کالا بین دو انبار، ابتدا انبار مبدا و مقصد را انتخاب کرده، سپس کالا و مقدار را وارد کنید.</p>
            <p>موجودی انبار مبدا به صورت خودکار کاهش و موجودی انبار مقصد افزایش می‌یابد.</p>
        </div>
        
        <div class="form-container">
            <form method="POST" action="" id="transferForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="transfer-visual" id="transferVisual" style="display: none;">
                    <div class="warehouse-box from">
                        <h3>از انبار</h3>
                        <div class="name" id="fromWarehouseName">-</div>
                        <div class="stock-info" id="fromStock">موجودی: -</div>
                    </div>
                    <div class="arrow">→</div>
                    <div class="warehouse-box to">
                        <h3>به انبار</h3>
                        <div class="name" id="toWarehouseName">-</div>
                        <div class="stock-info" id="toStock">موجودی: -</div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>از انبار <span class="required">*</span></label>
                        <select name="from_warehouse_id" id="fromWarehouse" required onchange="updateVisual()">
                            <option value="">انتخاب انبار مبدا</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse['id']; ?>"
                                        data-name="<?php echo h($warehouse['name']); ?>">
                                    <?php echo h($warehouse['name'] . ' (' . $warehouse['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>به انبار <span class="required">*</span></label>
                        <select name="to_warehouse_id" id="toWarehouse" required onchange="updateVisual()">
                            <option value="">انتخاب انبار مقصد</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse['id']; ?>"
                                        data-name="<?php echo h($warehouse['name']); ?>">
                                    <?php echo h($warehouse['name'] . ' (' . $warehouse['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>کالا <span class="required">*</span></label>
                        <select name="item_id" id="itemSelect" required onchange="updateVisual()">
                            <option value="">انتخاب کالا</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>"
                                        data-unit="<?php echo h($item['unit']); ?>">
                                    <?php echo h($item['code'] . ' - ' . $item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>مقدار/تعداد <span class="required">*</span></label>
                        <input type="number" name="quantity" id="quantityInput" 
                               step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ جابجایی <span class="required">*</span></label>
                        <input type="text" name="transaction_date" class="jalali-date-input"
                               value="<?php echo $today_jalali; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>شماره مرجع</label>
                        <input type="text" name="reference_number"
                               placeholder="شماره حواله جابجایی">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>دلیل جابجایی</label>
                        <input type="text" name="reason"
                               placeholder="مثال: انتقال به انبار پروژه، تنظیم موجودی">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>یادداشت</label>
                        <textarea name="notes"></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        ✓ ثبت جابجایی
                    </button>
                    <a href="warehouse_items.php" class="btn btn-cancel">انصراف</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updateVisual() {
            const fromSelect = document.getElementById('fromWarehouse');
            const toSelect = document.getElementById('toWarehouse');
            const itemSelect = document.getElementById('itemSelect');
            
            const fromOption = fromSelect.options[fromSelect.selectedIndex];
            const toOption = toSelect.options[toSelect.selectedIndex];
            const itemOption = itemSelect.options[itemSelect.selectedIndex];
            
            if (fromSelect.value && toSelect.value && itemSelect.value) {
                document.getElementById('transferVisual').style.display = 'flex';
                document.getElementById('fromWarehouseName').textContent = fromOption.dataset.name || '-';
                document.getElementById('toWarehouseName').textContent = toOption.dataset.name || '-';
                
                // در واقعیت باید با AJAX موجودی را دریافت کنیم
                document.getElementById('fromStock').textContent = 'موجودی: در حال بررسی...';
                document.getElementById('toStock').textContent = 'موجودی: در حال بررسی...';
            } else {
                document.getElementById('transferVisual').style.display = 'none';
            }
            
            // جلوگیری از انتخاب انبار یکسان
            if (fromSelect.value && toSelect.value && fromSelect.value === toSelect.value) {
                alert('انبار مبدا و مقصد نمی‌توانند یکسان باشند!');
                toSelect.value = '';
                document.getElementById('transferVisual').style.display = 'none';
            }
        }
    </script>
</body>
</html>