<?php
/**
 * فرم دستور کار - افزودن/ویرایش/مشاهده
 * Work Order Form
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$action = $_GET['action'] ?? 'add';
$workOrderId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$workOrder = null;

// چک مجوز
$canWrite = check_permission('production', PERMISSION_WRITE);
$canManage = check_permission('production', PERMISSION_FULL);

if ($action === 'view' || $action === 'edit') {
    if (!$workOrderId) {
        redirect(SITE_URL . '/work_orders.php');
    }
    
    $workOrder = db()->selectOne(
        "SELECT wo.*, p.title as project_title, pr.name as product_name, pr.code as product_code
         FROM work_orders wo
         LEFT JOIN projects p ON p.id = wo.project_id
         LEFT JOIN products pr ON pr.id = wo.product_id
         WHERE wo.id = :id",
        [':id' => $workOrderId]
    );
    
    if (!$workOrder) {
        redirect(SITE_URL . '/work_orders.php');
    }
}

// ذخیره دستور کار
if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($action === 'add' || $action === 'edit') && $canWrite) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً مجدداً تلاش کنید.';
    } else {
        $data = [
            'title' => sanitize_input($_POST['title']),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'project_id' => (int)($_POST['project_id'] ?? 0) ?: null,
            'product_id' => (int)($_POST['product_id'] ?? 0) ?: null,
            'quantity' => (float)($_POST['quantity'] ?? 0),
            'unit' => sanitize_input($_POST['unit'] ?? ''),
            'priority' => sanitize_input($_POST['priority']),
            'status' => sanitize_input($_POST['status']),
            'start_date' => sanitize_input($_POST['start_date'] ?? ''),
            'due_date' => sanitize_input($_POST['due_date'] ?? ''),
            'assigned_to' => (int)($_POST['assigned_to'] ?? 0) ?: null,
            'estimated_hours' => (float)($_POST['estimated_hours'] ?? 0),
            'notes' => sanitize_input($_POST['notes'] ?? ''),
            'specifications' => sanitize_input($_POST['specifications'] ?? '')
        ];
        
        // اعتبارسنجی
        if (empty($data['title']) || empty($data['priority']) || empty($data['status'])) {
            $error = 'عنوان، اولویت و وضعیت الزامی است.';
        } elseif ($data['quantity'] <= 0) {
            $error = 'تعداد باید بیشتر از صفر باشد.';
        } else {
            db()->beginTransaction();
            
            try {
                if ($action === 'add') {
                    // تولید شماره دستور کار
                    $lastNumber = db()->selectOne(
                        "SELECT work_order_number FROM work_orders ORDER BY id DESC LIMIT 1"
                    );
                    
                    if ($lastNumber) {
                        $num = (int)substr($lastNumber['work_order_number'], 3) + 1;
                    } else {
                        $num = 1;
                    }
                    
                    $data['work_order_number'] = 'WO-' . str_pad($num, 5, '0', STR_PAD_LEFT);
                    $data['created_by'] = $_SESSION['user_id'];
                    $data['progress'] = 0;
                    
                    $workOrderId = db()->insert('work_orders', $data);
                    $logAction = 'add_work_order';
                } else {
                    db()->update('work_orders', $data, 'id = :id', [':id' => $workOrderId]);
                    $logAction = 'edit_work_order';
                }
                
                // ثبت لاگ
                db()->insert('logs', [
                    'user_id' => $_SESSION['user_id'],
                    'action' => $logAction,
                    'module' => 'production',
                    'record_id' => $workOrderId,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                db()->commit();
                
                redirect(SITE_URL . '/work_orders.php?msg=saved');
            } catch (Exception $e) {
                db()->rollback();
                $error = 'خطا در ذخیره اطلاعات: ' . $e->getMessage();
            }
        }
    }
}

// دریافت لیست پروژه‌های فعال
$projects = db()->select("SELECT id, code, title FROM projects WHERE status = 'active' ORDER BY title");

// دریافت لیست محصولات
$products = db()->select("SELECT id, code, name FROM products WHERE status = 'active' ORDER BY name");

// دریافت لیست کاربران
$users = db()->select("SELECT id, fullname FROM users WHERE is_active = 1 ORDER BY fullname");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        echo $action === 'add' ? 'دستور کار جدید' : 
             ($action === 'edit' ? 'ویرایش دستور کار' : 'مشاهده دستور کار');
    ?> - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1200px