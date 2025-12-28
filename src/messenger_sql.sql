-- جداول سیستم پیام‌رسان پیشرفته
-- eSmartis Advanced Messenger System

-- جدول پیام‌ها (به‌روزرسانی شده)
ALTER TABLE messages 
ADD COLUMN IF NOT EXISTS reply_to INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_pinned TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_edited TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS edited_at DATETIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS deleted_by TEXT DEFAULT NULL COMMENT 'Comma-separated user IDs',
ADD COLUMN IF NOT EXISTS attachments TEXT DEFAULT NULL COMMENT 'JSON array of files',
ADD COLUMN IF NOT EXISTS forwarded_from INT DEFAULT NULL,
ADD INDEX idx_reply_to (reply_to),
ADD INDEX idx_pinned (is_pinned);

-- جدول گروه‌های پیام‌رسانی (به‌روزرسانی شده)
ALTER TABLE message_groups
ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS settings TEXT DEFAULT NULL COMMENT 'JSON group settings';

-- جدول ری‌اکشن‌های پیام
CREATE TABLE IF NOT EXISTS message_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reaction (message_id, user_id, emoji),
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تنظیمات چت کاربران
CREATE TABLE IF NOT EXISTS user_chat_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chat_with INT NOT NULL COMMENT 'User ID for private chat',
    archived TINYINT(1) DEFAULT 0,
    muted TINYINT(1) DEFAULT 0,
    notifications_enabled TINYINT(1) DEFAULT 1,
    custom_name VARCHAR(100) DEFAULT NULL,
    background VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chat_with) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_chat_setting (user_id, chat_with),
    INDEX idx_user (user_id),
    INDEX idx_archived (archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول وضعیت‌های آنلاین/آفلاین
CREATE TABLE IF NOT EXISTS user_online_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    is_online TINYINT(1) DEFAULT 0,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    typing_to INT DEFAULT NULL COMMENT 'User ID or NULL',
    typing_in_group INT DEFAULT NULL COMMENT 'Group ID or NULL',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_status (user_id),
    INDEX idx_online (is_online),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول فایل‌های آپلود شده
CREATE TABLE IF NOT EXISTS message_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_message (message_id),
    INDEX idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تاریخچه تماس‌ها (صوتی و تصویری)
CREATE TABLE IF NOT EXISTS call_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('voice', 'video') NOT NULL,
    caller_id INT NOT NULL,
    receiver_id INT DEFAULT NULL,
    group_id INT DEFAULT NULL,
    status ENUM('missed', 'rejected', 'completed', 'ongoing') DEFAULT 'missed',
    duration INT DEFAULT 0 COMMENT 'Duration in seconds',
    started_at DATETIME NOT NULL,
    ended_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES message_groups(id) ON DELETE CASCADE,
    INDEX idx_caller (caller_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول وایت‌بردهای ذخیره شده
CREATE TABLE IF NOT EXISTS whiteboards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    data LONGTEXT NOT NULL COMMENT 'Canvas data as base64',
    thumbnail VARCHAR(500) DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES message_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول گزارش‌های پیام (برای مدیریت)
CREATE TABLE IF NOT EXISTS message_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    reported_by INT NOT NULL,
    reason ENUM('spam', 'inappropriate', 'harassment', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول پیام‌های زمان‌بندی شده
CREATE TABLE IF NOT EXISTS scheduled_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT DEFAULT NULL,
    group_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    attachments TEXT DEFAULT NULL COMMENT 'JSON array',
    scheduled_for DATETIME NOT NULL,
    sent TINYINT(1) DEFAULT 0,
    sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES message_groups(id) ON DELETE CASCADE,
    INDEX idx_scheduled (scheduled_for, sent),
    INDEX idx_sender (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول دسترسی‌های گروه
CREATE TABLE IF NOT EXISTS group_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'moderator', 'member') DEFAULT 'member',
    can_send_messages TINYINT(1) DEFAULT 1,
    can_add_members TINYINT(1) DEFAULT 0,
    can_remove_members TINYINT(1) DEFAULT 0,
    can_edit_info TINYINT(1) DEFAULT 0,
    can_pin_messages TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES message_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user (group_id, user_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول پیام‌های پین شده
CREATE TABLE IF NOT EXISTS pinned_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    pinned_by INT NOT NULL,
    group_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL COMMENT 'For private chats',
    pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (pinned_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES message_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_message (message_id),
    INDEX idx_group (group_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- نماهای کاربردی (Views)

-- نمای آمار پیام‌ها
CREATE OR REPLACE VIEW message_stats AS
SELECT 
    u.id as user_id,
    u.fullname,
    COUNT(m.id) as total_messages,
    COUNT(CASE WHEN m.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as messages_today,
    COUNT(CASE WHEN m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as messages_week,
    MAX(m.created_at) as last_message_at
FROM users u
LEFT JOIN messages m ON m.sender_id = u.id
GROUP BY u.id, u.fullname;

-- نمای مکالمات فعال
CREATE OR REPLACE VIEW active_conversations AS
SELECT 
    CASE 
        WHEN m.sender_id < m.receiver_id THEN m.sender_id
        ELSE m.receiver_id
    END as user1_id,
    CASE 
        WHEN m.sender_id < m.receiver_id THEN m.receiver_id
        ELSE m.sender_id
    END as user2_id,
    MAX(m.created_at) as last_activity,
    COUNT(m.id) as message_count,
    COUNT(CASE WHEN m.is_read = 0 THEN 1 END) as unread_count
FROM messages m
WHERE m.group_id IS NULL
GROUP BY user1_id, user2_id
ORDER BY last_activity DESC;

-- Trigger برای به‌روزرسانی تعداد اعضای گروه
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS update_group_member_count
AFTER UPDATE ON message_groups
FOR EACH ROW
BEGIN
    -- می‌توان منطق اضافی اضافه کرد
    IF OLD.members != NEW.members THEN
        INSERT INTO logs (user_id, action, module, record_id, new_data, ip_address)
        VALUES (NULL, 'group_members_updated', 'messenger', NEW.id, NEW.members, '0.0.0.0');
    END IF;
END$$

DELIMITER ;

-- Insert default data for testing (optional)
-- INSERT INTO user_online_status (user_id, is_online) 
-- SELECT id, 0 FROM users ON DUPLICATE KEY UPDATE user_id=user_id;