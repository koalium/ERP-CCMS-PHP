/**
 * توابع JavaScript برای چت پیشرفته
 */

// متغیرهای سراسری
let currentReplyTo = null;
let currentEditId = null;
let selectedMessageId = null;
let typingTimeout = null;
let isTyping = false;
let lastMessageId = 0;

// اسکرول خودکار به آخرین پیام
const messagesArea = document.getElementById('messagesArea');
messagesArea.scrollTop = messagesArea.scrollHeight;

// تنظیم ارتفاع خودکار textarea
const messageInput = document.getElementById('messageInput');
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});

// ارسال با Enter
messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) {
            document.getElementById('messageForm').dispatchEvent(new Event('submit'));
        }
    }
});

// ارسال پیام
document.getElementById('messageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const message = messageInput.value.trim();
    if (!message) return;
    
    const formData = new FormData();
    formData.append('message', message);
    formData.append('reply_to', currentReplyTo || '');
    formData.append('edit_message_id', currentEditId || '');
    
    // URL params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('type') === 'private') {
        formData.append('receiver_id', urlParams.get('user_id'));
    } else {
        formData.append('group_id', urlParams.get('group_id'));
    }
    
    try {
        const response = await fetch('message_api.php?action=send', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            messageInput.value = '';
            messageInput.style.height = 'auto';
            cancelReply();
            cancelEdit();
            
            // بارگذاری مجدد پیام‌ها
            await loadNewMessages();
        } else {
            alert('خطا در ارسال پیام: ' + data.error);
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('خطا در ارسال پیام');
    }
});

// ریپلای کردن به پیام
function replyToMessage(messageId, messagePreview) {
    currentReplyTo = messageId;
    document.getElementById('replyToInput').value = messageId;
    document.getElementById('replyPreview').classList.add('active');
    document.getElementById('replyPreviewContent').textContent = '↩️ پاسخ به: ' + messagePreview;
    messageInput.focus();
}

// لغو ریپلای
function cancelReply() {
    currentReplyTo = null;
    document.getElementById('replyToInput').value = '';
    document.getElementById('replyPreview').classList.remove('active');
}

// ویرایش پیام
function editMessage(messageId, currentText) {
    currentEditId = messageId;
    document.getElementById('editMessageId').value = messageId;
    messageInput.value = currentText;
    messageInput.focus();
    messageInput.setSelectionRange(messageInput.value.length, messageInput.value.length);
    
    // نمایش حالت ویرایش
    document.getElementById('replyPreview').classList.add('active');
    document.getElementById('replyPreviewContent').textContent = '✏️ ویرایش پیام';
}

// لغو ویرایش
function cancelEdit() {
    currentEditId = null;
    document.getElementById('editMessageId').value = '';
}

// فوروارد پیام
async function forwardMessage(messageId) {
    // در نسخه کامل، یک modal برای انتخاب مخاطب نمایش داده می‌شود
    const confirmation = confirm('آیا می‌خواهید این پیام را فوروارد کنید؟');
    if (confirmation) {
        alert('قابلیت فوروارد در نسخه کامل فعال خواهد شد');
    }
}

// حذف پیام
async function deleteMessage() {
    if (!selectedMessageId) return;
    
    if (!confirm('آیا از حذف این پیام اطمینان دارید؟')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('message_id', selectedMessageId);
        
        const response = await fetch('message_api.php?action=delete', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // حذف پیام از DOM
            const messageElement = document.querySelector(`[data-message-id="${selectedMessageId}"]`);
            if (messageElement) {
                messageElement.remove();
            }
        } else {
            alert('خطا در حذف پیام');
        }
    } catch (error) {
        console.error('Error deleting message:', error);
        alert('خطا در حذف پیام');
    }
    
    hideContextMenu();
}

// نمایش/مخفی کردن ایموجی
function toggleEmoji() {
    const picker = document.getElementById('emojiPicker');
    picker.classList.toggle('active');
}

// درج ایموجی
function insertEmoji(emoji) {
    const input = messageInput;
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const text = input.value;
    
    input.value = text.substring(0, start) + emoji + text.substring(end);
    input.focus();
    input.setSelectionRange(start + emoji.length, start + emoji.length);
    
    toggleEmoji();
}

// بستن ایموجی با کلیک خارج
document.addEventListener('click', function(e) {
    const emojiPicker = document.getElementById('emojiPicker');
    const emojiBtn = event.target.closest('.input-btn[title="ایموجی"]');
    
    if (!emojiPicker.contains(e.target) && !emojiBtn) {
        emojiPicker.classList.remove('active');
    }
});

