<?php
/**
 * فرم افزودن/ویرایش پیشنهاد
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('marketing', PERMISSION_READ)) {
    die('شما مجوز دسترسی ندارید.');
}

$action = $_GET['action'] ?? 'add';
$proposalId = $_GET['id'] ?? null;
$tenderId = $_GET['tender_id'] ?? null;
$userId = $_SESSION['user_id'];
$error = '';
$proposal = null;

// بارگذاری پیشنهاد
if (in_array($action, ['edit', 'view', 'delete']) && $proposalId) {
    $proposal = db()->selectOne("SELECT * FROM proposals WHERE id = :id", [':id' => $proposalId]);
    if (!$proposal) die('پیشنهاد یافت نشد.');
    $tenderId = $proposal['tender_id'];
}

// بارگذاری مناقصه
$tender = $tenderId ? db()->selectOne("SELECT * FROM tenders WHERE id = :id", [':id' => $tenderId]) : null;

// دریافت لیست مناقصات برای انتخاب
$tenders = db()->select("SELECT id, tender_number, title FROM tenders ORDER BY created_at DESC LIMIT 50");

// حذف
if ($action === 'delete' && $proposal && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_permission('marketing', PERMISSION_FULL)) {
        die('شما مجوز حذف ندارید.');
    }
    
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        db()->delete('proposals', 'id = :id', [':id' => $proposalId]);
        redirect(SITE_URL . '/proposals.php?msg=deleted');
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!check_permission('marketing', PERMISSION_WRITE)) {
        die('شما مجوز ویرایش ندارید.');
    }
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی';
    } else {
        $data = [
            'proposal_number' => sanitize_input($_POST['proposal_number'] ?? ''),
            'tender_id' => (int)($_POST['tender_id'] ?? 0),
            'type' => sanitize_input($_POST['type'] ?? 'technical'),
            'title' => sanitize_input($_POST['title'] ?? ''),
            'status' => sanitize_input($_POST['status'] ?? 'draft'),
            'total_price' => (float)($_POST['total_price'] ?? 0),
            'currency' => sanitize_input($_POST['currency'] ?? 'IRR'),
            'validity_days' => (int)($_POST['validity_days'] ?? 0),
            'delivery_time_days' => (int)($_POST['delivery_time_days'] ?? 0),
            'payment_terms' => sanitize_input($_POST['payment_terms'] ?? ''),
            'technical_specs' => sanitize_input($_POST['technical_specs'] ?? ''),
            'content' => sanitize_input($_POST['content'] ?? ''),
            'submitted_date' => sanitize_input($_POST['submitted_date'] ?? '')
        ];
        
        if (empty($data['proposal_number']) || empty($data['title'])) {
            $error = 'شماره و عنوان پیشنهاد الزامی است';
        } elseif (!$data['tender_id']) {
            $error = 'انتخاب مناقصه الزامی است';
        } else {
            if ($action === 'add') {
                $data['prepared_by'] = $userId;
                $newId = db()->insert('proposals', $data);
                
                if ($newId) {
                    redirect(SITE_URL . '/proposals.php?tender_id=' . $data['tender_id'] . '&msg=added');
                } else {
                    $error = 'خطا در ذخیره';
                }
            } elseif ($action === 'edit') {
                db()->update('proposals', $data, 'id = :id', [':id' => $proposalId]);
                redirect(SITE_URL . '/proposals.php?tender_id=' . $data['tender_id'] . '&msg=updated');
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
    <title><?php echo $action === 'add' ? 'پیشنهاد جدید' : 'ویرایش پیشنهاد'; ?> - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .tender-info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
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
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>📝 <?php echo $action === 'add' ? 'پیشنهاد جدید' : 'ویرایش پیشنهاد'; ?></h1>
                <a href="proposals.php<?php echo $tenderId ? '?tender_id=' . $tenderId : ''; ?>" class="btn-back">
                    ↩️ بازگشت
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert">⚠️ <?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($tender): ?>
                <div class="tender-info-box">
                    <h3>📋 مناقصه: <?php echo h($tender['title']); ?></h3>
                    <p>شماره: <?php echo h($tender['tender_number']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-section">
                    <div class="section-title">📋 اطلاعات اصلی</div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>شماره پیشنهاد *</label>
                            <input type="text" name="proposal_number" required 
                                   value="<?php echo h($proposal['proposal_number'] ?? ''); ?>"
                                   placeholder="PRO-001">
                        </div>
                        
                        <div class="form-group">
                            <label>نوع پیشنهاد</label>
                            <select name="type">
                                <option value="technical" <?php echo ($proposal['type'] ?? 'technical') === 'technical' ? 'selected' : ''; ?>>🔧 فنی</option>
                                <option value="financial" <?php echo ($proposal['type'] ?? '') === 'financial' ? 'selected' : ''; ?>>💰 مالی</option>
                                <option value="combined" <?php echo ($proposal['type'] ?? '') === 'combined' ? 'selected' : ''; ?>>📋 ترکیبی</option>
                                <option value="final" <?php echo ($proposal['type'] ?? '') === 'final' ? 'selected' : ''; ?>>✅ نهایی</option>
                            </select>
                        </div>
                        
                        <?php if (!$tenderId): ?>
                            <div class="form-group full-width">
                                <label>انتخاب مناقصه *</label>
                                <select name="tender_id" required>
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($tenders as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" 
                                                <?php echo ($proposal['tender_id'] ?? 0) == $t['id'] ? 'selected' : ''; ?>>
                                            <?php echo h($t['tender_number']); ?> - <?php echo h($t['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="tender_id" value="<?php echo $tenderId; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group full-width">
                            <label>عنوان پیشنهاد *</label>
                            <input type="text" name="title" required 
                                   value="<?php echo h($proposal['title'] ?? ''); ?>"
                                   placeholder="عنوان کامل پیشنهاد">
                        </div>
                        
                        <div class="form-group">
                            <label>وضعیت</label>
                            <select name="status">
                                <option value="draft" <?php echo ($proposal['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>📝 پیش‌نویس</option>
                                <option value="review" <?php echo ($proposal['status'] ?? '') === 'review' ? 'selected' : ''; ?>>👀 در حال بررسی</option>
                                <option value="submitted" <?php echo ($proposal['status'] ?? '') === 'submitted' ? 'selected' : ''; ?>>📤 ارسال شده</option>
                                <option value="accepted" <?php echo ($proposal['status'] ?? '') === 'accepted' ? 'selected' : ''; ?>>✅ پذیرفته شده</option>
                                <option value="rejected" <?php echo ($proposal['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>❌ رد شده</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>تاریخ ارسال</label>
                            <input type="text" name="submitted_date" class="jalali-date"
                                   value="<?php echo h($proposal['submitted_date'] ?? ''); ?>"
                                   placeholder="۱۴۰۴/۰۱/۰۱">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">💰 اطلاعات مالی</div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>مبلغ کل</label>
                            <input type="number" name="total_price" step="0.01"
                                   value="<?php echo h($proposal['total_price'] ?? ''); ?>"
                                   placeholder="0">
                        </div>
                        
                        <div class="form-group">
                            <label>واحد پول</label>
                            <select name="currency">
                                <option value="IRR" <?php echo ($proposal['currency'] ?? 'IRR') === 'IRR' ? 'selected' : ''; ?>>ریال</option>
                                <option value="USD" <?php echo ($proposal['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>دلار</option>
                                <option value="EUR" <?php echo ($proposal['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>یورو</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>اعتبار پیشنهاد (روز)</label>
                            <input type="number" name="validity_days"
                                   value="<?php echo h($proposal['validity_days'] ?? ''); ?>"
                                   placeholder="30">
                        </div>
                        
                        <div class="form-group">
                            <label>زمان تحویل (روز)</label>
                            <input type="number" name="delivery_time_days"
                                   value="<?php echo h($proposal['delivery_time_days'] ?? ''); ?>"
                                   placeholder="60">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>شرایط پرداخت</label>
                            <textarea name="payment_terms" rows="3" 
                                      placeholder="شرایط پرداخت..."><?php echo h($proposal['payment_terms'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-title">🔧 مشخصات فنی و محتوا</div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>مشخصات فنی</label>
                            <textarea name="technical_specs" rows="5" 
                                      placeholder="مشخصات فنی پیشنهاد..."><?php echo h($proposal['technical_specs'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>محتوای پیشنهاد</label>
                            <textarea name="content" rows="8" 
                                      placeholder="محتوای کامل پیشنهاد..."><?php echo h($proposal['content'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'add' ? '➕ ثبت پیشنهاد' : '💾 به‌روزرسانی'; ?>
                    </button>
                    <a href="proposals.php<?php echo $tenderId ? '?tender_id=' . $tenderId : ''; ?>" 
                       class="btn btn-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initJalaliDatePicker('.jalali-date');
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>