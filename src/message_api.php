<?php
/**
 * API مدیریت پیام‌ها
 * برای استفاده با AJAX
 */

require_once 'config.php';
require_once 'dbc.php';

// تنظیم header برای JSON
header('Content-Type: application/json; charset=utf-8');

// چک کردن ورود
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// توابع API
switch ($action) {
    case 'send':
        sendMessage();
        break;
    
    case 'check':
        checkNewMessages();
        break;
    
    case 'get_messages':
        getMessages();
        break;
    
    case 'mark_read':
        markAsRead();
        break;
    
    case 'upload_file':
        uploadFile();
        break;
    
    case 'search':
        searchMessages();
        break;
    
    case 'create_group':
        createGroup();
        break;
    
    case 'add_member':
        addMember();
        break;
    
    case 'remove_member':
        removeMember();
        break;
    
    case 'get_online_users':
        getOnlineUsers();
        break;
    
    case 'typing':
        setTyping();
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * ارسال پیام جدید
 */
function sendMessage() {
    global $currentUserId;
    
    $message = trim($_POST['message'] ?? '');
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $groupId = (int)($_POST['group_id'] ?? 0);
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'پیام نمی‌تواند خالی باشد']);
        return;
    }
    
    $data = [
        'sender_id' => $currentUserId,
        'message' => $message
    ];
    
    if ($groupId > 0) {
        $data['group_id'] = $groupId;
        $data['receiver_id'] = null;
    } elseif ($receiverId > 0) {
        $data['receiver_id'] = $receiverId;
        $data['group_id'] = null;
    } else {
        echo json_encode(['success' => false, 'error' => 'گیرنده مشخص نشده']);
        return;
    }
    
    $messageId = db()->insert('messages', $data);
    
    if ($messageId) {
        // دریافت اطلاعات پیام ارسال شده
        $sentMessage = db()->selectOne(
            "SELECT m.*, u.fullname as sender_name 
             FROM messages m
             LEFT JOIN users u ON u.id = m.sender_id
             WHERE m.id = :id",
            [':id' => $messageId]
        );
        
        // ثبت لاگ
        db()->insert('logs', [
            'user_id' => $currentUserId,
            'action' => 'send_message',
            'module' => 'messenger',
            'record_id' => $messageId,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'message' => $sentMessage
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در ارسال پیام']);
    }
}

/**
 * چک کردن پیام‌های جدید
 */
function checkNewMessages() {
    global $currentUserId;
    
    $lastId = (int)($_GET['last_id'] ?? 0);
    $userId = (int)($_GET['user_id'] ?? 0);
    $groupId = (int)($_GET['group_id'] ?? 0);
    
    $sql = "SELECT COUNT(*) as new_count FROM messages WHERE id > :last_id";
    $params = [':last_id' => $lastId];
    
    if ($groupId > 0) {
        $sql .= " AND group_id = :group_id";
        $params[':group_id'] = $groupId;
    } elseif ($userId > 0) {
        $sql .= " AND ((sender_id = :user1 AND receiver_id = :user2) 
                       OR (sender_id = :user2 AND receiver_id = :user1))";
        $params[':user1'] = $currentUserId;
        $params[':user2'] = $userId;
    } else {
        $sql .= " AND receiver_id = :user_id";
        $params[':user_id'] = $currentUserId;
    }
    
    $result = db()->selectOne($sql, $params);
    
    echo json_encode([
        'success' => true,
        'new_messages' => (int)($result['new_count'] ?? 0)
    ]);
}

/**
 * دریافت پیام‌ها
 */
