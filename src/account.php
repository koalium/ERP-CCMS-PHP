<?php
/**
 * فرم افزودن/ویرایش حساب مالی
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('financial', PERMISSION_READ)) {
    die('شما مجوز دسترسی ندارید.');
}

$action = $_GET['action'] ?? 'add';
$accountId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$error = '';
$account = null;

// بارگذاری حساب
if (in_array($action, ['edit', 'view', 'delete']) && $accountId) {
    $account = db()->selectOne("SELECT * FROM accounts WHERE id = :id", [':id' => $accountId]);
    if (!$account) die('حساب یافت نشد.');
}

// دریافت لیست مخاطبین
$contacts = db()->select("SELECT id, name, company_name FROM contacts WHERE is_active = 1 ORDER BY name LIMIT 100");

// حذف
if ($action === 'delete' && $account && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_permission('financial', PERMISSION_FULL)) {
        die('شما مجوز حذف ندارید.');
    }
    
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        db()->update('accounts', ['is_active' => 0], 'id = :id', [':id' => $accountId]);
        redirect(SITE_URL . '/accounts.php?msg=deleted');
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
            'type' => sanitize_input($_POST['type'] ?? 'bank'),
            'name' => sanitize_input($_POST['name'] ?? ''),
            'account_number' => sanitize_input($_POST['account_number'] ?? ''),
            'iban' => sanitize_input($_POST['iban'] ?? ''),
            'shaba' => sanitize_input($_POST['shaba'] ?? ''),
            'bank_name' => sanitize_input($_POST['bank_name'] ?? ''),
            'currency' => sanitize_input($_POST['currency'] ?? 'IRR'),
            'balance' => (float)($_POST['balance'] ?? 0),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'owner_contact_id' => (int)($_POST['owner_contact_id'] ?? 0) ?: null,
            'description' => sanitize_input($_POST['description'] ?? '')
        ];
        
        if (empty($data['name'])) {
            $error = 'نام حساب الزامی است';
        } else {
            if ($action === 'add') {
                $newId = db()->insert('accounts', $data);
                
                if ($newId) {
                    redirect(SITE_URL . '/accounts.php?msg=added');
                } else {
                    $error = 'خطا در ذخیره';
                }
            } elseif ($action === 'edit') {
                db()->update('accounts', $data, 'id = :id', [':id' => $accountId]);
                redirect(SITE_URL . '/accounts.php?msg=updated');
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
    <title><?php echo $action === 'add' ? 'حساب جدید' : 'ویرایش حساب'; ?> - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
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
            font-size: 15px;
            transition: all 0.3s;
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
            border-color: #11998e;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(17, 153, 142, 0.5);
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
                <h1>💰 <?php echo $action === 'add' ? 'حساب جدید' : ($action === 'edit' ? 'ویرایش حساب' : 'مشاهده حساب'); ?></h1>
                <a href="accounts.php" class="btn-back">↩️ بازگشت</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert">⚠️ <?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($action === 'delete'): ?>
                <div class="alert">
                    ⚠️ آیا از حذف این حساب اطمینان دارید؟
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger" style="background: #f44336;">🗑️ تایید حذف</button>
                        <a href="accounts.php" class="btn btn-secondary">انصراف</a>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-section">
                        <div class="section-title">📋 اطلاعات اصلی</div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>نوع حساب</label>
                                <select name="type" id="accountType" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                    <option value="bank" <?php echo ($account['type'] ?? 'bank') === 'bank' ? 'selected' : ''; ?>>🏦 بانکی</option>
                                    <option value="cash" <?php echo ($account['type'] ?? '') === 'cash' ? 'selected' : ''; ?>>💵 نقدی</option>
                                    <option value="wallet" <?php echo ($account['type'] ?? '') === 'wallet' ? 'selected' : ''; ?>>👛 کیف پول</option>
                                    <option value="custom" <?php echo ($account['type'] ?? '') === 'custom' ? 'selected' : ''; ?>>📋 سایر</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>نام حساب *</label>
                                <input type="text" name="name" required 
                                       value="<?php echo h($account['name'] ?? ''); ?>"
                                       placeholder="نام حساب"
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>واحد پول</label>
                                <select name="currency" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                    <option value="IRR" <?php echo ($account['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال (IRR)</option>
                                    <option value="USD" <?php echo ($account['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار (USD)</option>
                                    <option value="EUR" <?php echo ($account['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو (EUR)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>موجودی اولیه</label>
                                <input type="number" name="balance" step="0.01"
                                       value="<?php echo h($account['balance'] ?? '0'); ?>"
                                       placeholder="0.00"
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>دسته‌بندی</label>
                                <input type="text" name="category"
                                       value="<?php echo h($account['category'] ?? ''); ?>"
                                       placeholder="عملیاتی، سرمایه‌گذاری، ..."
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>مالک حساب</label>
                                <select name="owner_contact_id" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?php echo $contact['id']; ?>"
                                                <?php echo ($account['owner_contact_id'] ?? 0) == $contact['id'] ? 'selected' : ''; ?>>
                                            <?php echo h($contact['name']); ?>
                                            <?php echo $contact['company_name'] ? ' (' . h($contact['company_name']) . ')' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section" id="bankFields" style="display: none;">
                        <div class="section-title">🏦 اطلاعات بانکی</div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>نام بانک</label>
                                <input type="text" name="bank_name"
                                       value="<?php echo h($account['bank_name'] ?? ''); ?>"
                                       placeholder="ملی، ملت، پارسیان، ..."
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>شماره حساب</label>
                                <input type="text" name="account_number"
                                       value="<?php echo h($account['account_number'] ?? ''); ?>"
                                       placeholder="0000000000"
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>شماره شبا</label>
                                <input type="text" name="shaba"
                                       value="<?php echo h($account['shaba'] ?? ''); ?>"
                                       placeholder="IR000000000000000000000000"
                                       maxlength="26"
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>IBAN</label>
                                <input type="text" name="iban"
                                       value="<?php echo h($account['iban'] ?? ''); ?>"
                                       placeholder="برای حساب‌های خارجی"
                                       <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">📝 توضیحات</div>
                        
                        <div class="form-group full-width">
                            <textarea name="description" rows="4" 
                                      placeholder="توضیحات تکمیلی..."
                                      <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($account['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <?php if ($action !== 'view'): ?>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '➕ ثبت حساب' : '💾 به‌روزرسانی'; ?>
                            </button>
                            <a href="accounts.php" class="btn btn-secondary">انصراف</a>
                        </div>
                    <?php else: ?>
                        <div class="form-actions">
                            <a href="account.php?action=edit&id=<?php echo $account['id']; ?>" class="btn btn-primary">
                                ✏️ ویرایش
                            </a>
                            <a href="transactions.php?account_id=<?php echo $account['id']; ?>" class="btn btn-primary">
                                📊 تراکنش‌ها
                            </a>
                            <a href="accounts.php" class="btn btn-secondary">بازگشت</a>
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const accountType = document.getElementById('accountType');
            const bankFields = document.getElementById('bankFields');
            
            function toggleBankFields() {
                if (accountType && bankFields) {
                    bankFields.style.display = accountType.value === 'bank' ? 'block' : 'none';
                }
            }
            
            if (accountType) {
                accountType.addEventListener('change', toggleBankFields);
                toggleBankFields();
            }
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>