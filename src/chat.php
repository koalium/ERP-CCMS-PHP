<?php
/**
 * صفحه چت - خصوصی و گروهی
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

$currentUserId = $_SESSION['user_id'];
$chatType = $_GET['type'] ?? 'private'; // private or group
$userId = (int)($_GET['user_id'] ?? 0);
$groupId = (int)($_GET['group_id'] ?? 0);

// ایجاد گروه جدید
if ($chatType === 'group' && isset($_GET['action']) && $_GET['action'] === 'create') {
    // ایجاد جدول groups اگر وجود نداشت
    db()->query("CREATE TABLE IF NOT EXISTS message_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        created_by INT NOT NULL,
        members TEXT COMMENT 'JSON array of user IDs',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $groupName = sanitize_input($_GET['name'] ?? 'گروه جدید');
    $members = json_encode([$currentUserId]);
    
    $groupId = db()->insert('message_groups', [
        'name' => $groupName,
        'created_by' => $currentUserId,
        'members' => $members
    ]);
    
    redirect(SITE_URL . '/chat.php?type=group&group_id=' . $groupId);
}

// اطلاعات کاربر یا گروه
$chatInfo = null;
if ($chatType === 'private' && $userId > 0) {
    $chatInfo = db()->selectOne(
        "SELECT id, fullname, email, 
         CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online
         FROM users WHERE id = :id",
        [':id' => $userId]
    );
} elseif ($chatType === 'group' && $groupId > 0) {
    $chatInfo = db()->selectOne(
        "SELECT * FROM message_groups WHERE id = :id",
        [':id' => $groupId]
    );
    if ($chatInfo) {
        $chatInfo['members'] = json_decode($chatInfo['members'] ?? '[]', true);
    }
}

if (!$chatInfo) {
    redirect(SITE_URL . '/messenger.php');
}

// دریافت پیام‌ها
$messages = [];
if ($chatType === 'private') {
    $messages = db()->select(
        "SELECT m.*, u.fullname as sender_name
         FROM messages m
         LEFT JOIN users u ON u.id = m.sender_id
         WHERE ((m.sender_id = :user1 AND m.receiver_id = :user2)
                OR (m.sender_id = :user2 AND m.receiver_id = :user1))
         AND m.group_id IS NULL
         ORDER BY m.created_at ASC",
        [':user1' => $currentUserId, ':user2' => $userId]
    );
    
    // علامت‌گذاری به عنوان خوانده شده
    db()->query(
        "UPDATE messages SET is_read = 1, read_at = NOW() 
         WHERE receiver_id = :user_id AND sender_id = :sender_id AND is_read = 0",
        [':user_id' => $currentUserId, ':sender_id' => $userId]
    );
} else {
    $messages = db()->select(
        "SELECT m.*, u.fullname as sender_name
         FROM messages m
         LEFT JOIN users u ON u.id = m.sender_id
         WHERE m.group_id = :group_id
         ORDER BY m.created_at ASC",
        [':group_id' => $groupId]
    );
    
    // علامت‌گذاری به عنوان خوانده شده
    db()->query(
        "UPDATE messages SET is_read = 1, read_at = NOW() 
         WHERE group_id = :group_id AND receiver_id = :user_id AND is_read = 0",
        [':group_id' => $groupId, ':user_id' => $currentUserId]
    );
}

// پردازش ارسال پیام
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    
    if (!empty($message)) {
        $messageData = [
            'sender_id' => $currentUserId,
            'message' => $message
        ];
        
        if ($chatType === 'private') {
            $messageData['receiver_id'] = $userId;
        } else {
            $messageData['group_id'] = $groupId;
            // در گروه، receiver_id null است
            $messageData['receiver_id'] = null;
        }
        
        db()->insert('messages', $messageData);
        
        // بارگذاری مجدد صفحه برای نمایش پیام جدید
        redirect($_SERVER['REQUEST_URI']);
    }
}

$pageTitle = ($chatType === 'private' ? 'چت با ' . $chatInfo['fullname'] : 'گروه: ' . $chatInfo['name']);
require_once 'header.php';
?>

<style>
    .chat-container {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 220px);
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    /* Chat Header */
    .chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .chat-header-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .back-btn {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .back-btn:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .chat-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: white;
        color: #667eea;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 22px;
        position: relative;
    }
    
    .chat-info h2 {
        font-size: 20px;
        margin-bottom: 3px;
    }
    
    .chat-status {
        font-size: 13px;
        opacity: 0.9;
    }
    
    .chat-header-right {
        display: flex;
        gap: 10px;
    }
    
    .header-btn {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .header-btn:hover {
        background: rgba(255,255,255,0.3);
    }
    
    /* Messages Area */
    .messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f8f9fa;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .messages-area::-webkit-scrollbar {
        width: 8px;
    }
    
    .messages-area::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 4px;
    }
    
    .message {
        display: flex;
        gap: 10px;
        animation: messageSlide 0.3s ease-out;
    }
    
    @keyframes messageSlide {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .message.sent {
        flex-direction: row-reverse;
    }
    
    .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        flex-shrink: 0;
    }
    
    .message.sent .message-avatar {
        background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%);
    }
    
    .message-content {
        max-width: 60%;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .message-sender {
        font-size: 12px;
        font-weight: 600;
        color: #667eea;
        padding: 0 5px;
    }
    
    .message.sent .message-sender {
        text-align: left;
        color: #4caf50;
    }
    
    .message-bubble {
        padding: 12px 16px;
        border-radius: 18px;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        word-wrap: break-word;
        line-height: 1.5;
    }
    
    .message.sent .message-bubble {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .message-time {
        font-size: 11px;
        color: #999;
        padding: 0 5px;
    }
    
    .message.sent .message-time {
        text-align: left;
    }
    
    .date-divider {
        text-align: center;
        margin: 20px 0;
    }
    
    .date-divider span {
        background: white;
        padding: 5px 15px;
        border-radius: 15px;
        font-size: 12px;
        color: #999;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    /* Input Area */
    .input-area {
        background: white;
        border-top: 2px solid #f0f0f0;
        padding: 20px 25px;
    }
    
    .input-container {
        display: flex;
        gap: 10px;
        align-items: end;
    }
    
    .input-actions {
        display: flex;
        gap: 5px;
    }
    
    .input-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        background: #f0f0f0;
        color: #667eea;
        cursor: pointer;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .input-btn:hover {
        background: #667eea;
        color: white;
        transform: scale(1.1);
    }
    
    .message-input {
        flex: 1;
        min-height: 45px;
        max-height: 150px;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        font-size: 14px;
        font-family: inherit;
        resize: none;
        overflow-y: auto;
        transition: border-color 0.3s;
    }
    
    .message-input:focus {
        outline: none;
        border-color: #667eea;
    }
    
    .send-btn {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        cursor: pointer;
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .send-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .send-btn:active {
        transform: scale(0.95);
    }
    
    .typing-indicator {
        display: none;
        padding: 10px 20px;
        font-size: 13px;
        color: #999;
        font-style: italic;
    }
    
    .typing-indicator.active {
        display: block;
    }
    
    @media (max-width: 768px) {
        .chat-container {
            height: calc(100vh - 160px);
        }
        
        .chat-header {
            padding: 15px;
        }
        
        .chat-info h2 {
            font-size: 16px;
        }
        
        .message-content {
            max-width: 80%;
        }
        
        .input-area {
            padding: 15px;
        }
    }
</style>

<div class="chat-container">
    <!-- Chat Header -->
    <div class="chat-header">
        <div class="chat-header-left">
            <button class="back-btn" onclick="window.location.href='messenger.php'">⬅️</button>
            
            <div class="chat-avatar">
                <?php 
                if ($chatType === 'private') {
                    echo mb_substr($chatInfo['fullname'], 0, 1);
                    if ($chatInfo['is_online']): ?>
                        <div class="online-indicator"></div>
                    <?php endif;
                } else {
                    echo '👥';
                }
                ?>
            </div>
            
            <div class="chat-info">
                <h2>
                    <?php 
                    echo h($chatType === 'private' ? $chatInfo['fullname'] : $chatInfo['name']); 
                    ?>
                </h2>
                <div class="chat-status">
                    <?php 
                    if ($chatType === 'private') {
                        echo $chatInfo['is_online'] ? 'آنلاین' : 'آفلاین';
                    } else {
                        echo en2fa(count($chatInfo['members'])) . ' عضو';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="chat-header-right">
            <?php if ($chatType === 'group'): ?>
                <button class="header-btn" title="اعضا" onclick="showMembers()">👥</button>
                <button class="header-btn" title="تنظیمات" onclick="groupSettings()">⚙️</button>
            <?php endif; ?>
            <button class="header-btn" title="جستجو" onclick="searchMessages()">🔍</button>
        </div>
    </div>
    
    <!-- Messages Area -->
    <div class="messages-area" id="messagesArea">
        <?php 
        $lastDate = '';
        foreach ($messages as $msg): 
            $msgDate = date('Y-m-d', strtotime($msg['created_at']));
            
            // نمایش تقسیم‌کننده تاریخ
            if ($msgDate !== $lastDate):
                $lastDate = $msgDate;
                $dateLabel = ($msgDate === date('Y-m-d')) ? 'امروز' : en2fa(date('Y/m/d', strtotime($msgDate)));
                ?>
                <div class="date-divider">
                    <span><?php echo $dateLabel; ?></span>
                </div>
            <?php endif;
            
            $isSent = ($msg['sender_id'] == $currentUserId);
            ?>
            
            <div class="message <?php echo $isSent ? 'sent' : 'received'; ?>">
                <div class="message-avatar">
                    <?php echo mb_substr($msg['sender_name'], 0, 1); ?>
                </div>
                
                <div class="message-content">
                    <?php if ($chatType === 'group' && !$isSent): ?>
                        <div class="message-sender"><?php echo h($msg['sender_name']); ?></div>
                    <?php endif; ?>
                    
                    <div class="message-bubble">
                        <?php echo nl2br(h($msg['message'])); ?>
                    </div>
                    
                    <div class="message-time">
                        <?php echo en2fa(date('H:i', strtotime($msg['created_at']))); ?>
                        <?php if ($isSent && $msg['is_read']): ?>
                            ✓✓
                        <?php elseif ($isSent): ?>
                            ✓
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="typing-indicator" id="typingIndicator">
        در حال نوشتن...
    </div>
    
    <!-- Input Area -->
    <div class="input-area">
        <form method="POST" id="messageForm" class="input-container">
            <div class="input-actions">
                <button type="button" class="input-btn" title="ضمیمه فایل" onclick="attachFile()">📎</button>
                <button type="button" class="input-btn" title="ایموجی" onclick="insertEmoji()">😊</button>
            </div>
            
            <textarea class="message-input" 
                      id="messageInput" 
                      name="message" 
                      placeholder="پیام خود را بنویسید..." 
                      rows="1"
                      required></textarea>
            
            <button type="submit" class="send-btn" title="ارسال">➤</button>
        </form>
    </div>
</div>

<script>
    // اسکرول خودکار به آخرین پیام
    const messagesArea = document.getElementById('messagesArea');
    messagesArea.scrollTop = messagesArea.scrollHeight;
    
    // تنظیم ارتفاع خودکار textarea
    const messageInput = document.getElementById('messageInput');
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // ارسال با Enter (Shift+Enter برای خط جدید)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('messageForm').submit();
        }
    });
    
    // توابع کمکی
    function attachFile() {
        alert('امکان ضمیمه فایل در نسخه‌های آینده فعال خواهد شد');
    }
    
    function insertEmoji() {
        const emojis = ['😊', '😂', '❤️', '👍', '🎉', '🔥', '💪', '🙏'];
        const emoji = emojis[Math.floor(Math.random() * emojis.length)];
        messageInput.value += emoji;
        messageInput.focus();
    }
    
    function searchMessages() {
        const search = prompt('جستجو در پیام‌ها:');
        if (search) {
            // پیاده‌سازی جستجو
            alert('جستجو برای: ' + search);
        }
    }
    
    function showMembers() {
        alert('لیست اعضا');
    }
    
    function groupSettings() {
        alert('تنظیمات گروه');
    }
    
    // به‌روزرسانی خودکار پیام‌ها (polling)
    let lastMessageId = <?php echo count($messages) > 0 ? end($messages)['id'] : 0; ?>;
    
    function checkNewMessages() {
        // در نسخه کامل از AJAX استفاده می‌شود
        // این کد فقط برای نمایش ساختار است
        
        // fetch('message_api.php?action=check&last_id=' + lastMessageId)
        //     .then(response => response.json())
        //     .then(data => {
        //         if (data.new_messages > 0) {
        //             location.reload();
        //         }
        //     });
    }
    
    // چک کردن پیام‌های جدید هر 3 ثانیه
    setInterval(checkNewMessages, 3000);
</script>

<?php require_once 'footer.php'; ?>