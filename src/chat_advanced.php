<?php
/**
 * صفحه چت پیشرفته با تمام قابلیت‌ها
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

$currentUserId = $_SESSION['user_id'];
$chatType = $_GET['type'] ?? 'private';
$userId = (int)($_GET['user_id'] ?? 0);
$groupId = (int)($_GET['group_id'] ?? 0);

// اطلاعات کاربر یا گروه
$chatInfo = null;
$members = [];

if ($chatType === 'private' && $userId > 0) {
    $chatInfo = db()->selectOne(
        "SELECT id, fullname, email, 
         CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online,
         last_login
         FROM users WHERE id = :id",
        [':id' => $userId]
    );
} elseif ($chatType === 'group' && $groupId > 0) {
    $chatInfo = db()->selectOne(
        "SELECT * FROM message_groups WHERE id = :id",
        [':id' => $groupId]
    );
    
    if ($chatInfo) {
        $memberIds = json_decode($chatInfo['members'] ?? '[]', true);
        if (!empty($memberIds)) {
            list($inClause, $params) = db()->prepareInClause($memberIds);
            $members = db()->select("SELECT id, fullname FROM users WHERE id IN ($inClause)", $params);
        }
    }
}

if (!$chatInfo) {
    redirect(SITE_URL . '/messenger_advanced.php');
}

// دریافت پیام‌ها
$messages = [];
if ($chatType === 'private') {
    $messages = db()->select(
        "SELECT m.*, u.fullname as sender_name,
         (SELECT COUNT(*) FROM message_reactions WHERE message_id = m.id) as reactions_count
         FROM messages m
         LEFT JOIN users u ON u.id = m.sender_id
         WHERE ((m.sender_id = :user1 AND m.receiver_id = :user2)
                OR (m.sender_id = :user2 AND m.receiver_id = :user1))
         AND m.group_id IS NULL
         AND m.deleted_by NOT LIKE CONCAT('%,', :current_user, ',%')
         ORDER BY m.created_at ASC
         LIMIT 500",
        [':user1' => $currentUserId, ':user2' => $userId, ':current_user' => $currentUserId]
    );
    
    // علامت‌گذاری به عنوان خوانده شده
    db()->query(
        "UPDATE messages SET is_read = 1, read_at = NOW() 
         WHERE receiver_id = :user_id AND sender_id = :sender_id AND is_read = 0",
        [':user_id' => $currentUserId, ':sender_id' => $userId]
    );
} else {
    $messages = db()->select(
        "SELECT m.*, u.fullname as sender_name,
         (SELECT COUNT(*) FROM message_reactions WHERE message_id = m.id) as reactions_count
         FROM messages m
         LEFT JOIN users u ON u.id = m.sender_id
         WHERE m.group_id = :group_id
         AND m.deleted_by NOT LIKE CONCAT('%,', :current_user, ',%')
         ORDER BY m.created_at ASC
         LIMIT 500",
        [':group_id' => $groupId, ':current_user' => $currentUserId]
    );
}

$pageTitle = ($chatType === 'private' ? 'چت با ' . $chatInfo['fullname'] : 'گروه: ' . $chatInfo['name']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --sent-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --received-bg: white;
            --bg-chat: #e8eaf6;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', 'Vazir', Arial, sans-serif;
            background: var(--bg-chat);
            overflow: hidden;
            height: 100vh;
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: white;
        }
        
        /* Chat Header */
        .chat-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }
        
        .chat-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 0;
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
            flex-shrink: 0;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        
        .chat-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            position: relative;
            flex-shrink: 0;
            cursor: pointer;
        }
        
        .chat-info {
            flex: 1;
            min-width: 0;
            cursor: pointer;
        }
        
        .chat-info h2 {
            font-size: 18px;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-status {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .chat-header-right {
            display: flex;
            gap: 8px;
        }
        
        .header-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 38px;
            height: 38px;
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
            transform: scale(1.1);
        }
        
        /* Messages Area */
        .messages-wrapper {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: var(--bg-chat);
        }
        
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            scroll-behavior: smooth;
        }
        
        .messages-area::-webkit-scrollbar {
            width: 8px;
        }
        
        .messages-area::-webkit-scrollbar-thumb {
            background: #bbb;
            border-radius: 4px;
        }
        
        .message {
            display: flex;
            gap: 10px;
            animation: messageSlide 0.3s ease-out;
            position: relative;
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
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
            font-size: 16px;
        }
        
        .message.sent .message-avatar {
            background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%);
        }
        
        .message-content {
            max-width: 65%;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .message-sender {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            padding: 0 5px;
        }
        
        .message.sent .message-sender {
            text-align: left;
            color: #4caf50;
        }
        
        .message-bubble {
            padding: 10px 14px;
            border-radius: 18px;
            background: var(--received-bg);
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            word-wrap: break-word;
            line-height: 1.5;
            position: relative;
        }
        
        .message.sent .message-bubble {
            background: var(--sent-bg);
            color: white;
        }
        
        .message-bubble.reply {
            border-right: 4px solid var(--primary);
            padding-right: 18px;
        }
        
        .message.sent .message-bubble.reply {
            border-right: none;
            border-left: 4px solid rgba(255,255,255,0.5);
            padding-left: 18px;
            padding-right: 14px;
        }
        
        .reply-to {
            background: rgba(0,0,0,0.05);
            padding: 8px 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .message.sent .reply-to {
            background: rgba(255,255,255,0.2);
        }
        
        .message-file {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(0,0,0,0.05);
            border-radius: 12px;
            margin-top: 6px;
            cursor: pointer;
        }
        
        .message.sent .message-file {
            background: rgba(255,255,255,0.2);
        }
        
        .file-icon {
            font-size: 32px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .file-size {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .message-image {
            max-width: 100%;
            border-radius: 12px;
            cursor: pointer;
            margin-top: 6px;
        }
        
        .message-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            padding: 0 5px;
        }
        
        .message-time {
            font-size: 10px;
            color: #999;
        }
        
        .message.sent .message-time {
            text-align: left;
            color: rgba(255,255,255,0.8);
        }
        
        .message-actions {
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .message:hover .message-actions {
            opacity: 1;
        }
        
        .msg-action-btn {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            background: rgba(0,0,0,0.1);
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .message.sent .msg-action-btn {
            background: rgba(255,255,255,0.2);
        }
        
        .msg-action-btn:hover {
            transform: scale(1.2);
        }
        
        .message-reactions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        
        .reaction {
            padding: 3px 8px;
            background: rgba(0,0,0,0.05);
            border-radius: 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .reaction:hover {
            transform: scale(1.1);
        }
        
        .message.sent .reaction {
            background: rgba(255,255,255,0.2);
        }
        
        .date-divider {
            text-align: center;
            margin: 15px 0;
        }
        
        .date-divider span {
            background: white;
            padding: 6px 16px;
            border-radius: 16px;
            font-size: 12px;
            color: #666;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .typing-indicator {
            display: none;
            padding: 15px 20px;
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        
        .typing-indicator.active {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .typing-dots {
            display: flex;
            gap: 4px;
        }
        
        .typing-dots span {
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.7;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
        
        /* Input Area */
        .input-area {
            background: white;
            border-top: 2px solid #f0f0f0;
            padding: 15px 20px;
        }
        
        .reply-preview {
            display: none;
            padding: 10px 15px;
            background: #f5f5f5;
            border-radius: 12px 12px 0 0;
            margin-bottom: 10px;
            position: relative;
        }
        
        .reply-preview.active {
            display: block;
        }
        
        .reply-preview-content {
            font-size: 13px;
            color: #666;
        }
        
        .reply-preview-close {
            position: absolute;
            top: 10px;
            left: 10px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #999;
        }
        
        .input-container {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
        .input-actions {
            display: flex;
            gap: 6px;
        }
        
        .input-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: #f0f0f0;
            color: var(--primary);
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .input-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }
        
        .message-input {
            flex: 1;
            min-height: 42px;
            max-height: 120px;
            padding: 11px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 21px;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            overflow-y: auto;
            transition: border-color 0.3s;
        }
        
        .message-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            cursor: pointer;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .send-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .send-btn:active {
            transform: scale(0.95);
        }
        
        /* Emoji Picker */
        .emoji-picker {
            display: none;
            position: absolute;
            bottom: 70px;
            right: 80px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            padding: 15px;
            max-width: 320px;
            z-index: 1000;
        }
        
        .emoji-picker.active {
            display: block;
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .emoji-picker h4 {
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 5px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .emoji-item {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .emoji-item:hover {
            background: #f0f0f0;
            transform: scale(1.2);
        }
        
        /* Context Menu */
        .context-menu {
            display: none;
            position: fixed;
            background: white;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            padding: 8px 0;
            min-width: 180px;
            z-index: 2000;
        }
        
        .context-menu.active {
            display: block;
        }
        
        .context-menu-item {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }
        
        .context-menu-item:hover {
            background: #f5f5f5;
        }
        
        .context-menu-item.danger {
            color: #f44336;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: bold;
            color: var(--dark);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .chat-header {
                padding: 12px 15px;
            }
            
            .chat-info h2 {
                font-size: 16px;
            }
            
            .message-content {
                max-width: 80%;
            }
            
            .input-area {
                padding: 12px 15px;
            }
            
            .emoji-picker {
                right: 20px;
                max-width: calc(100% - 40px);
            }
        }
    </style>
</head>
<body>

<div class="chat-container">
    <!-- Chat Header -->
    <div class="chat-header">
        <div class="chat-header-left">
            <button class="back-btn" onclick="window.location.href='messenger_advanced.php'">⬅️</button>
            
            <div class="chat-avatar" onclick="showProfile()">
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
            
            <div class="chat-info" onclick="showProfile()">
                <h2>
                    <?php 
                    echo h($chatType === 'private' ? $chatInfo['fullname'] : $chatInfo['name']); 
                    ?>
                </h2>
                <div class="chat-status">
                    <?php 
                    if ($chatType === 'private') {
                        if ($chatInfo['is_online']) {
                            echo 'آنلاین';
                        } else {
                            $lastSeen = strtotime($chatInfo['last_login']);
                            $diff = time() - $lastSeen;
                            if ($diff < 3600) {
                                echo 'آخرین بازدید: ' . en2fa(floor($diff / 60)) . ' دقیقه پیش';
                            } else {
                                echo 'آخرین بازدید: ' . en2fa(date('Y/m/d H:i', $lastSeen));
                            }
                        }
                    } else {
                        echo en2fa(count($members)) . ' عضو';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="chat-header-right">
            <button class="header-btn" title="جستجو" onclick="openSearch()">🔍</button>
            <button class="header-btn" title="تماس صوتی" onclick="startVoiceCall()">📞</button>
            <button class="header-btn" title="تماس تصویری" onclick="startVideoCall()">📹</button>
            <button class="header-btn" title="وایت‌برد" onclick="openWhiteboard()">🎨</button>
            <button class="header-btn" title="منو" onclick="showChatMenu()">⋮</button>
        </div>
    </div>
    
    <!-- Messages Area -->
    <div class="messages-wrapper">
        <div class="messages-area" id="messagesArea">
            <?php 
            $lastDate = '';
            foreach ($messages as $msg): 
                $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                
                if ($msgDate !== $lastDate):
                    $lastDate = $msgDate;
                    $dateLabel = ($msgDate === date('Y-m-d')) ? 'امروز' : (($msgDate === date('Y-m-d', strtotime('-1 day'))) ? 'دیروز' : en2fa(date('Y/m/d', strtotime($msgDate))));
                    ?>
                    <div class="date-divider">
                        <span><?php echo $dateLabel; ?></span>
                    </div>
                <?php endif;
                
                $isSent = ($msg['sender_id'] == $currentUserId);
                $attachments = json_decode($msg['attachments'] ?? '[]', true);
                ?>
                
                <div class="message <?php echo $isSent ? 'sent' : 'received'; ?>" 
                     data-message-id="<?php echo $msg['id']; ?>"
                     oncontextmenu="showContextMenu(event, <?php echo $msg['id']; ?>, <?php echo $isSent ? 'true' : 'false'; ?>)">
                    <div class="message-avatar">
                        <?php echo mb_substr($msg['sender_name'], 0, 1); ?>
                    </div>
                    
                    <div class="message-content">
                        <?php if ($chatType === 'group' && !$isSent): ?>
                            <div class="message-sender"><?php echo h($msg['sender_name']); ?></div>
                        <?php endif; ?>
                        
                        <div class="message-bubble <?php echo $msg['reply_to'] ? 'reply' : ''; ?>">
                            <?php if ($msg['reply_to']): ?>
                                <div class="reply-to" onclick="scrollToMessage(<?php echo $msg['reply_to']; ?>)">
                                    ↩️ پاسخ به پیام
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($msg['is_edited']): ?>
                                <small style="opacity: 0.7;">ویرایش شده</small>
                            <?php endif; ?>
                            
                            <?php echo nl2br(h($msg['message'])); ?>
                            
                            <?php if (!empty($attachments)): ?>
                                <?php foreach ($attachments as $file): ?>
                                    <?php if (in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif'])): ?>
                                        <img src="<?php echo h($file['url']); ?>" 
                                             class="message-image" 
                                             onclick="openImageViewer('<?php echo h($file['url']); ?>')">
                                    <?php else: ?>
                                        <div class="message-file" onclick="downloadFile('<?php echo h($file['url']); ?>')">
                                            <div class="file-icon">📄</div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo h($file['name']); ?></div>
                                                <div class="file-size"><?php echo h($file['size']); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="message-footer">
                            <div class="message-time">
                                <?php echo en2fa(date('H:i', strtotime($msg['created_at']))); ?>
                                <?php if ($isSent): ?>
                                    <?php if ($msg['is_read']): ?>
                                        <span style="color: #4caf50;">✓✓</span>
                                    <?php else: ?>
                                        <span>✓</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-actions">
                                <button class="msg-action-btn" title="ریپلای" onclick="replyToMessage(<?php echo $msg['id']; ?>, '<?php echo h(mb_substr($msg['message'], 0, 30)); ?>')">↩️</button>
                                <button class="msg-action-btn" title="فوروارد" onclick="forwardMessage(<?php echo $msg['id']; ?>)">➡️</button>
                                <button class="msg-action-btn" title="ری‌اکشن" onclick="showReactions(<?php echo $msg['id']; ?>)">❤️</button>
                                <?php if ($isSent): ?>
                                    <button class="msg-action-btn" title="ویرایش" onclick="editMessage(<?php echo $msg['id']; ?>, '<?php echo h($msg['message']); ?>')">✏️</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($msg['reactions_count'] > 0): ?>
                            <div class="message-reactions">
                                <div class="reaction">❤️ <?php echo en2fa($msg['reactions_count']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="typing-indicator" id="typingIndicator">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span>در حال نوشتن...</span>
        </div>
    </div>
    
    <!-- Input Area -->
    <div class="input-area">
        <div class="reply-preview" id="replyPreview">
            <button class="reply-preview-close" onclick="cancelReply()">✕</button>
            <div class="reply-preview-content" id="replyPreviewContent"></div>
        </div>
        
        <form method="POST" id="messageForm" class="input-container">
            <input type="hidden" name="reply_to" id="replyToInput">
            <input type="hidden" name="edit_message_id" id="editMessageId">
            
            <div class="input-actions">
                <button type="button" class="input-btn" title="ضمیمه" onclick="document.getElementById('fileInput').click()">📎</button>
                <input type="file" id="fileInput" style="display: none;" multiple onchange="handleFileSelect(event)">
                
                <button type="button" class="input-btn" title="ایموجی" onclick="toggleEmoji()">😊</button>
                <button type="button" class="input-btn" title="ضبط صدا" onclick="startVoiceRecord()">🎤</button>
            </div>
            
            <textarea class="message-input" 
                      id="messageInput" 
                      name="message" 
                      placeholder="پیام خود را بنویسید..." 
                      rows="1"
                      oninput="handleTyping()"
                      required></textarea>
            
            <button type="submit" class="send-btn" title="ارسال">➤</button>
        </form>
    </div>
</div>

<!-- Emoji Picker -->
<div class="emoji-picker" id="emojiPicker">
    <h4>انتخاب ایموجی</h4>
    <div class="emoji-grid">
        <?php
        $emojis = ['😊','😂','❤️','👍','🎉','🔥','💪','🙏','😍','🤔','😎','🥰','😢','😭','🤣','😅','😇','🤗','😘','💯','✨','🌟','⭐','🎈','🎁','🎂','🎊','🎵','🎶','👏','🤝','💼','📱','💻','📧','📞','✅','❌','⚠️','📌','🔔','🔕','🔒','🔓','⏰','📅','📊','📈','📉','💡','🔍','📝'];
        foreach ($emojis as $emoji) {
            echo "<div class='emoji-item' onclick=\"insertEmoji('$emoji')\">$emoji</div>";
        }
        ?>
    </div>
</div>

<!-- Context Menu -->
<div class="context-menu" id="contextMenu">
    <div class="context-menu-item" onclick="copyMessage()">📋 کپی</div>
    <div class="context-menu-item" onclick="replyToMessageFromMenu()">↩️ پاسخ</div>
    <div class="context-menu-item" onclick="forwardMessageFromMenu()">➡️ فوروارد</div>
    <div class="context-menu-item" onclick="pinMessage()">📌 پین کردن</div>
    <div class="context-menu-item" id="editMenuItem" style="display: none;" onclick="editMessageFromMenu()">✏️ ویرایش</div>
    <div class="context-menu-item danger" id="deleteMenuItem" style="display: none;" onclick="deleteMessage()">🗑️ حذف</div>
</div>

<script src="chat_functions.js"></script>

<?php require_once 'footer.php'; ?>