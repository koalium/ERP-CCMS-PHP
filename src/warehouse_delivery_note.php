<?php
/**
 * بارنامه و رسید تحویل کالا با امکان ثبت امضای دیجیتال
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'jalali-converter.php';

check_login();

if (!check_permission('warehouse', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

$transaction_id = (int)($_GET['id'] ?? 0);

if (!$transaction_id) {
    die('شماره تراکنش نامعتبر است.');
}

// دریافت اطلاعات تراکنش
$transaction = db()->selectOne("
    SELECT wt.*, 
           w.name as warehouse_name, w.code as warehouse_code, w.location as warehouse_location,
           wi.name as item_name, wi.code as item_code, wi.unit,
           c.name as contact_name, c.company_name,
           p.code as project_code, p.title as project_title,
           u.fullname as requested_by_name,
           u2.fullname as approved_by_name,
           wd.receiver_name, wd.receiver_id_number, wd.receiver_signature, wd.delivery_date
    FROM warehouse_transactions wt
    JOIN warehouses w ON w.id = wt.warehouse_id
    JOIN warehouse_items wi ON wi.id = wt.item_id
    LEFT JOIN contacts c ON c.id = wt.contact_id
    LEFT JOIN projects p ON p.id = wt.project_id
    LEFT JOIN users u ON u.id = wt.requested_by
    LEFT JOIN users u2 ON u2.id = wt.approved_by
    LEFT JOIN warehouse_deliveries wd ON wd.transaction_id = wt.id
    WHERE wt.id = :id
", [':id' => $transaction_id]);

if (!$transaction) {
    die('تراکنش مورد نظر یافت نشد.');
}

// اگر امضا ثبت نشده و این درخواست POST است
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signature'])) {
    $signature = $_POST['signature'];
    $receiver_name = sanitize_input($_POST['receiver_name']);
    $receiver_id = sanitize_input($_POST['receiver_id']);
    
    // ذخیره یا به‌روزرسانی اطلاعات تحویل
    $deliveryExists = db()->exists('warehouse_deliveries', 'transaction_id = :id', [':id' => $transaction_id]);
    
    if ($deliveryExists) {
        db()->update('warehouse_deliveries', [
            'receiver_name' => $receiver_name,
            'receiver_id_number' => $receiver_id,
            'receiver_signature' => $signature,
            'delivery_date' => date('Y-m-d H:i:s')
        ], 'transaction_id = :id', [':id' => $transaction_id]);
    } else {
        db()->insert('warehouse_deliveries', [
            'transaction_id' => $transaction_id,
            'receiver_name' => $receiver_name,
            'receiver_id_number' => $receiver_id,
            'receiver_signature' => $signature,
            'delivery_date' => date('Y-m-d H:i:s'),
            'delivered_by' => $_SESSION['user_id']
        ]);
    }
    
    // بارگذاری مجدد
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $transaction_id);
    exit;
}

$hasSignature = !empty($transaction['receiver_signature']);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بارنامه و رسید تحویل - <?php echo SITE_TITLE; ?></title>
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
            padding: 20px;
        }
        
        .actions {
            text-align: center;
            margin-bottom: 20px;
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
            margin: 0 5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .delivery-note {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .header-section {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header-section h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header-section .doc-number {
            font-size: 18px;
            color: #666;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
        }
        
        .info-box h3 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-row .label {
            font-weight: bold;
            width: 120px;
            color: #666;
        }
        
        .info-row .value {
            flex: 1;
            color: #2c3e50;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: right;
            border: 1px solid #e0e0e0;
        }
        
        .items-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        
        .items-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .signature-section {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .signature-box {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .signature-box h4 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .signature-canvas-container {
            border: 2px dashed #667eea;
            border-radius: 8px;
            margin: 15px 0;
            background: #f9f9f9;
        }
        
        #signatureCanvas {
            display: block;
            cursor: crosshair;
        }
        
        .signature-image {
            max-width: 100%;
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 10px;
            background: #f9f9f9;
        }
        
        .signature-controls {
            margin-top: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
            text-align: right;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #666;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .actions, .signature-controls, .btn {
                display: none !important;
            }
            
            .delivery-note {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()" class="btn btn-primary">🖨️ چاپ</button>
        <?php if (!$hasSignature && $transaction['type'] === 'out'): ?>
            <button onclick="showSignatureForm()" class="btn btn-success">✍️ ثبت امضای دریافت</button>
        <?php endif; ?>
        <a href="warehouse_transactions.php" class="btn btn-secondary">بازگشت</a>
    </div>
    
    <div class="delivery-note">
        <div class="header-section">
            <h1>📦 بارنامه و رسید تحویل کالا</h1>
            <div class="doc-number">
                شماره: <strong><?php echo en2fa($transaction['id']); ?></strong>
                | تاریخ: <strong><?php echo en2fa(formatJalaliDate($transaction['transaction_date'], 'Y/m/d')); ?></strong>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-box">
                <h3>🏢 اطلاعات انبار</h3>
                <div class="info-row">
                    <div class="label">نام انبار:</div>
                    <div class="value"><?php echo h($transaction['warehouse_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">کد انبار:</div>
                    <div class="value"><?php echo h($transaction['warehouse_code']); ?></div>
                </div>
                <?php if ($transaction['warehouse_location']): ?>
                <div class="info-row">
                    <div class="label">آدرس:</div>
                    <div class="value"><?php echo h($transaction['warehouse_location']); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="label">نوع تراکنش:</div>
                    <div class="value">
                        <?php
                        $types = ['in' => 'ورود', 'out' => 'خروج', 'transfer' => 'جابجایی'];
                        echo $types[$transaction['type']] ?? $transaction['type'];
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <h3>👤 اطلاعات درخواست‌کننده</h3>
                <div class="info-row">
                    <div class="label">درخواست‌کننده:</div>
                    <div class="value"><?php echo h($transaction['requested_by_name']); ?></div>
                </div>
                <?php if ($transaction['contact_name']): ?>
                <div class="info-row">
                    <div class="label">مخاطب:</div>
                    <div class="value"><?php echo h($transaction['contact_name']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($transaction['project_code']): ?>
                <div class="info-row">
                    <div class="label">پروژه:</div>
                    <div class="value"><?php echo h($transaction['project_code'] . ' - ' . $transaction['project_title']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($transaction['reference_number']): ?>
                <div class="info-row">
                    <div class="label">شماره مرجع:</div>
                    <div class="value"><?php echo h($transaction['reference_number']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>کد کالا</th>
                    <th>نام کالا</th>
                    <th>مقدار</th>
                    <th>واحد</th>
                    <?php if ($transaction['unit_price']): ?>
                    <th>قیمت واحد</th>
                    <th>قیمت کل</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo h($transaction['item_code']); ?></td>
                    <td><strong><?php echo h($transaction['item_name']); ?></strong></td>
                    <td><strong style="font-size: 16px;"><?php echo en2fa(number_format($transaction['quantity'], 2)); ?></strong></td>
                    <td><?php echo h($transaction['unit']); ?></td>
                    <?php if ($transaction['unit_price']): ?>
                    <td><?php echo en2fa(number_format($transaction['unit_price'], 0)); ?></td>
                    <td><strong><?php echo en2fa(number_format($transaction['total_price'], 0)); ?></strong></td>
                    <?php endif; ?>
                </tr>
            </tbody>
        </table>
        
        <?php if ($transaction['notes']): ?>
        <div class="info-box" style="margin-bottom: 20px;">
            <h3>📝 یادداشت</h3>
            <p><?php echo nl2br(h($transaction['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="signature-section">
            <div class="signature-box">
                <h4>👤 تحویل‌دهنده</h4>
                <div class="info-row" style="justify-content: center; margin: 20px 0;">
                    <strong><?php echo h($transaction['approved_by_name'] ?: $transaction['requested_by_name']); ?></strong>
                </div>
                <div style="border-top: 2px solid #e0e0e0; padding-top: 10px; margin-top: 50px;">
                    امضا و تاریخ
                </div>
            </div>
            
            <div class="signature-box">
                <h4>👤 تحویل‌گیرنده</h4>
                
                <?php if ($hasSignature): ?>
                    <div class="info-row">
                        <div class="label">نام:</div>
                        <div class="value"><?php echo h($transaction['receiver_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="label">کد ملی:</div>
                        <div class="value"><?php echo en2fa($transaction['receiver_id_number']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="label">تاریخ:</div>
                        <div class="value"><?php echo en2fa(formatJalaliDate($transaction['delivery_date'], 'Y/m/d H:i')); ?></div>
                    </div>
                    <img src="<?php echo h($transaction['receiver_signature']); ?>" class="signature-image" alt="امضا">
                <?php else: ?>
                    <div id="signatureFormContainer" style="display: none;">
                        <form method="POST" id="signatureForm">
                            <div class="form-group">
                                <label>نام و نام خانوادگی:</label>
                                <input type="text" name="receiver_name" required>
                            </div>
                            <div class="form-group">
                                <label>کد ملی:</label>
                                <input type="text" name="receiver_id" pattern="[0-9]{10}" required>
                            </div>
                            <div class="signature-canvas-container">
                                <canvas id="signatureCanvas" width="400" height="200"></canvas>
                            </div>
                            <div class="signature-controls">
                                <button type="button" onclick="clearSignature()" class="btn btn-secondary">🗑️ پاک کردن</button>
                                <button type="button" onclick="saveSignature()" class="btn btn-success">✓ ثبت امضا</button>
                            </div>
                            <input type="hidden" name="signature" id="signatureData">
                        </form>
                    </div>
                    <div style="border-top: 2px solid #e0e0e0; padding-top: 10px; margin-top: 50px;">
                        امضا و تاریخ
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer">
            <p>© <?php echo date('Y'); ?> eSmartis - سیستم یکپارچه مدیریت سازمان</p>
            <p>طراحی و توسعه: Ashkarian.r</p>
        </div>
    </div>
    
    <script>
        let canvas, ctx, isDrawing = false;
        
        function initCanvas() {
            canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;
            
            ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);
            
            // Touch support
            canvas.addEventListener('touchstart', handleTouch);
            canvas.addEventListener('touchmove', handleTouch);
            canvas.addEventListener('touchend', stopDrawing);
        }
        
        function startDrawing(e) {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            ctx.beginPath();
            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }
        
        function draw(e) {
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            ctx.stroke();
        }
        
        function stopDrawing() {
            isDrawing = false;
        }
        
        function handleTouch(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 'mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        }
        
        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        
        function saveSignature() {
            const signatureData = canvas.toDataURL('image/png');
            document.getElementById('signatureData').value = signatureData;
            document.getElementById('signatureForm').submit();
        }
        
        function showSignatureForm() {
            document.getElementById('signatureFormContainer').style.display = 'block';
            initCanvas();
        }
    </script>
</body>
</html>