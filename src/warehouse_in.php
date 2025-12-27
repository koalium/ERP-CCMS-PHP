<?php
/**
 * ثبت ورود کالا به انبار
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
        $warehouse_id = (int)$_POST['warehouse_id'];
        $item_id = (int)$_POST['item_id'];
        $quantity = (float)$_POST['quantity'];
        $unit_price = (float)($_POST['unit_price'] ?? 0);
        $transaction_date = sanitize_input($_POST['transaction_date']);
        $reference_number = sanitize_input($_POST['reference_number']);
        $contact_id = (int)($_POST['contact_id'] ?? 0) ?: null;
        $project_id = (int)($_POST['project_id'] ?? 0) ?: null;
        $reason = sanitize_input($_POST['reason']);
        $notes = sanitize_input($_POST['notes']);
        
        // اعتبارسنجی
        if (empty($warehouse_id) || empty($item_id) || $quantity <= 0 || empty($transaction_date)) {
            $error = 'لطفاً تمام فیلدهای الزامی را پر کنید.';
        } else {
            // تبدیل تاریخ جلالی به میلادی
            $transaction_date_g = jalaliToGregorianDate($transaction_date);
            if (!$transaction_date_g) {
                $error = 'فرمت تاریخ نامعتبر است.';
            } else {
                db()->beginTransaction();
                
                try {
                    $transactionData = [
                        'type' => 'in',
                        'status' => 'completed',
                        'warehouse_id' => $warehouse_id,
                        'item_id' => $item_id,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total_price' => $quantity * $unit_price,
                        'reference_number' => $reference_number,
                        'contact_id' => $contact_id,
                        'project_id' => $project_id,
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
                    
                    // به‌روزرسانی موجودی کالا
                    db()->query("
                        UPDATE warehouse_items 
                        SET current_stock = current_stock + :quantity 
                        WHERE id = :item_id
                    ", [':quantity' => $quantity, ':item_id' => $item_id]);
                    
                    db()->commit();
                    
                    // ثبت لاگ
                    db()->insert('logs', [
                        'user_id' => $_SESSION['user_id'],
                        'action' => 'warehouse_in',
                        'module' => 'warehouse',
                        'record_id' => $transactionId,
                        'new_data' => json_encode($transactionData, JSON_UNESCAPED_UNICODE),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    $success = 'ورود کالا با موفقیت ثبت شد. شماره تراکنش: ' . $transactionId;
                    
                    // پاک کردن فرم
                    $_POST = [];
                    
                } catch (Exception $e) {
                    db()->rollback();
                    $error = 'خطا در ثبت ورود: ' . $e->getMessage();
                }
            }
        }
    }
}

// دریافت انبارها
$warehouses = db()->select("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name");

// دریافت کالاها
$items = db()->select("SELECT id, code, name, unit, unit_price, currency FROM warehouse_items WHERE is_active = 1 ORDER BY name");

// دریافت مخاطبین (تامین‌کنندگان)
$contacts = db()->select("
    SELECT id, name, company_name 
    FROM contacts 
    WHERE is_active = 1 AND (is_vendor = 1 OR type = 'company')
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
    <title>ثبت ورود به انبار - <?php echo SITE_TITLE; ?></title>
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            border-color: #4caf50;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
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
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
            color: white;
            font-size: 16px;
            padding: 12px 30px;
        }
        
        .btn-cancel {
            background: #e0e0e0;
            color: #666;
        }
        
        .info-box {
            background: #e8f5e9;
            border-right: 4px solid #4caf50;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        
        .calculation-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .calculation-box .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .calculation-box .row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: #2e7d32;
        }
        
        .stock-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 6px;
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
            <h1>📥 ثبت ورود کالا به انبار</h1>
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
            <p>برای ثبت ورود کالا به انبار، ابتدا انبار مقصد و کالا را انتخاب کرده، سپس مقدار و سایر اطلاعات را وارد کنید.</p>
            <p>پس از ثبت، موجودی کالا به صورت خودکار به‌روزرسانی می‌شود.</p>
        </div>
        
        <div class="form-container">
            <form method="POST" action="" id="incomingForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>انبار مقصد <span class="required">*</span></label>
                        <select name="warehouse_id" required>
                            <option value="">انتخاب انبار</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse['id']; ?>"
                                        <?php echo ($_POST['warehouse_id'] ?? '') == $warehouse['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($warehouse['name'] . ' (' . $warehouse['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>کالا <span class="required">*</span></label>
                        <select name="item_id" id="itemSelect" required onchange="updateItemInfo()">
                            <option value="">انتخاب کالا</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>"
                                        data-unit="<?php echo h($item['unit']); ?>"
                                        data-price="<?php echo $item['unit_price']; ?>"
                                        data-currency="<?php echo h($item['currency']); ?>"
                                        <?php echo ($_POST['item_id'] ?? '') == $item['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($item['code'] . ' - ' . $item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="stock-info" id="stockInfo" style="display: none;">
                            واحد: <span id="unitDisplay">-</span> | 
                            قیمت واحد: <span id="priceDisplay">-</span> <span id="currencyDisplay"></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>مقدار/تعداد <span class="required">*</span></label>
                        <input type="number" name="quantity" id="quantityInput" 
                               step="0.01" min="0.01" required
                               value="<?php echo h($_POST['quantity'] ?? ''); ?>"
                               oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>قیمت واحد (اختیاری)</label>
                        <input type="number" name="unit_price" id="priceInput" 
                               step="0.01" min="0"
                               value="<?php echo h($_POST['unit_price'] ?? ''); ?>"
                               oninput="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ ورود <span class="required">*</span></label>
                        <input type="text" name="transaction_date" class="jalali-date-input"
                               value="<?php echo h($_POST['transaction_date'] ?? $today_jalali); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>شماره مرجع (فاکتور/حواله)</label>
                        <input type="text" name="reference_number"
                               value="<?php echo h($_POST['reference_number'] ?? ''); ?>"
                               placeholder="شماره فاکتور یا حواله">
                    </div>
                    
                    <div class="form-group">
                        <label>تامین‌کننده (اختیاری)</label>
                        <select name="contact_id">
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?php echo $contact['id']; ?>"
                                        <?php echo ($_POST['contact_id'] ?? '') == $contact['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($contact['name'] . ($contact['company_name'] ? ' - ' . $contact['company_name'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>پروژه (اختیاری)</label>
                        <select name="project_id">
                            <option value="">بدون پروژه</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"
                                        <?php echo ($_POST['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($project['code'] . ' - ' . $project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>دلیل ورود</label>
                        <input type="text" name="reason"
                               value="<?php echo h($_POST['reason'] ?? ''); ?>"
                               placeholder="مثال: خرید، برگشت از پروژه، انتقال از انبار دیگر">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>یادداشت</label>
                        <textarea name="notes"><?php echo h($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="calculation-box" id="calculationBox" style="display: none;">
                    <div class="row">
                        <span>مقدار:</span>
                        <span id="calcQuantity">-</span>
                    </div>
                    <div class="row">
                        <span>قیمت واحد:</span>
                        <span id="calcPrice">-</span>
                    </div>
                    <div class="row">
                        <span>جمع کل:</span>
                        <span id="calcTotal">-</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        ✓ ثبت ورود به انبار
                    </button>
                    <a href="warehouse_items.php" class="btn btn-cancel">انصراف</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updateItemInfo() {
            const select = document.getElementById('itemSelect');
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                document.getElementById('unitDisplay').textContent = option.dataset.unit;
                document.getElementById('priceDisplay').textContent = parseFloat(option.dataset.price || 0).toLocaleString('fa-IR');
                document.getElementById('currencyDisplay').textContent = option.dataset.currency;
                document.getElementById('priceInput').value = option.dataset.price || '';
                document.getElementById('stockInfo').style.display = 'block';
                
                calculateTotal();
            } else {
                document.getElementById('stockInfo').style.display = 'none';
                document.getElementById('calculationBox').style.display = 'none';
            }
        }
        
        function calculateTotal() {
            const quantity = parseFloat(document.getElementById('quantityInput').value) || 0;
            const price = parseFloat(document.getElementById('priceInput').value) || 0;
            
            if (quantity > 0 && price > 0) {
                const total = quantity * price;
                const select = document.getElementById('itemSelect');
                const option = select.options[select.selectedIndex];
                const unit = option.dataset.unit || '';
                const currency = option.dataset.currency || 'IRR';
                
                document.getElementById('calcQuantity').textContent = quantity.toLocaleString('fa-IR') + ' ' + unit;
                document.getElementById('calcPrice').textContent = price.toLocaleString('fa-IR') + ' ' + currency;
                document.getElementById('calcTotal').textContent = total.toLocaleString('fa-IR') + ' ' + currency;
                document.getElementById('calculationBox').style.display = 'block';
            } else {
                document.getElementById('calculationBox').style.display = 'none';
            }
        }
        
        // مقداردهی اولیه
        if (document.getElementById('itemSelect').value) {
            updateItemInfo();
        }
    </script>
</body>
</html>