<?php
/**
 * سیستم پیام‌رسان پیشرفته eSmartis
 * با قابلیت‌های کامل
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'پیام‌رسان پیشرفته';
require_once 'header.php';

check_login();

$currentUserId = $_SESSION['user_id'];
$currentUser = db()->selectOne("SELECT * FROM users WHERE id = :id", [':id' => $currentUserId]);

// دریافت لیست کاربران
$users = db()->select(
    "SELECT u.id, u.fullname, u.email, u.last_login,
     CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online,
     (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = :user_id AND is_read = 0 AND group_id IS NULL) as unread_count
     FROM users u
     WHERE u.id != :user_id AND u.is_active = 1
     ORDER BY is_online DESC, u.fullname",
    [':user_id' => $currentUserId]
);

// دریافت مکالمات اخیر
$conversations = db()->select(
    "SELECT 
        CASE 
            WHEN m.sender_id = :user_id THEN m.receiver_id
            ELSE m.sender_id
        END as other_user_id,
        u.fullname as other_user_name,
        CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online,
        MAX(m.created_at) as last_message_time,
        (SELECT message FROM messages m2 
         WHERE ((m2.sender_id = :user_id AND m2.receiver_id = other_user_id) 
                OR (m2.sender_id = other_user_id AND m2.receiver_id = :user_id))
         AND m2.group_id IS NULL
         AND m2.deleted_by NOT LIKE CONCAT('%,', :user_id, ',%')
         ORDER BY m2.created_at DESC LIMIT 1) as last_message,
        (SELECT is_pinned FROM messages m2 
         WHERE ((m2.sender_id = :user_id AND m2.receiver_id = other_user_id) 
                OR (m2.sender_id = other_user_id AND m2.receiver_id = :user_id))
         AND m2.group_id IS NULL
         AND m2.is_pinned = 1
         ORDER BY m2.created_at DESC LIMIT 1) as is_pinned,
        COUNT(CASE WHEN m.receiver_id = :user_id AND m.is_read = 0 AND m.deleted_by NOT LIKE CONCAT('%,', :user_id, ',%') THEN 1 END) as unread_count,
        (SELECT archived FROM user_chat_settings WHERE user_id = :user_id AND chat_with = other_user_id) as is_archived
     FROM messages m
     LEFT JOIN users u ON u.id = CASE 
        WHEN m.sender_id = :user_id THEN m.receiver_id
        ELSE m.sender_id
     END
     WHERE (m.sender_id = :user_id OR m.receiver_id = :user_id)
     AND m.group_id IS NULL
     AND m.deleted_by NOT LIKE CONCAT('%,', :user_id, ',%')
     GROUP BY other_user_id
     HAVING is_archived IS NULL OR is_archived = 0
     ORDER BY is_pinned DESC, last_message_time DESC
     LIMIT 50",
    [':user_id' => $currentUserId]
);

// دریافت گروه‌ها
$groups = db()->select(
    "SELECT mg.id, mg.name, mg.description, mg.avatar, mg.created_by,
     (SELECT COUNT(*) FROM messages WHERE group_id = mg.id AND is_read = 0 AND deleted_by NOT LIKE CONCAT('%,', :user_id, ',%')) as unread_count,
     (SELECT message FROM messages WHERE group_id = mg.id AND deleted_by NOT LIKE CONCAT('%,', :user_id, ',%') ORDER BY created_at DESC LIMIT 1) as last_message,
     (SELECT created_at FROM messages WHERE group_id = mg.id AND deleted_by NOT LIKE CONCAT('%,', :user_id, ',%') ORDER BY created_at DESC LIMIT 1) as last_message_time,
     (SELECT COUNT(*) FROM message_groups WHERE id = mg.id AND JSON_CONTAINS(members, CAST(:user_id AS JSON), '$')) as is_member
     FROM message_groups mg
     WHERE JSON_CONTAINS(mg.members, CAST(:user_id AS JSON), '$')
     ORDER BY last_message_time DESC",
    [':user_id' => $currentUserId]
);

// دریافت مکالمات آرشیو شده
$archivedCount = db()->count(
    'user_chat_settings',
    'user_id = :user_id AND archived = 1',
    [':user_id' => $currentUserId]
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_TITLE; ?></title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #4caf50;
            --danger: #f44336;
            --warning: #ff9800;
            --info: #2196f3;
            --dark: #2c3e50;
            --light: #f5f7fa;
            --border: #e0e0e0;
            --online: #4caf50;
            --offline: #999;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', 'Vazir', Arial, sans-serif;
            background: var(--light);
            overflow: hidden;
        }
        
        .messenger-wrapper {
            height: calc(100vh - 150px);
            display: flex;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            margin: 20px;
        }
        
        /* Sidebar - لیست کاربران */
        .users-sidebar {
            width: 320px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            flex-direction: column;
            border-left: 1px solid rgba(255,255,255,0.1);
            position: relative;
            transition: transform 0.3s;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
        }
        
        .header-btn {
            width: 35px;
            height: 35px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .header-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        
        .search-users {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: none;
            border-radius: 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 14px;
            font-family: inherit;
        }
        
        .search-users::placeholder {
            color: rgba(255,255,255,0.7);
        }
        
        .search-users:focus {
            outline: none;
            background: rgba(255,255,255,0.3);
        }
        
        .users-tabs {
            display: flex;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .user-tab {
            flex: 1;
            padding: 12px;
            background: none;
            border: none;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .user-tab.active {
            color: white;
            border-bottom-color: white;
        }
        
        .users-list {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.3) transparent;
        }
        
        .users-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .users-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: relative;
        }
        
        .user-item:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .user-item.pinned {
            background: rgba(255,255,255,0.05);
        }
        
        .user-item.pinned::before {
            content: '📌';
            position: absolute;
            right: 5px;
            top: 5px;
            font-size: 12px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
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
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .online-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--online);
            border: 3px solid white;
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
        
        .offline-indicator {
            background: var(--offline);
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .user-name {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 15px;
        }
        
        .user-time {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .user-preview {
            font-size: 13px;
            opacity: 0.8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unread-badge {
            min-width: 22px;
            height: 22px;
            background: white;
            color: var(--primary);
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            padding: 0 6px;
            flex-shrink: 0;
        }
        
        /* Conversations Panel */
        .conversations-panel {
            width: 380px;
            background: #fafafa;
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .conversations-header {
            padding: 20px;
            background: white;
            border-bottom: 2px solid var(--border);
        }
        
        .conversations-header h3 {
            color: var(--dark);
            font-size: 18px;
            margin-bottom: 12px;
        }
        
        .conv-tabs {
            display: flex;
            gap: 8px;
        }
        
        .conv-tab {
            flex: 1;
            padding: 10px;
            background: var(--light);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .conv-tab.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid #e8e8e8;
            background: white;
            margin-bottom: 2px;
            position: relative;
        }
        
        .conversation-item:hover {
            background: #f0f2ff;
        }
        
        .conversation-item.active {
            background: #e3e7ff;
            border-right: 4px solid var(--primary);
        }
        
        .conversation-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 22px;
            position: relative;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .conversation-name {
            font-weight: 600;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 15px;
        }
        
        .conversation-time {
            font-size: 11px;
            color: #999;
        }
        
        .conversation-preview {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #e8eaf6;
            position: relative;
        }
        
        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #999;
            background: white;
        }
        
        .empty-chat-icon {
            font-size: 100px;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .empty-chat h3 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .empty-chat p {
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .new-chat-btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .new-chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Archived Chats */
        .archived-section {
            padding: 15px 20px;
            background: rgba(255, 193, 7, 0.1);
            border-bottom: 1px solid rgba(255, 193, 7, 0.3);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .archived-section:hover {
            background: rgba(255, 193, 7, 0.2);
        }
        
        .archived-icon {
            font-size: 20px;
        }
        
        .archived-text {
            flex: 1;
            font-weight: 500;
            color: var(--dark);
        }
        
        .archived-count {
            background: var(--warning);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .users-sidebar {
                width: 280px;
            }
            
            .conversations-panel {
                width: 320px;
            }
        }
        
        @media (max-width: 992px) {
            .conversations-panel {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .messenger-wrapper {
                margin: 10px;
                height: calc(100vh - 120px);
            }
            
            .users-sidebar {
                width: 100%;
                position: absolute;
                z-index: 100;
                transform: translateX(-100%);
            }
            
            .users-sidebar.active {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>

<div class="messenger-wrapper">
    <!-- Sidebar - کاربران -->
    <div class="users-sidebar" id="usersSidebar">
        <div class="sidebar-header">
            <h2>
                <span>👥 کاربران</span>
                <div class="header-actions">
                    <button class="header-btn" title="تنظیمات" onclick="openSettings()">⚙️</button>
                    <button class="header-btn" title="ایجاد گروه" onclick="createNewGroup()">➕</button>
                </div>
            </h2>
            <input type="text" class="search-users" placeholder="جستجو..." id="searchUsers">
        </div>
        
        <div class="users-tabs">
            <button class="user-tab active" onclick="switchUserTab('online')">آنلاین (<?php echo count(array_filter($users, fn($u) => $u['is_online'])); ?>)</button>
            <button class="user-tab" onclick="switchUserTab('all')">همه</button>
        </div>
        
        <div class="users-list" id="usersList">
            <?php foreach ($users as $user): ?>
                <div class="user-item <?php echo $user['is_online'] ? 'online' : 'offline'; ?>" 
                     data-user-id="<?php echo $user['id']; ?>"
                     onclick="startChat(<?php echo $user['id']; ?>, '<?php echo h($user['fullname']); ?>')">
                    <div class="user-avatar">
                        <?php echo mb_substr($user['fullname'], 0, 1); ?>
                        <div class="online-indicator <?php echo !$user['is_online'] ? 'offline-indicator' : ''; ?>"></div>
                    </div>
                    <div class="user-info">
                        <div class="user-header">
                            <div class="user-name"><?php echo h($user['fullname']); ?></div>
                            <?php if ($user['is_online']): ?>
                                <div class="user-time">آنلاین</div>
                            <?php endif; ?>
                        </div>
                        <div class="user-preview"><?php echo h($user['email']); ?></div>
                    </div>
                    <?php if ($user['unread_count'] > 0): ?>
                        <div class="unread-badge"><?php echo en2fa($user['unread_count']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Conversations Panel -->
    <div class="conversations-panel">
        <div class="conversations-header">
            <h3>💬 مکالمات</h3>
            <div class="conv-tabs">
                <button class="conv-tab active" onclick="switchConvTab('private')">
                    💬 خصوصی
                </button>
                <button class="conv-tab" onclick="switchConvTab('groups')">
                    👥 گروهی (<?php echo count($groups); ?>)
                </button>
            </div>
        </div>
        
        <div class="conversations-list">
            <?php if ($archivedCount > 0): ?>
                <div class="archived-section" onclick="showArchived()">
                    <span class="archived-icon">📦</span>
                    <span class="archived-text">مکالمات آرشیو شده</span>
                    <span class="archived-count"><?php echo en2fa($archivedCount); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- مکالمات خصوصی -->
            <div id="tab-private" class="tab-content active">
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?php echo $conv['is_pinned'] ? 'pinned' : ''; ?>" 
                             onclick="openChat(<?php echo $conv['other_user_id']; ?>, 'private')">
                            <div class="conversation-avatar">
                                <?php echo mb_substr($conv['other_user_name'], 0, 1); ?>
                                <?php if ($conv['is_online']): ?>
                                    <div class="online-indicator"></div>
                                <?php endif; ?>
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <div class="conversation-name"><?php echo h($conv['other_user_name']); ?></div>
                                    <div class="conversation-time">
                                        <?php echo $conv['last_message_time'] ? en2fa(date('H:i', strtotime($conv['last_message_time']))) : ''; ?>
                                    </div>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo h(mb_substr($conv['last_message'] ?? 'شروع مکالمه', 0, 35)); ?>...
                                </div>
                            </div>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo en2fa($conv['unread_count']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; color: #999;">
                        <p>هنوز مکالمه‌ای ندارید</p>
                        <p style="font-size: 12px; margin-top: 10px;">از سمت راست یک کاربر را انتخاب کنید</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- گروه‌ها -->
            <div id="tab-groups" class="tab-content">
                <?php if (count($groups) > 0): ?>
                    <?php foreach ($groups as $group): ?>
                        <div class="conversation-item" onclick="openChat(<?php echo $group['id']; ?>, 'group')">
                            <div class="conversation-avatar">
                                <?php if ($group['avatar']): ?>
                                    <img src="<?php echo h($group['avatar']); ?>" alt="Group">
                                <?php else: ?>
                                    👥
                                <?php endif; ?>
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <div class="conversation-name"><?php echo h($group['name']); ?></div>
                                    <div class="conversation-time">
                                        <?php echo $group['last_message_time'] ? en2fa(date('H:i', strtotime($group['last_message_time']))) : ''; ?>
                                    </div>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo h(mb_substr($group['last_message'] ?? 'گروه جدید', 0, 35)); ?>...
                                </div>
                            </div>
                            <?php if ($group['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo en2fa($group['unread_count']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; color: #999;">
                        <p>هنوز گروهی ندارید</p>
                        <button class="new-chat-btn" style="margin-top: 15px;" onclick="createNewGroup()">
                            ➕ ایجاد گروه جدید
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Chat Area -->
    <div class="chat-area">
        <div class="empty-chat">
            <div class="empty-chat-icon">💬</div>
            <h3>پیام‌رسان پیشرفته eSmartis</h3>
            <p>یک مکالمه را انتخاب کنید یا چت جدیدی شروع کنید</p>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button class="new-chat-btn" onclick="showNewChatDialog()">
                    ➕ چت جدید
                </button>
                <button class="new-chat-btn" onclick="createNewGroup()">
                    👥 گروه جدید
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentTab = 'private';
    let currentUserTab = 'online';
    
    // تابع تغییر تب مکالمات
    function switchConvTab(tab) {
        currentTab = tab;
        
        document.querySelectorAll('.conv-tab').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
    }
    
    // تابع تغییر تب کاربران
    function switchUserTab(tab) {
        currentUserTab = tab;
        
        document.querySelectorAll('.user-tab').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        
        const users = document.querySelectorAll('.user-item');
        users.forEach(user => {
            if (tab === 'online') {
                user.style.display = user.classList.contains('online') ? 'flex' : 'none';
            } else {
                user.style.display = 'flex';
            }
        });
    }
    
    // شروع چت با کاربر
    function startChat(userId, userName) {
        window.location.href = `chat_advanced.php?type=private&user_id=${userId}`;
    }
    
    // باز کردن چت
    function openChat(id, type) {
        if (type === 'private') {
            window.location.href = `chat_advanced.php?type=private&user_id=${id}`;
        } else {
            window.location.href = `chat_advanced.php?type=group&group_id=${id}`;
        }
    }
    
    // ایجاد گروه جدید
    function createNewGroup() {
        window.location.href = 'group_create.php';
    }
    
    // نمایش دیالوگ چت جدید
    function showNewChatDialog() {
        // در نسخه کامل، یک modal نمایش داده می‌شود
        alert('لیست کاربران را از سمت راست ببینید');
    }
    
    // باز کردن تنظیمات
    function openSettings() {
        window.location.href = 'messenger_settings.php';
    }
    
    // نمایش آرشیو
    function showArchived() {
        window.location.href = 'messenger_archived.php';
    }
    
    // جستجو در کاربران
    document.getElementById('searchUsers').addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.user-item').forEach(item => {
            const name = item.querySelector('.user-name').textContent.toLowerCase();
            const email = item.querySelector('.user-preview').textContent.toLowerCase();
            
            if (name.includes(search) || email.includes(search)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // به‌روزرسانی خودکار وضعیت آنلاین
    setInterval(function() {
        // در نسخه کامل، از AJAX برای به‌روزرسانی استفاده می‌شود
        fetch('message_api.php?action=get_online_users')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // به‌روزرسانی وضعیت کاربران
                    console.log('Online users updated');
                }
            })
            .catch(err => console.error('Error updating online status:', err));
    }, 15000); // هر 15 ثانیه
    
    // Toggle sidebar در موبایل
    function toggleSidebar() {
        document.getElementById('usersSidebar').classList.toggle('active');
    }
</script>

<?php require_once 'footer.php'; ?>