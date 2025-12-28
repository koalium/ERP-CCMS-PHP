<?php
/**
 * عملیات تایید/رد تراکنش‌های مالی
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

if (!check_permission('financial', PERMISSION_FULL)) {
    die('شما مجوز تایید تراکنش‌های مالی را ندارید.');
}

$action = $_GET['action'] ?? '';
$transactionId = (int)($_GET['id'] ?? 0);

if (!in_array($action, ['approve', 'reject']) || $transactionId <= 0) {
    redirect(SITE_URL . '/dashboard.php');
}

// دریافت اطلاعات تراکنش
$transaction = db()->selectOne(
    "SELECT * FROM transactions WHERE id = :id",
    [':id' => $transactionId]
);

if (!$transaction) {
    redirect(SITE_URL . '/dashboard.php');
}

if ($action === 'approve') {
    // تایید تراکنش
    db()->beginTransaction();
    
    try {
        // به‌روزرسانی وضعیت تراکنش
        db()->update('transactions', [
            'status' => 'confirmed',
            'confirmed_by' => $_SESSION['user_id'],
            'confirmed_at' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $transactionId]);
        
        // به‌روزرسانی موجودی حساب‌ها
        if ($transaction['type'] === 'income' && $transaction['to_account_id']) {
            db()->query(
                "UPDATE accounts SET balance = balance + :amount WHERE id = :id",
                [':amount' => $transaction['amount'], ':id' => $transaction['to_account_id']]
            );
        } elseif ($transaction['type'] === 'expense' && $transaction['from_account_id']) {
            db()->query(
                "UPDATE accounts SET balance = balance - :amount WHERE id = :id",
                [':amount' => $transaction['amount'], ':id' => $transaction['from_account_id']]
            );
        } elseif ($transaction['type'] === 'transfer') {
            if ($transaction['from_account_id']) {
                db()->query(
                    "UPDATE accounts SET balance = balance - :amount WHERE id = :id",
                    [':amount' => $transaction['amount'], ':id' => $transaction['from_account_id']]
                );
            }
            if ($transaction['to_account_id']) {
                db()->query(
                    "UPDATE accounts SET balance = balance + :amount WHERE id = :id",
                    [':amount' => $transaction['amount'], ':id' => $transaction['to_account_id']]
                );
            }
        }
        
        // ثبت لاگ
        db()->insert('logs', [
            'user_id' => $_SESSION['user_id'],
            'action' => 'approve_transaction',
            'module' => 'financial',
            'record_id' => $transactionId,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        db()->commit();
        
        // ارسال پیام به ایجادکننده تراکنش
        if ($transaction['created_by'] != $_SESSION['user_id']) {
            db()->insert('messages', [
                'sender_id' => $_SESSION['user_id'],
                'receiver_id' => $transaction['created_by'],
                'message' => 'تراکنش مالی شماره ' . $transaction['reference_number'] . ' به مبلغ ' . number_format($transaction['amount']) . ' ریال تایید شد.'
            ]);
        }
        
        $_SESSION['success_message'] = 'تراکنش با موفقیت تایید شد.';
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("Transaction Approval Error: " . $e->getMessage());
        $_SESSION['error_message'] = 'خطا در تایید تراکنش.';
    }
    
} elseif ($action === 'reject') {
    $reason = sanitize_input($_GET['reason'] ?? 'بدون دلیل');
    
    // رد تراکنش
    db()->update('transactions', [
        'status' => 'cancelled',
        'notes' => ($transaction['notes'] ?? '') . "\n\nرد شده توسط: " . $_SESSION['fullname'] . "\nدلیل: " . $reason . "\nتاریخ: " . date('Y-m-d H:i:s')
    ], 'id = :id', [':id' => $transactionId]);
    
    // ثبت لاگ
    db()->insert('logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'reject_transaction',
        'module' => 'financial',
        'record_id' => $transactionId,
        'old_data' => json_encode(['reason' => $reason]),
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // ارسال پیام به ایجادکننده تراکنش
    if ($transaction['created_by'] != $_SESSION['user_id']) {
        db()->insert('messages', [
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $transaction['created_by'],
            'message' => 'تراکنش مالی شماره ' . $transaction['reference_number'] . ' رد شد.\nدلیل: ' . $reason
        ]);
    }
    
    $_SESSION['success_message'] = 'تراکنش رد شد.';
}

redirect(SITE_URL . '/dashboard.php');
?>