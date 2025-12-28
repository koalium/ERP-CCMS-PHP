<?php
/**
 * سیستم پیام‌رسان پیشرفته
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'پیام‌رسان';
require_once 'header.php';

check_login();

$currentUserId = $_SESSION['user_id'];

// دریافت لیست کاربران
$users = db()->select(
    "SELECT u.id, u.fullname, u.email, u.last_login,
     CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online
     FROM users u
     WHERE u.id != :current_user AND u.is_active = 1
     ORDER BY is_online DESC, u.fullname",
    [':current_user' => $currentUserId]
);

// دریافت مکالمات اخیر
$conversations = db()->select(
    "SELECT 
        CASE 
            WHEN m.sender_id = :user_id THEN m.receiver_id
            ELSE m.sender_id
        END as other_user_id,
        u.fullname as other_user_name,
        u.email as other_user_email,
        CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online,
        MAX(m.created_at) as last_message_time,
        (SELECT message FROM messages m2 
         WHERE ((m2.sender_id = :user_id AND m2.receiver_id = other_user_id) 
                OR (m2.sender_id = other_user_id AND m2.receiver_id = :user_id))
         AND m2.group_id IS NULL
         ORDER BY m2.created_at DESC LIMIT 1) as last_message,
        COUNT(CASE WHEN m.receiver_id = :user_id AND m.is_read = 0 THEN 1 END) as unread_count
     FROM messages m
     LEFT JOIN users u ON u.id = CASE 
        WHEN m.sender_id = :user_id THEN m.receiver_id
        ELSE m.sender_id
     END
     WHERE (m.sender_id = :user_id OR m.receiver_id = :user_id)
     AND m.group_id IS NULL
     GROUP BY other_user_id, u.fullname, u.email, u.last_login
     ORDER BY last_message_time DESC
     LIMIT 20",
    [':user_id' => $currentUserId]
);

// دریافت گروه‌ها
$groups = db()->select(
    "SELECT DISTINCT mg.id, mg.name, mg.description, mg.created_at,
     (SELECT COUNT(*) FROM messages WHERE group_id = mg.id AND receiver_id = :user_id AND is_read = 0) as unread_count,
     (SELECT message FROM messages WHERE group_id = mg.id ORDER BY created_at DESC LIMIT 1) as last_message,
     (SELECT created_at FROM messages WHERE group_id = mg.id ORDER BY created_at DESC LIMIT 1) as last_message_time
     FROM message_groups mg
     WHERE JSON_CONTAINS(mg.members, CAST(:user_id AS JSON), '$')
     ORDER BY last_message_time DESC",
    [':user_id' => $currentUserId]
);
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    .messenger-container {
        display: grid;
        grid-template-columns: 300px 350px 1fr;
        height: calc(100vh - 200px);
        gap: 0;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    /* Sidebar - لیست کاربران */
    .users-sidebar {
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        flex-direction: column;
    }
    
    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .sidebar-header h2 {
        font-size: 20px;
        margin-bottom: 15px;
    }
    
    .search-users {
        width: 100%;
        padding: 10px 15px;
        border: none;
        border-radius: 20px;
        background: rgba(255,255,255,0.2);
        color: white;
        font-size: 14px;
    }
    
    .search-users::placeholder {
        color: rgba(255,255,255,0.7);
    }
    
    .users-list {
        flex: 1;
        overflow-y: auto;
        scrollbar-width: thin;
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
        transition: background 0.2s;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .user-item:hover {
        background: rgba(255,255,255,0.1);
    }
    
    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: white;
        color: #667eea;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        position: relative;
        flex-shrink: 0;
    }
    
    .online-indicator {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #4caf50;
        border: 2px solid white;
    }
    
    .user-info {
        flex: 1;
        min-width: 0;
    }
    
    .user-name {
        font-weight: 500;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .user-email {
        font-size: 12px;
        opacity: 0.8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Conversations - مکالمات */
    .conversations-panel {
        background: #f8f9fa;
        border-left: 1px solid #e0e0e0;
        border-right: 1px solid #e0e0e0;
        display: flex;
        flex-direction: column;
    }
    
    .conversations-header {
        padding: 20px;
        background: white;
        border-bottom: 2px solid #e0e0e0;
    }
    
    .conversations-header h3 {
        color: #2c3e50;
        font-size: 18px;
        margin-bottom: 10px;
    }
    
    .tabs {
        display: flex;
        gap: 5px;
    }
    
    .tab {
        flex: 1;
        padding: 8px;
        background: #f0f0f0;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .tab.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        transition: background 0.2s;
        border-bottom: 1px solid #e8e8e8;
        background: white;
        margin-bottom: 2px;
    }
    
    .conversation-item:hover {
        background: #f0f2ff;
    }
    
    .conversation-item.active {
        background: #e3e7ff;
        border-right: 4px solid #667eea;
    }
    
    .conversation-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 20px;
        position: relative;
        flex-shrink: 0;
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
        color: #2c3e50;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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
    
    .unread-badge {
        min-width: 20px;
        height: 20px;
        background: #f44336;
        color: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        padding: 0 6px;
        flex-shrink: 0;
    }
    
    /* Chat Area */
    .chat-area {
        display: flex;
        flex-direction: column;
        background: white;
    }
    
    .empty-chat {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #999;
    }
    
    .empty-chat-icon {
        font-size: 80px;
        margin-bottom: 20px;
    }
    
    .empty-chat h3 {
        font-size: 24px;
        margin-bottom: 10px;
    }
    
    .empty-chat p {
        font-size: 14px;
    }
    
    .new-group-btn {
        margin-top: 15px;
        padding: 12px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    @media (max-width: 1024px) {
        .messenger-container {
            grid-template-columns: 80px 1fr;
        }
        
        .conversations-panel {
            display: none;
        }
        
        .user-info {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .messenger-container {
            grid-template-columns: 1fr;
        }
        
        .users-sidebar {
            display: none;
        }
    }
</style>

<div class="messenger-container">
    <!-- Sidebar - کاربران آنلاین -->
    <div class="users-sidebar">
        <div class="sidebar-header">
            <h2>👥 کاربران</h2>
            <input type="text" class="search-users" placeholder="جستجو..." id="searchUsers">
        </div>
        
        <div class="users-list" id="usersList">
            <?php foreach ($users as $user): ?>
                <div class="user-item" onclick="startChat(<?php echo $user['id']; ?>, '<?php echo h($user['fullname']); ?>')">
                    <div class="user-avatar">
                        <?php echo mb_substr($user['fullname'], 0, 1); ?>
                        <?php if ($user['is_online']): ?>
                            <div class="online-indicator"></div>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo h($user['fullname']); ?></div>
                        <div class="user-email"><?php echo h($user['email']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Panel مکالمات -->
    <div class="conversations-panel">
        <div class="conversations-header">
            <h3>💬 پیام‌ها</h3>
            <div class="tabs">
                <button class="tab active" onclick="switchTab('private')">خصوصی</button>
                <button class="tab" onclick="switchTab('groups')">گروهی</button>
            </div>
        </div>
        
        <div class="conversations-list">
            <!-- مکالمات خصوصی -->
            <div id="tab-private" class="tab-content active">
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item" onclick="openChat(<?php echo $conv['other_user_id']; ?>, 'private')">
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
                                        <?php echo en2fa(date('H:i', strtotime($conv['last_message_time']))); ?>
                                    </div>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo h(mb_substr($conv['last_message'] ?? 'پیامی وجود ندارد', 0, 40)); ?>...
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
                        <p style="font-size: 12px; margin-top: 10px;">از سمت چپ یک کاربر را انتخاب کنید</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- گروه‌ها -->
            <div id="tab-groups" class="tab-content">
                <?php if (count($groups) > 0): ?>
                    <?php foreach ($groups as $group): ?>
                        <div class="conversation-item" onclick="openChat(<?php echo $group['id']; ?>, 'group')">
                            <div class="conversation-avatar">👥</div>
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <div class="conversation-name"><?php echo h($group['name']); ?></div>
                                    <div class="conversation-time">
                                        <?php echo $group['last_message_time'] ? en2fa(date('H:i', strtotime($group['last_message_time']))) : ''; ?>
                                    </div>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo h(mb_substr($group['last_message'] ?? 'پیامی وجود ندارد', 0, 40)); ?>...
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
                        <button class="new-group-btn" onclick="createGroup()">
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
            <h3>پیام‌رسان eSmartis</h3>
            <p>یک مکالمه را انتخاب کنید یا چت جدیدی شروع کنید</p>
            <button class="new-group-btn" onclick="createGroup()">
                👥 ایجاد گروه جدید
            </button>
        </div>
    </div>
</div>

<script>
    let currentTab = 'private';
    
    function switchTab(tab) {
        currentTab = tab;
        
        // تغییر active tab
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        
        // تغییر محتوا
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
    }
    
    function startChat(userId, userName) {
        window.location.href = `chat.php?type=private&user_id=${userId}`;
    }
    
    function openChat(id, type) {
        if (type === 'private') {
            window.location.href = `chat.php?type=private&user_id=${id}`;
        } else {
            window.location.href = `chat.php?type=group&group_id=${id}`;
        }
    }
    
    function createGroup() {
        const name = prompt('نام گروه را وارد کنید:');
        if (name) {
            window.location.href = `chat.php?type=group&action=create&name=${encodeURIComponent(name)}`;
        }
    }
    
    // جستجو در کاربران
    document.getElementById('searchUsers').addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.user-item').forEach(item => {
            const name = item.querySelector('.user-name').textContent.toLowerCase();
            const email = item.querySelector('.user-email').textContent.toLowerCase();
            if (name.includes(search) || email.includes(search)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // به‌روزرسانی خودکار (هر 10 ثانیه)
    setInterval(function() {
        // در نسخه کامل، از AJAX برای به‌روزرسانی استفاده می‌شود
        console.log('Checking for new messages...');
    }, 10000);
</script>

<?php require_once 'footer.php'; ?>