// Context Menu
let contextMenu = document.getElementById('contextMenu');

function showContextMenu(e, messageId, isSent) {
    e.preventDefault();
    selectedMessageId = messageId;
    
    // نمایش/مخفی کردن آیتم‌های مربوط به پیام‌های خودی
    document.getElementById('editMenuItem').style.display = isSent ? 'block' : 'none';
    document.getElementById('deleteMenuItem').style.display = isSent ? 'block' : 'none';
    
    contextMenu.classList.add('active');
    contextMenu.style.top = e.pageY + 'px';
    contextMenu.style.left = e.pageX + 'px';
}

function hideContextMenu() {
    contextMenu.classList.remove('active');
    selectedMessageId = null;
}

// بستن context menu با کلیک خارج
document.addEventListener('click', function(e) {
    if (!contextMenu.contains(e.target)) {
        hideContextMenu();
    }
});

// توابع context menu
function copyMessage() {
    const messageElement = document.querySelector(`[data-message-id="${selectedMessageId}"] .message-bubble`);
    if (messageElement) {
        const text = messageElement.textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert('پیام کپی شد');
        });
    }
    hideContextMenu();
}

function replyToMessageFromMenu() {
    const messageElement = document.querySelector(`[data-message-id="${selectedMessageId}"] .message-bubble`);
    if (messageElement) {
        const text = messageElement.textContent.substring(0, 30);
        replyToMessage(selectedMessageId, text);
    }
    hideContextMenu();
}

function forwardMessageFromMenu() {
    forwardMessage(selectedMessageId);
    hideContextMenu();
}

function editMessageFromMenu() {
    const messageElement = document.querySelector(`[data-message-id="${selectedMessageId}"] .message-bubble`);
    if (messageElement) {
        const text = messageElement.textContent;
        editMessage(selectedMessageId, text);
    }
    hideContextMenu();
}

async function pinMessage() {
    try {
        const formData = new FormData();
        formData.append('message_id', selectedMessageId);
        
        const response = await fetch('message_api.php?action=pin', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('پیام پین شد');
            location.reload();
        }
    } catch (error) {
        console.error('Error pinning message:', error);
    }
    
    hideContextMenu();
}

// اسکرول به پیام
function scrollToMessage(messageId) {
    const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
    if (messageElement) {
        messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        messageElement.style.backgroundColor = '#fff3cd';
        setTimeout(() => {
            messageElement.style.backgroundColor = '';
        }, 2000);
    }
}

// Typing indicator
function handleTyping() {
    if (!isTyping) {
        isTyping = true;
        sendTypingStatus(true);
    }
    
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        isTyping = false;
        sendTypingStatus(false);
    }, 3000);
}

async function sendTypingStatus(typing) {
    try {
        const formData = new FormData();
        formData.append('is_typing', typing ? '1' : '0');
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('type') === 'private') {
            formData.append('receiver_id', urlParams.get('user_id'));
        } else {
            formData.append('group_id', urlParams.get('group_id'));
        }
        
        await fetch('message_api.php?action=typing', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Error sending typing status:', error);
    }
}

// بارگذاری پیام‌های جدید
async function loadNewMessages() {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        let url = 'message_api.php?action=get_messages&last_id=' + lastMessageId;
        
        if (urlParams.get('type') === 'private') {
            url += '&user_id=' + urlParams.get('user_id');
        } else {
            url += '&group_id=' + urlParams.get('group_id');
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.messages.length > 0) {
            // افزودن پیام‌های جدید به DOM
            data.messages.forEach(msg => {
                appendMessage(msg);
                lastMessageId = Math.max(lastMessageId, msg.id);
            });
            
            // اسکرول به پایین
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    } catch (error) {
        console.error('Error loading new messages:', error);
    }
}

function appendMessage(msg) {
    // در نسخه کامل، پیام جدید به DOM اضافه می‌شود
    console.log('New message:', msg);
}

// چک کردن پیام‌های جدید (polling)
setInterval(loadNewMessages, 3000);

// دریافت آخرین ID پیام
const messages = document.querySelectorAll('[data-message-id]');
if (messages.length > 0) {
    lastMessageId = parseInt(messages[messages.length - 1].dataset.messageId);
}