function getMessages() {
    global $currentUserId;
    
    $userId = (int)($_GET['user_id'] ?? 0);
    $groupId = (int)($_GET['group_id'] ?? 0);
    $lastId = (int)($_GET['last_id'] ?? 0);
    $limit = min(50, (int)($_GET['limit'] ?? 20));
    
    if ($groupId > 0) {
        $sql = "SELECT m.*, u.fullname as sender_name
                FROM messages m
                LEFT JOIN users u ON u.id = m.sender_id
                WHERE m.group_id = :group_id";
        $params = [':group_id' => $groupId];
    } elseif ($userId > 0) {
        $sql = "SELECT m.*, u.fullname as sender_name
                FROM messages m
                LEFT JOIN users u ON u.id = m.sender_id
                WHERE ((m.sender_id = :user1 AND m.receiver_id = :user2)
                       OR (m.sender_id = :user2 AND m.receiver_id = :user1))
                AND m.group_id IS NULL";
        $params = [':user1' => $currentUserId, ':user2' => $userId];
    } else {
        echo json_encode(['success' => false, 'error' => 'پارامترهای نامعتبر']);
        return;
    }
    
    if ($lastId > 0) {
        $sql .= " AND m.id > :last_id";
        $params[':last_id'] = $lastId;
    }
    
    $sql .= " ORDER BY m.created_at ASC LIMIT :limit";
    $params[':limit'] = $limit;
    
    $stmt = db()->query($sql, $params);
    $messages = $stmt ? $stmt->fetchAll() : [];
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
}

/**
 * علامت‌گذاری به عنوان خوانده شده
 */
function markAsRead() {
    global $currentUserId;
    
    $messageIds = $_POST['message_ids'] ?? [];
    
    if (empty($messageIds)) {
        echo json_encode(['success' => false, 'error' => 'پیامی مشخص نشده']);
        return;
    }
    
    list($inClause, $params) = db()->prepareInClause($messageIds);
    $params[':user_id'] = $currentUserId;
    
    $sql = "UPDATE messages 
            SET is_read = 1, read_at = NOW() 
            WHERE id IN ($inClause) 
            AND receiver_id = :user_id 
            AND is_read = 0";
    
    $updated = db()->query($sql, $params);
    
    echo json_encode([
        'success' => true,
        'updated' => $updated ? $updated->rowCount() : 0
    ]);
}

/**
 * آپلود فایل
 */
function uploadFile() {
    global $currentUserId;
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'فایلی انتخاب نشده']);
        return;
    }
    
    $file = $_FILES['file'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/zip'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'نوع فایل مجاز نیست']);
        return;
    }
    
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'حجم فایل بیش از حد مجاز است']);
        return;
    }
    
    $uploadDir = UPLOAD_DIR . '/messages/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = time() . '_' . $currentUserId . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode([
            'success' => true,
            'file_name' => $fileName,
            'file_url' => SITE_URL . '/uploads/messages/' . $fileName,
            'file_size' => $file['size']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در آپلود فایل']);
    }
}

/**
 * جستجو در پیام‌ها
 */
function searchMessages() {
    global $currentUserId;
    
    $query = trim($_GET['query'] ?? '');
    $userId = (int)($_GET['user_id'] ?? 0);
    $groupId = (int)($_GET['group_id'] ?? 0);
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'عبارت جستجو خالی است']);
        return;
    }
    
    $sql = "SELECT m.*, u.fullname as sender_name
            FROM messages m
            LEFT JOIN users u ON u.id = m.sender_id
            WHERE m.message LIKE :query";
    $params = [':query' => '%' . $query . '%'];
    
    if ($groupId > 0) {
        $sql .= " AND m.group_id = :group_id";
        $params[':group_id'] = $groupId;
    } elseif ($userId > 0) {
        $sql .= " AND ((m.sender_id = :user1 AND m.receiver_id = :user2)
                       OR (m.sender_id = :user2 AND m.receiver_id = :user1))";
        $params[':user1'] = $currentUserId;
        $params[':user2'] = $userId;
    } else {
        $sql .= " AND (m.sender_id = :user_id OR m.receiver_id = :user_id)";
        $params[':user_id'] = $currentUserId;
    }
    
    $sql .= " ORDER BY m.created_at DESC LIMIT 50";
    
    $messages = db()->select($sql, $params);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
}

/**
 * ایجاد گروه جدید
 */
