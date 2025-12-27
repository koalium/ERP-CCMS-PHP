<?php
/**
 * مدیریت لیست کارهای روزانه (To-Do List)
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$userId = $_SESSION['user_id'];

// ایجاد جدول اگر وجود ندارد
$createTableSQL = "CREATE TABLE IF NOT EXISTS todos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(300) NOT NULL,
    description TEXT,
    due_date DATE,
    due_time TIME,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    category VARCHAR(50),
    is_completed TINYINT(1) DEFAULT 0,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_completed (user_id, is_completed),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
db()->query($createTableSQL);

// پردازش عملیات AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    $response = ['success' => false];
    
    if ($action === 'add' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $data = [
            'user_id' => $userId,
            'title' => sanitize_input($_POST['title'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'due_date' => sanitize_input($_POST['due_date'] ?? ''),
            'due_time' => sanitize_input($_POST['due_time'] ?? ''),
            'priority' => sanitize_input($_POST['priority'] ?? 'medium'),
            'category' => sanitize_input($_POST['category'] ?? '')
        ];
        
        $id = db()->insert('todos', $data);
        if ($id) {
            $response = ['success' => true, 'id' => $id, 'data' => $data];
        }
    } elseif ($action === 'toggle' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $todoId = (int)($_POST['id'] ?? 0);
        $todo = db()->selectOne("SELECT * FROM todos WHERE id = :id AND user_id = :uid", 
                                [':id' => $todoId, ':uid' => $userId]);
        
        if ($todo) {
            $newStatus = $todo['is_completed'] ? 0 : 1;
            $data = [
                'is_completed' => $newStatus,
                'completed_at' => $newStatus ? date('Y-m-d H:i:s') : null
            ];
            
            db()->update('todos', $data, 'id = :id', [':id' => $todoId]);
            $response = ['success' => true, 'completed' => $newStatus];
        }
    } elseif ($action === 'delete' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $todoId = (int)($_POST['id'] ?? 0);
        db()->delete('todos', 'id = :id AND user_id = :uid', [':id' => $todoId, ':uid' => $userId]);
        $response = ['success' => true];
    } elseif ($action === 'update' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $todoId = (int)($_POST['id'] ?? 0);
        $data = [
            'title' => sanitize_input($_POST['title'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'due_date' => sanitize_input($_POST['due_date'] ?? ''),
            'due_time' => sanitize_input($_POST['due_time'] ?? ''),
            'priority' => sanitize_input($_POST['priority'] ?? 'medium'),
            'category' => sanitize_input($_POST['category'] ?? '')
        ];
        
        db()->update('todos', $data, 'id = :id AND user_id = :uid', 
                    [':id' => $todoId, ':uid' => $userId]);
        $response = ['success' => true];
    }
    
    echo json_encode($response);
    exit;
}

// دریافت کارها
$filter = $_GET['filter'] ?? 'active';
$sql = "SELECT * FROM todos WHERE user_id = :user_id";
$params = [':user_id' => $userId];

if ($filter === 'active') {
    $sql .= " AND is_completed = 0";
} elseif ($filter === 'completed') {
    $sql .= " AND is_completed = 1";
}

$sql .= " ORDER BY 
    CASE 
        WHEN priority = 'urgent' THEN 1 
        WHEN priority = 'high' THEN 2 
        WHEN priority = 'medium' THEN 3 
        ELSE 4 
    END,
    due_date ASC,
    due_time ASC";

$todos = db()->select($sql, $params);

// آمار
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN is_completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM todos WHERE user_id = :user_id
", [':user_id' => $userId]);

$csrfToken = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لیست کارها - <?php echo SITE_TITLE; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 25px;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-box.completed {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-box.overdue {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .add-todo-form {
            background: white;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .form-row input {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-row input:focus {
            outline: none;
            border-color: #4facfe;
        }
        
        .form-row button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .form-row button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }
        
        .advanced-options {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .advanced-options.show {
            display: grid;
        }
        
        .advanced-options input,
        .advanced-options select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .toggle-advanced {
            background: transparent;
            border: none;
            color: #4facfe;
            cursor: pointer;
            font-size: 13px;
            padding: 0;
            margin-top: 5px;
        }
        
        .filters {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            font-family: Tahoma, Arial, sans-serif;
            text-decoration: none;
            color: #333;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            font-weight: bold;
        }
        
        .todos-list {
            background: white;
            padding: 0;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .todo-item {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            align-items: start;
            transition: background 0.2s;
        }
        
        .todo-item:hover {
            background: #f8f9fa;
        }
        
        .todo-item.completed {
            opacity: 0.6;
        }
        
        .todo-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            margin-top: 3px;
        }
        
        .todo-content {
            flex: 1;
        }
        
        .todo-title {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 8px;
            word-break: break-word;
        }
        
        .todo-item.completed .todo-title {
            text-decoration: line-through;
        }
        
        .todo-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .priority-urgent {
            background: #fee;
            color: #c33;
        }
        
        .priority-high {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-medium {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .priority-low {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .todo-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-delete {
            background: #fee;
            color: #c33;
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        .no-todos {
            padding: 60px 20px;
            text-align: center;
            color: #999;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-save {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .todo-item {
                grid-template-columns: auto 1fr;
            }
            
            .todo-actions {
                grid-column: 2;
                justify-content: flex-start;
            }
            
            .advanced-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ لیست کارهای من</h1>
            
            <div class="stats">
                <div class="stat-box">
                    <span class="stat-number"><?php echo en2fa($stats['active']); ?></span>
                    <span class="stat-label">فعال</span>
                </div>
                <div class="stat-box completed">
                    <span class="stat-number"><?php echo en2fa($stats['completed']); ?></span>
                    <span class="stat-label">انجام شده</span>
                </div>
                <div class="stat-box overdue">
                    <span class="stat-number"><?php echo en2fa($stats['overdue']); ?></span>
                    <span class="stat-label">عقب‌افتاده</span>
                </div>
            </div>
        </div>
        
        <div class="add-todo-form">
            <form id="addTodoForm">
                <div class="form-row">
                    <input type="text" id="quickTitle" placeholder="کار جدید اضافه کنید..." required>
                    <button type="submit">➕ افزودن</button>
                </div>
                <button type="button" class="toggle-advanced" onclick="toggleAdvanced()">
                    ⚙️ گزینه‌های پیشرفته
                </button>
                <div class="advanced-options" id="advancedOptions">
                    <input type="text" id="quickDate" class="jalali-date" placeholder="تاریخ">
                    <input type="time" id="quickTime" placeholder="ساعت">
                    <select id="quickPriority">
                        <option value="low">اولویت پایین</option>
                        <option value="medium" selected>اولویت متوسط</option>
                        <option value="high">اولویت بالا</option>
                        <option value="urgent">فوری</option>
                    </select>
                    <input type="text" id="quickCategory" placeholder="دسته‌بندی">
                </div>
            </form>
        </div>
        
        <div class="filters">
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">همه</a>
            <a href="?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">فعال</a>
            <a href="?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">انجام شده</a>
        </div>
        
        <div class="todos-list" id="todosList">
            <?php if (count($todos) > 0): ?>
                <?php foreach ($todos as $todo): ?>
                    <div class="todo-item <?php echo $todo['is_completed'] ? 'completed' : ''; ?>" data-id="<?php echo $todo['id']; ?>">
                        <input type="checkbox" class="todo-checkbox" 
                               <?php echo $todo['is_completed'] ? 'checked' : ''; ?>
                               onchange="toggleTodo(<?php echo $todo['id']; ?>)">
                        
                        <div class="todo-content">
                            <div class="todo-title"><?php echo h($todo['title']); ?></div>
                            <div class="todo-meta">
                                <?php if ($todo['due_date']): ?>
                                    <span class="badge">📅 <?php echo h($todo['due_date']); ?></span>
                                <?php endif; ?>
                                <?php if ($todo['due_time']): ?>
                                    <span class="badge">⏰ <?php echo h($todo['due_time']); ?></span>
                                <?php endif; ?>
                                <span class="badge priority-<?php echo $todo['priority']; ?>">
                                    <?php 
                                    $priorities = ['low' => 'پایین', 'medium' => 'متوسط', 'high' => 'بالا', 'urgent' => 'فوری'];
                                    echo $priorities[$todo['priority']];
                                    ?>
                                </span>
                                <?php if ($todo['category']): ?>
                                    <span class="badge">📂 <?php echo h($todo['category']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="todo-actions">
                            <button class="btn-icon btn-edit" onclick="editTodo(<?php echo $todo['id']; ?>)">✏️</button>
                            <button class="btn-icon btn-delete" onclick="deleteTodo(<?php echo $todo['id']; ?>)">🗑️</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-todos">
                    <h3>هیچ کاری یافت نشد</h3>
                    <p>یک کار جدید اضافه کنید</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal ویرایش -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ویرایش کار</h3>
                <button class="modal-close" onclick="closeEditModal()">×</button>
            </div>
            <form id="editForm">
                <input type="hidden" id="editId">
                <div class="form-group">
                    <label>عنوان</label>
                    <input type="text" id="editTitle" required>
                </div>
                <div class="form-group">
                    <label>توضیحات</label>
                    <textarea id="editDescription"></textarea>
                </div>
                <div class="form-group">
                    <label>تاریخ</label>
                    <input type="text" id="editDate" class="jalali-date">
                </div>
                <div class="form-group">
                    <label>ساعت</label>
                    <input type="time" id="editTime">
                </div>
                <div class="form-group">
                    <label>اولویت</label>
                    <select id="editPriority">
                        <option value="low">پایین</option>
                        <option value="medium">متوسط</option>
                        <option value="high">بالا</option>
                        <option value="urgent">فوری</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>دسته‌بندی</label>
                    <input type="text" id="editCategory">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-save">💾 ذخیره</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="jalali-datepicker.js"></script>
    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
        
        document.addEventListener('DOMContentLoaded', function() {
            initJalaliDatePicker('.jalali-date');
        });
        
        function toggleAdvanced() {
            document.getElementById('advancedOptions').classList.toggle('show');
        }
        
        // افزودن کار جدید
        document.getElementById('addTodoForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax_action', 'add');
            formData.append('csrf_token', csrfToken);
            formData.append('title', document.getElementById('quickTitle').value);
            formData.append('due_date', document.getElementById('quickDate').value);
            formData.append('due_time', document.getElementById('quickTime').value);
            formData.append('priority', document.getElementById('quickPriority').value);
            formData.append('category', document.getElementById('quickCategory').value);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                location.reload();
            }
        });
        
        // تغییر وضعیت
        async function toggleTodo(id) {
            const formData = new FormData();
            formData.append('ajax_action', 'toggle');
            formData.append('csrf_token', csrfToken);
            formData.append('id', id);
            
            await fetch('', {
                method: 'POST',
                body: formData
            });
            
            location.reload();
        }
        
        // حذف
        async function deleteTodo(id) {
            if (!confirm('آیا از حذف این کار اطمینان دارید؟')) return;
            
            const formData = new FormData();
            formData.append('ajax_action', 'delete');
            formData.append('csrf_token', csrfToken);
            formData.append('id', id);
            
            await fetch('', {
                method: 'POST',
                body: formData
            });
            
            location.reload();
        }
        
        // ویرایش
        function editTodo(id) {
            const todoItem = document.querySelector(`[data-id="${id}"]`);
            const title = todoItem.querySelector('.todo-title').textContent;
            
            document.getElementById('editId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax_action', 'update');
            formData.append('csrf_token', csrfToken);
            formData.append('id', document.getElementById('editId').value);
            formData.append('title', document.getElementById('editTitle').value);
            formData.append('description', document.getElementById('editDescription').value);
            formData.append('due_date', document.getElementById('editDate').value);
            formData.append('due_time', document.getElementById('editTime').value);
            formData.append('priority', document.getElementById('editPriority').value);
            formData.append('category', document.getElementById('editCategory').value);
            
            await fetch('', {
                method: 'POST',
                body: formData
            });
            
            location.reload();
        });
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>