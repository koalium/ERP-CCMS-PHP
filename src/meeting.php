<?php
/**
 * فرم جلسه و صورتجلسه
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

$action = $_GET['action'] ?? 'add';
$meetingId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// دریافت لیست کاربران برای حاضرین
$users = db()->select("SELECT id, fullname, email FROM users WHERE is_active = 1 ORDER BY fullname");

// بارگذاری اطلاعات جلسه برای ویرایش یا مشاهده
$meeting = null;
if ($meetingId > 0) {
    $meeting = db()->selectOne("SELECT * FROM meetings WHERE id = :id", [':id' => $meetingId]);
    
    if (!$meeting) {
        redirect(SITE_URL . '/meetings.php');
    }
    
    // Decode JSON fields
    $meeting['attendees'] = json_decode($meeting['attendees'] ?? '[]', true);
    $meeting['action_items'] = json_decode($meeting['action_items'] ?? '[]', true);
    $meeting['attachments'] = json_decode($meeting['attachments'] ?? '[]', true);
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_permission('meetings', PERMISSION_WRITE)) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'خطای امنیتی. لطفاً دوباره تلاش کنید.';
    } else {
        $data = [
            'title' => sanitize_input($_POST['title'] ?? ''),
            'type' => sanitize_input($_POST['type'] ?? ''),
            'meeting_date' => sanitize_input($_POST['meeting_date'] ?? ''),
            'meeting_time' => sanitize_input($_POST['meeting_time'] ?? ''),
            'duration_minutes' => (int)($_POST['duration_minutes'] ?? 0),
            'location' => sanitize_input($_POST['location'] ?? ''),
            'agenda' => $_POST['agenda'] ?? '',
            'attendees' => json_encode($_POST['attendees'] ?? []),
            'status' => sanitize_input($_POST['status'] ?? 'scheduled')
        ];
        
        // برای جلسات تکمیل شده، ذخیره صورتجلسه
        if ($data['status'] === 'completed') {
            $data['minutes'] = $_POST['minutes'] ?? '';
            $data['decisions'] = $_POST['decisions'] ?? '';
            $data['action_items'] = json_encode($_POST['action_items'] ?? []);
        }
        
        // اعتبارسنجی
        if (empty($data['title'])) {
            $error = 'عنوان جلسه الزامی است.';
        } elseif (empty($data['meeting_date'])) {
            $error = 'تاریخ جلسه الزامی است.';
        } elseif (empty($data['meeting_time'])) {
            $error = 'ساعت جلسه الزامی است.';
        } else {
            if ($action === 'add') {
                // تولید شماره جلسه
                $lastMeeting = db()->selectOne("SELECT meeting_number FROM meetings ORDER BY id DESC LIMIT 1");
                $lastNumber = $lastMeeting ? (int)substr($lastMeeting['meeting_number'], 4) : 0;
                $data['meeting_number'] = 'MTG-' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
                $data['created_by'] = $_SESSION['user_id'];
                
                $newId = db()->insert('meetings', $data);
                
                if ($newId) {
                    // ثبت لاگ
                    db()->insert('logs', [
                        'user_id' => $_SESSION['user_id'],
                        'action' => 'create_meeting',
                        'module' => 'meetings',
                        'record_id' => $newId,
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $success = 'جلسه با موفقیت ایجاد شد.';
                    $meetingId = $newId;
                    $action = 'edit';
                } else {
                    $error = 'خطا در ایجاد جلسه.';
                }
            } elseif ($action === 'edit') {
                $updated = db()->update('meetings', $data, 'id = :id', [':id' => $meetingId]);
                
                if ($updated !== false) {
                    // ثبت لاگ
                    db()->insert('logs', [
                        'user_id' => $_SESSION['user_id'],
                        'action' => 'update_meeting',
                        'module' => 'meetings',
                        'record_id' => $meetingId,
                        'new_data' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $success = 'جلسه با موفقیت به‌روزرسانی شد.';
                } else {
                    $error = 'خطا در به‌روزرسانی جلسه.';
                }
            }
            
            // بارگذاری مجدد اطلاعات
            if ($meetingId > 0) {
                $meeting = db()->selectOne("SELECT * FROM meetings WHERE id = :id", [':id' => $meetingId]);
                $meeting['attendees'] = json_decode($meeting['attendees'] ?? '[]', true);
                $meeting['action_items'] = json_decode($meeting['action_items'] ?? '[]', true);
                $meeting['attachments'] = json_decode($meeting['attachments'] ?? '[]', true);
            }
        }
    }
}

$pageTitle = ($action === 'add' ? 'برنامه‌ریزی جلسه جدید' : ($action === 'view' ? 'مشاهده جلسه' : 'ویرایش جلسه'));
require_once 'header.php';
?>

<style>
    .form-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .form-card {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .form-header h2 {
        color: #2c3e50;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-section {
        margin-bottom: 30px;
    }
    
    .section-title {
        font-size: 18px;
        color: #2c3e50;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    
    .form-group label {
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
        font-size: 14px;
    }
    
    .form-group label.required::after {
        content: ' *';
        color: #f44336;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: border-color 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
    }
    
    .form-group textarea {
        min-height: 120px;
        resize: vertical;
    }
    
    .attendees-selector {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .attendee-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: 6px;
        transition: background 0.2s;
    }
    
    .attendee-item:hover {
        background: #f5f7fa;
    }
    
    .attendee-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .attendee-info {
        flex: 1;
    }
    
    .attendee-name {
        font-weight: 500;
        color: #2c3e50;
    }
    
    .attendee-email {
        font-size: 12px;
        color: #999;
    }
    
    .action-items-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .action-item {
        display: grid;
        grid-template-columns: 1fr 200px 120px 40px;
        gap: 10px;
        align-items: start;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border-right: 4px solid #667eea;
    }
    
    .action-item input,
    .action-item select {
        padding: 8px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 13px;
    }
    
    .btn-remove-item {
        padding: 8px;
        background: #f44336;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 18px;
    }
    
    .btn-add-item {
        padding: 10px 20px;
        background: #4caf50;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
    }
    
    .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-success {
        background: #4caf50;
        color: white;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert-success {
        background: #e8f5e9;
        color: #2e7d32;
        border-right: 4px solid #4caf50;
    }
    
    .alert-error {
        background: #ffebee;
        color: #c62828;
        border-right: 4px solid #f44336;
    }
    
    .view-mode .form-group input,
    .view-mode .form-group select,
    .view-mode .form-group textarea {
        background: #f5f7fa;
        border-color: #d0d0d0;
        cursor: not-allowed;
    }
    
    @media (max-width: 768px) {
        .form-card {
            padding: 20px 15px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .action-item {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
</style>

<div class="form-container">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <span>❌</span>
            <span><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <span><?php echo h($success); ?></span>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="<?php echo $action === 'view' ? 'view-mode' : ''; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <!-- اطلاعات اصلی جلسه -->
        <div class="form-card">
            <div class="form-header">
                <h2>
                    <span>🤝</span>
                    <?php echo $pageTitle; ?>
                </h2>
                <?php if ($meeting): ?>
                    <div style="color: #999; font-size: 14px;">
                        شماره: <?php echo h($meeting['meeting_number']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-section">
                <div class="section-title">
                    <span>📋</span>
                    <span>اطلاعات اصلی</span>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">عنوان جلسه</label>
                        <input type="text" name="title" 
                               value="<?php echo h($meeting['title'] ?? ''); ?>" 
                               required <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>نوع جلسه</label>
                        <select name="type" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                            <option value="">انتخاب کنید</option>
                            <option value="board" <?php echo ($meeting['type'] ?? '') === 'board' ? 'selected' : ''; ?>>هیئت مدیره</option>
                            <option value="management" <?php echo ($meeting['type'] ?? '') === 'management' ? 'selected' : ''; ?>>مدیریت</option>
                            <option value="technical" <?php echo ($meeting['type'] ?? '') === 'technical' ? 'selected' : ''; ?>>فنی</option>
                            <option value="project" <?php echo ($meeting['type'] ?? '') === 'project' ? 'selected' : ''; ?>>پروژه</option>
                            <option value="general" <?php echo ($meeting['type'] ?? '') === 'general' ? 'selected' : ''; ?>>عمومی</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>وضعیت</label>
                        <select name="status" <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                            <option value="scheduled" <?php echo ($meeting['status'] ?? 'scheduled') === 'scheduled' ? 'selected' : ''; ?>>برنامه‌ریزی شده</option>
                            <option value="in_progress" <?php echo ($meeting['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>در حال برگزاری</option>
                            <option value="completed" <?php echo ($meeting['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>برگزار شده</option>
                            <option value="cancelled" <?php echo ($meeting['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">تاریخ جلسه</label>
                        <input type="date" name="meeting_date" 
                               value="<?php echo h($meeting['meeting_date'] ?? ''); ?>" 
                               required <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">ساعت شروع</label>
                        <input type="time" name="meeting_time" 
                               value="<?php echo h($meeting['meeting_time'] ?? ''); ?>" 
                               required <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>مدت زمان (دقیقه)</label>
                        <input type="number" name="duration_minutes" 
                               value="<?php echo h($meeting['duration_minutes'] ?? ''); ?>" 
                               min="0" <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>محل برگزاری</label>
                        <input type="text" name="location" 
                               value="<?php echo h($meeting['location'] ?? ''); ?>" 
                               placeholder="اتاق جلسات، آنلاین، ..." 
                               <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>دستور جلسه</label>
                        <textarea name="agenda" <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($meeting['agenda'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- شرکت‌کنندگان -->
        <div class="form-card">
            <div class="form-section">
                <div class="section-title">
                    <span>👥</span>
                    <span>شرکت‌کنندگان</span>
                </div>
                
                <div class="attendees-selector">
                    <?php 
                    $selectedAttendees = $meeting['attendees'] ?? [];
                    foreach ($users as $user): 
                    ?>
                        <div class="attendee-item">
                            <input type="checkbox" 
                                   name="attendees[]" 
                                   value="<?php echo $user['id']; ?>"
                                   id="attendee_<?php echo $user['id']; ?>"
                                   <?php echo in_array($user['id'], $selectedAttendees) ? 'checked' : ''; ?>
                                   <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                            <label for="attendee_<?php echo $user['id']; ?>" class="attendee-info">
                                <div class="attendee-name"><?php echo h($user['fullname']); ?></div>
                                <div class="attendee-email"><?php echo h($user['email']); ?></div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- صورتجلسه (فقط برای جلسات برگزار شده) -->
        <?php if ($action !== 'add'): ?>
        <div class="form-card">
            <div class="form-section">
                <div class="section-title">
                    <span>📝</span>
                    <span>صورتجلسه</span>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>خلاصه جلسه</label>
                        <textarea name="minutes" <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($meeting['minutes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>تصمیمات اتخاذ شده</label>
                        <textarea name="decisions" <?php echo $action === 'view' ? 'readonly' : ''; ?>><?php echo h($meeting['decisions'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- اقدامات بعدی -->
        <div class="form-card">
            <div class="form-section">
                <div class="section-title">
                    <span>✅</span>
                    <span>اقدامات بعدی</span>
                </div>
                
                <div class="action-items-list" id="actionItemsList">
                    <?php 
                    $actionItems = $meeting['action_items'] ?? [];
                    if (empty($actionItems)):
                        $actionItems = [['task' => '', 'responsible' => '', 'deadline' => '']];
                    endif;
                    
                    foreach ($actionItems as $index => $item): 
                    ?>
                    <div class="action-item">
                        <input type="text" 
                               name="action_items[<?php echo $index; ?>][task]" 
                               value="<?php echo h($item['task'] ?? ''); ?>"
                               placeholder="شرح اقدام..." 
                               <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        
                        <select name="action_items[<?php echo $index; ?>][responsible]" 
                                <?php echo $action === 'view' ? 'disabled' : ''; ?>>
                            <option value="">مسئول...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo ($item['responsible'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($user['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="date" 
                               name="action_items[<?php echo $index; ?>][deadline]" 
                               value="<?php echo h($item['deadline'] ?? ''); ?>"
                               <?php echo $action === 'view' ? 'readonly' : ''; ?>>
                        
                        <?php if ($action !== 'view'): ?>
                        <button type="button" class="btn-remove-item" onclick="removeActionItem(this)">🗑️</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($action !== 'view'): ?>
                <button type="button" class="btn-add-item" onclick="addActionItem()">
                    ➕ افزودن اقدام
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- دکمه‌های عملیات -->
        <div class="form-card">
            <div class="form-actions">
                <a href="meetings.php" class="btn btn-secondary">
                    ⬅️ بازگشت
                </a>
                
                <?php if ($action !== 'view'): ?>
                    <button type="submit" class="btn btn-primary">
                        💾 ذخیره
                    </button>
                <?php else: ?>
                    <a href="meeting.php?action=edit&id=<?php echo $meetingId; ?>" class="btn btn-primary">
                        ✏️ ویرایش
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
    let actionItemIndex = <?php echo count($actionItems ?? [1]); ?>;
    
    function addActionItem() {
        const list = document.getElementById('actionItemsList');
        const item = document.createElement('div');
        item.className = 'action-item';
        item.innerHTML = `
            <input type="text" name="action_items[${actionItemIndex}][task]" placeholder="شرح اقدام...">
            <select name="action_items[${actionItemIndex}][responsible]">
                <option value="">مسئول...</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>"><?php echo h($user['fullname']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="action_items[${actionItemIndex}][deadline]">
            <button type="button" class="btn-remove-item" onclick="removeActionItem(this)">🗑️</button>
        `;
        list.appendChild(item);
        actionItemIndex++;
    }
    
    function removeActionItem(btn) {
        if (confirm('آیا از حذف این اقدام اطمینان دارید؟')) {
            btn.parentElement.remove();
        }
    }
</script>

<?php require_once 'footer.php'; ?>