function createGroup() {
    global $currentUserId;
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $members = $_POST['members'] ?? [$currentUserId];
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'نام گروه الزامی است']);
        return;
    }
    
    if (!in_array($currentUserId, $members)) {
        $members[] = $currentUserId;
    }
    
    $groupId = db()->insert('message_groups', [
        'name' => $name,
        'description' => $description,
        'created_by' => $currentUserId,
        'members' => json_encode($members)
    ]);
    
    if ($groupId) {
        echo json_encode([
            'success' => true,
            'group_id' => $groupId,
            'message' => 'گروه با موفقیت ایجاد شد'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در ایجاد گروه']);
    }
}

/**
 * افزودن عضو به گروه
 */
function addMember() {
    global $currentUserId;
    
    $groupId = (int)($_POST['group_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($groupId <= 0 || $userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'پارامترهای نامعتبر']);
        return;
    }
    
    $group = db()->selectOne("SELECT * FROM message_groups WHERE id = :id", [':id' => $groupId]);
    
    if (!$group) {
        echo json_encode(['success' => false, 'error' => 'گروه یافت نشد']);
        return;
    }
    
    $members = json_decode($group['members'], true);
    
    if (!in_array($userId, $members)) {
        $members[] = $userId;
        
        $updated = db()->update('message_groups', [
            'members' => json_encode($members)
        ], 'id = :id', [':id' => $groupId]);
        
        if ($updated !== false) {
            echo json_encode(['success' => true, 'message' => 'عضو با موفقیت اضافه شد']);
        } else {
            echo json_encode(['success' => false, 'error' => 'خطا در افزودن عضو']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'کاربر از قبل عضو گروه است']);
    }
}

/**
 * حذف عضو از گروه
 */
function removeMember() {
    global $currentUserId;
    
    $groupId = (int)($_POST['group_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($groupId <= 0 || $userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'پارامترهای نامعتبر']);
        return;
    }
    
    $group = db()->selectOne("SELECT * FROM message_groups WHERE id = :id", [':id' => $groupId]);
    
    if (!$group) {
        echo json_encode(['success' => false, 'error' => 'گروه یافت نشد']);
        return;
    }
    
    // فقط ایجادکننده می‌تواند اعضا را حذف کند
    if ($group['created_by'] != $currentUserId) {
        echo json_encode(['success' => false, 'error' => 'فقط مدیر گروه می‌تواند اعضا را حذف کند']);
        return;
    }
    
    $members = json_decode($group['members'], true);
    $index = array_search($userId, $members);
    
    if ($index !== false) {
        unset($members[$index]);
        $members = array_values($members); // Re-index array
        
        $updated = db()->update('message_groups', [
            'members' => json_encode($members)
        ], 'id = :id', [':id' => $groupId]);
        
        if ($updated !== false) {
            echo json_encode(['success' => true, 'message' => 'عضو با موفقیت حذف شد']);
        } else {
            echo json_encode(['success' => false, 'error' => 'خطا در حذف عضو']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'کاربر عضو گروه نیست']);
    }
}

/**
 * دریافت لیست کاربران آنلاین
 */
function getOnlineUsers() {
    global $currentUserId;
    
    $users = db()->select(
        "SELECT id, fullname, 
         CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online
         FROM users
         WHERE id != :current_user AND is_active = 1
         ORDER BY is_online DESC, fullname",
        [':current_user' => $currentUserId]
    );
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);
}

/**
 * تنظیم وضعیت "در حال نوشتن"
 */
function setTyping() {
    global $currentUserId;
    
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $groupId = (int)($_POST['group_id'] ?? 0);
    $isTyping = (bool)($_POST['is_typing'] ?? false);
    
    // در نسخه کامل، این اطلاعات در cache ذخیره می‌شود (Redis, Memcached)
    // برای سادگی، فعلاً فقط موفقیت برمی‌گردانیم
    
    echo json_encode([
        'success' => true,
        'user_id' => $currentUserId,
        'is_typing' => $isTyping
    ]);
}
?>