// انتخاب فایل
async function handleFileSelect(event) {
    const files = event.target.files;
    if (files.length === 0) return;
    
    for (let file of files) {
        await uploadFile(file);
    }
    
    event.target.value = '';
}

async function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        const response = await fetch('message_api.php?action=upload_file', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // ارسال پیام با فایل
            await sendFileMessage(data.file_name, data.file_url, data.file_size, file.type);
        } else {
            alert('خطا در آپلود فایل: ' + data.error);
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        alert('خطا در آپلود فایل');
    }
}

async function sendFileMessage(fileName, fileUrl, fileSize, fileType) {
    const formData = new FormData();
    formData.append('message', fileName);
    formData.append('attachments', JSON.stringify([{
        name: fileName,
        url: fileUrl,
        size: formatFileSize(fileSize),
        type: fileType
    }]));
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('type') === 'private') {
        formData.append('receiver_id', urlParams.get('user_id'));
    } else {
        formData.append('group_id', urlParams.get('group_id'));
    }
    
    try {
        const response = await fetch('message_api.php?action=send', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadNewMessages();
        }
    } catch (error) {
        console.error('Error sending file message:', error);
    }
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// نمایش تصویر در viewer
function openImageViewer(url) {
    window.open(url, '_blank');
}

// دانلود فایل
function downloadFile(url) {
    window.open(url, '_blank');
}

// ضبط صدا
let mediaRecorder = null;
let audioChunks = [];

async function startVoiceRecord() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        
        mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
        };
        
        mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            audioChunks = [];
            
            // آپلود فایل صوتی
            const formData = new FormData();
            formData.append('file', audioBlob, 'voice_' + Date.now() + '.webm');
            
            try {
                const response = await fetch('message_api.php?action=upload_file', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    await sendFileMessage('پیام صوتی', data.file_url, audioBlob.size, 'audio/webm');
                }
            } catch (error) {
                console.error('Error uploading voice:', error);
            }
        };
        
        mediaRecorder.start();
        
        // نمایش دکمه توقف
        const btn = event.target;
        btn.textContent = '⏹️';
        btn.onclick = stopVoiceRecord;
        
        setTimeout(() => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                stopVoiceRecord();
            }
        }, 60000); // حداکثر 60 ثانیه
        
    } catch (error) {
        console.error('Error accessing microphone:', error);
        alert('دسترسی به میکروفون امکان‌پذیر نیست');
    }
}

function stopVoiceRecord() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        
        // بازگرداندن دکمه
        const btn = document.querySelector('.input-btn[title="ضبط صدا"]');
        btn.textContent = '🎤';
        btn.onclick = startVoiceRecord;
    }
}

// نمایش ری‌اکشن‌ها
function showReactions(messageId) {
    const reactions = ['❤️', '👍', '😂', '😮', '😢', '🔥'];
    const choice = prompt('انتخاب ری‌اکشن:\n' + reactions.join(' '));
    
    if (choice && reactions.includes(choice)) {
        addReaction(messageId, choice);
    }
}

async function addReaction(messageId, emoji) {
    try {
        const formData = new FormData();
        formData.append('message_id', messageId);
        formData.append('emoji', emoji);
        
        const response = await fetch('message_api.php?action=add_reaction', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            location.reload(); // در نسخه کامل، به صورت دینامیک به‌روزرسانی می‌شود
        }
    } catch (error) {
        console.error('Error adding reaction:', error);
    }
}

// توابع header
function showProfile() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('type') === 'private') {
        window.location.href = 'user_profile.php?id=' + urlParams.get('user_id');
    } else {
        window.location.href = 'group_info.php?id=' + urlParams.get('group_id');
    }
}

function openSearch() {
    const query = prompt('جستجو در پیام‌ها:');
    if (query) {
        alert('جستجو برای: ' + query);
        // در نسخه کامل، پیام‌های مرتبط نمایش داده می‌شوند
    }
}

function startVoiceCall() {
    alert('تماس صوتی در نسخه کامل فعال خواهد شد');
}

function startVideoCall() {
    alert('تماس تصویری در نسخه کامل فعال خواهد شد');
}

function openWhiteboard() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('type') === 'group') {
        window.location.href = 'whiteboard.php?group_id=' + urlParams.get('group_id');
    } else {
        alert('وایت‌برد فقط برای گروه‌ها در دسترس است');
    }
}

function showChatMenu() {
    alert('منوی بیشتر');
}