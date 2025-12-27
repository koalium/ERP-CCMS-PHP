<?php
/**
 * فرم افزودن/ویرایش تراکنش مالی
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('financial', PERMISSION_READ)) {
    die('شما مجوز دسترسی ندارید.');
}

$action = $_GET['action'] ?? 'add';
$transId = $_GET['id'] ?? null;
$accountId = $_GET['account_id'] ?? null;
$userId = $_SESSION['user_id'];
$error = '';
$transaction = null;

// بارگذاری تراکنش
if (in_array($action, ['edit', 'view', 'delete']) && $transId) {
    $transaction = db()->selectOne("SELECT * FROM transactions WHERE id = :id", [':id' => $transId]);
    if (!$transaction) die('تراکنش یافت نشد.');
}

// دریافت لیست حساب‌ها
$accounts = db()->select("SELECT id, name, currency, balance FROM accounts WHERE is_active = 1 ORDER BY name");

// دریافت لیست مخاطبین
$contacts = db()->select("SELECT id, name FROM contacts WHERE is_active = 1 ORDER BY name LIMIT 100");

// دریافت لیست پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects ORDER BY created_at DESC LIMIT 50");

// حذف
if ($action === 'delete' && $transaction && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_permission('financial', PERMISSION_FULL)) {
        die('شما مجوز حذف ندارید.');
    }
    
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        db()->beginTransaction();
        
        // بازگشت موجودی حساب‌ها
        if ($transaction['status'] === 'confirmed') {
            if ($transaction['from_account_id']) {
                db()->query("UPDATE accounts SET balance = balance + :amount WHERE id = :id", 
                    [':amount' => $transaction['amount'], ':id' => $transaction['from_account_id']]);
            }
            if ($transaction['to_account_id']) {
                db()->query("UPDATE accounts SET balance = balance - :amount WHERE id = :id", 
                    [':amount' => $transaction['amount'], ':id' => $transaction['to_account_id']]);
            }
        }
        
        db()->delete('transactions', 'id = :id', [':id' => $transId]);
        db()->commit();
        
        redirect(SITE_URL . '/transactions.php?msg=deleted');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!check_permission('financial', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش ندارید.');
    }
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی';
    } else {
        $data = [
            'type' => sanitize_input($_POST['type'] ?? 'transfer'),
            'status' => sanitize_input($_POST['status'] ?? 'draft'),
            'from_account_id' => (int)($_POST['from_account_id'] ?? 0) ?: null,
            'to_account_id' => (int)($_POST['to_account_id'] ?? 0) ?: null,
            'amount' => (float)($_POST['amount'] ?? 0),
            'currency' => sanitize_input($_POST['currency'] ?? 'IRR'),
            'exchange_rate' => (float)($_POST['exchange_rate'] ?? 1),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'purpose' => sanitize_input($_POST['purpose'] ?? ''),
            'check_number' => sanitize_input($_POST['check_number'] ?? ''),
            'check_date' => sanitize_input($_POST['check_date'] ?? ''),
            'reference_number' => sanitize_input($_POST['reference_number'] ?? ''),
            'contact_id' => (int)($_POST['contact_id'] ?? 0) ?: null,
            'project_id' => (int)($_POST['project_id'] ?? 0) ?: null,
            'notes' => sanitize_input($_POST['notes'] ?? ''),
            'transaction_date' => sanitize_input($_POST['transaction_date'] ?? date('Y-m-d H:i:s'))
        ];
        
        // اعتبارسنجی
        if ($data['amount'] <= 0) {
            $error = 'مبلغ باید بیشتر از صفر باشد';
        } elseif (!$data['from_account_id'] && !$data['to_account_id']) {
            $error = 'حداقل یکی از حساب‌ها را انتخاب کنید';
        } else {
            db()->beginTransaction();
            
            try {
                if ($action === 'add') {
                    $data['created_by'] = $userId;
                    $newId = db()->insert('transactions', $data);
                    
                    // به‌روزرسانی موجودی در صورت تایید
                    if ($data['status'] === 'confirmed') {
                        if ($data['from_account_id']) {
                            db()->query("UPDATE accounts SET balance = balance - :amount WHERE id = :id", 
                                [':amount' => $data['amount'], ':id' => $data['from_account_id']]);
                        }
                        if ($data['to_account_id']) {
                            db()->query("UPDATE accounts SET balance = balance + :amount WHERE id = :id", 
                                [':amount' => $data['amount'], ':id' => $data['to_account_id']]);
                        }
                    }
                    
                    db()->commit();
                    redirect(SITE_URL . '/transactions.php?msg=added');
                } elseif ($action === 'edit') {
                    // بازگشت موجودی قبلی در صورت تایید قبلی
                    if ($transaction['status'] === 'confirmed') {
                        if ($transaction['from_account_id']) {
                            db()->query("UPDATE accounts SET balance = balance + :amount WHERE id = :id", 
                                [':amount' => $transaction['amount'], ':id' => $transaction['from_account_id']]);
                        }
                        if ($transaction['to_account_id']) {
                            db()->query("UPDATE accounts SET balance = balance - :amount WHERE id = :id", 
                                [':amount' => $transaction['amount'], ':id' => $transaction['to_account_id']]);
                        }
                    }
                    
                    db()->update('transactions', $data, 'id = :id', [':id' => $transId]);
                    
                    // اعمال موجودی جدید در صورت تایید
                    if ($data['status'] === 'confirmed') {
                        if ($data['from_account_id']) {
                            db()->query("UPDATE accounts SET balance = balance - :amount WHERE id = :id", 
                                [':amount' => $data['amount'], ':id' => $data['from_account_id']]);
                        }
                        if ($data['to_account_id']) {
                            db()->query("UPDATE accounts SET balance = balance + :amount WHERE id = :id", 
                                [':amount' => $data['amount'], ':id' => $data['to_account_id']]);
                        }
                    }
                    
                    db()->commit();
                    redirect(SITE_URL . '/transactions.php?msg=updated');
                }
            } catch (Exception $e) {
                db()->rollback();
                $error = 'خطا در ذخیره: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'add' ? 'تراکنش جدید' : 'ویرایش تراکنش'; ?> - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 4px solid #f0f0f0;
        }
        
        .form-header h1 {
            color: #2c3e50;
            font-size: 30px;
        }
        
        .btn-back {
            padding: 12px 24px;
            background: #f5f5f5;
            color: #333;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert {
            padding: 18px 24px;
            border-radius: 15px;
            margin-bottom: 30px;
            background: #fee;
            color: #c33;
            border: 2px solid #fcc;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 10px;
            color: #333;
            font-weight: bold;
            font-size: 15px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ee0979;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 30px;
            border-top: 4px solid #f0f0f0;
        }
        
        .btn {
            padding: 16px 35px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(238, 9, 121, 0.5);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
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
        <div class="form-card">
            <div class="form-header">
                <h1>📊 <?php echo $action === 'add' ? 'تراکنش جدید' : 'ویرایش تراکنش'; ?></h1>
                <a href="transactions.php" class="btn-back">↩️ بازگشت</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert">⚠️ <?php echo h($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-section">
                    <div class="section-title">📋 اطلاعات اصلی</div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>نوع تراکنش *</label>
                            <select name="type" id="transType" required>
                                <option value="transfer" <?php echo ($transaction['type'] ?? 'transfer') === 'transfer' ? 'selected' : ''; ?>>🔄 انتقال</option>
                                <option value="income" <?php echo ($transaction['type'] ?? '') === 'income' ? 'selected' : ''; ?>>📈 درآمد</option>
                                <option value="expense" <?php echo ($transaction['type'] ?? '') === 'expense' ? 'selected' : ''; ?>>📉 هزینه</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>وضعیت</label>
                            <select name="status">
                                <option value="draft" <?php echo ($transaction['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>📝 پیش‌نویس</option>
                                <option value="pending" <?php echo ($transaction['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>⏳ در انتظار</option>
                                <option value="confirmed" <?php echo ($transaction['status'] ?? '') === 'confirmed' ? 'selected' : ''; ?>>✅ تایید شده</option>
                                <option value="cancelled" <?php echo ($transaction['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>❌ لغو شده</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="fromAccountGroup">
                            <label>از حساب</label>
                            <select name="from_account_id">
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?php echo $acc['id']; ?>"
                                            <?php echo ($transaction['from_account_id'] ?? 0) == $acc['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($acc['name']); ?> (<?php echo h($acc['currency']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="toAccountGroup">
                            <label>به حساب</label>
                            <select name="to_account_id">
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?php echo $acc['id']; ?>"
                                            <?php echo ($transaction['to_account_id'] ?? 0) == $acc['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($acc['name']); ?> (<?php echo h($acc['currency']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>مبلغ *</label>
                            <input type="number" name="amount" step="0.01" required
                                   value="<?php echo h($transaction['amount'] ?? ''); ?>"
                                   placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label>واحد پول</label>
                            <select name="currency">
                                <option value="IRR" <?php echo ($transaction['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال</option>
                                <option value="USD" <?php echo ($transaction['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار</option>
                                <option value="EUR" <?php echo ($transaction['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ تراکنش *</label>
                            <input type="text" name="transaction_date" class="jalali-date" required
                                   value="<?php echo h($transaction['transaction_date'] ?? date('Y-m-d')); ?>"
                                   placeholder="۱۴۰۴/۰۱/۰۱">
                        </div>
                        
                        <div class="form-group">
                            <label>دسته‌بندی</label>
                            <input type="text" name="category"
                                   value="<?php echo h($transaction['category'] ?? ''); ?>"
                                   placeholder="حقوق، خرید، ...">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">🔗 ارتباطات</div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>مخاطب</label>
                            <select name="contact_id">
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($contacts as $contact): ?>
                                    <option value="<?php echo $contact['id']; ?>"
                                            <?php echo ($transaction['contact_id'] ?? 0) == $contact['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($contact['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>پروژه</label>
                            <select name="project_id">
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>"
                                            <?php echo ($transaction['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($project['code']); ?> - <?php echo h($project['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>شماره چک</label>
                            <input type="text" name="check_number"
                                   value="<?php echo h($transaction['check_number'] ?? ''); ?>"
                                   placeholder="در صورت پرداخت با چک">
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ چک</label>
                            <input type="text" name="check_date" class="jalali-date"
                                   value="<?php echo h($transaction['check_date'] ?? ''); ?>"
                                   placeholder="۱۴۰۴/۰۱/۰۱">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>شماره مرجع</label>
                            <input type="text" name="reference_number"
                                   value="<?php echo h($transaction['reference_number'] ?? ''); ?>"
                                   placeholder="شماره پیگیری، شناسه پرداخت، ...">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">📝 توضیحات</div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>شرح تراکنش</label>
                            <textarea name="purpose" rows="3" 
                                      placeholder="شرح و هدف تراکنش..."><?php echo h($transaction['purpose'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>یادداشت‌ها</label>
                            <textarea name="notes" rows="2" 
                                      placeholder="یادداشت‌های داخلی..."><?php echo h($transaction['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'add' ? '➕ ثبت تراکنش' : '💾 به‌روزرسانی'; ?>
                    </button>
                    <a href="transactions.php" class="btn btn-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initJalaliDatePicker('.jalali-date');
            
            const transType = document.getElementById('transType');
            const fromAccountGroup = document.getElementById('fromAccountGroup');
            const toAccountGroup = document.getElementById('toAccountGroup');
            
            function updateAccountFields() {
                const type = transType.value;
                
                if (type === 'income') {
                    fromAccountGroup.style.display = 'none';
                    toAccountGroup.style.display = 'block';
                } else if (type === 'expense') {
                    fromAccountGroup.style.display = 'block';
                    toAccountGroup.style.display = 'none';
                } else {
                    fromAccountGroup.style.display = 'block';
                    toAccountGroup.style.display = 'block';
                }
            }
            
            transType.addEventListener('change', updateAccountFields);
            updateAccountFields();
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>