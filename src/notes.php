<?php
/**
 * یادداشت‌های شخصی و مشترک
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

$userId = $_SESSION['user_id'];

// پیام‌ها
$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = show_message('یادداشت با موفقیت افزوده شد.', 'success');
            break;
        case 'updated':
            $message = show_message('یادداشت با موفقیت به‌روزرسانی شد.', 'success');
            break;
        case 'deleted':
            $message = show_message('یادداشت با موفقیت حذف شد.', 'success');
            break;
    }
}

// حذف سریع یادداشت
if (isset($_GET['delete']) && isset($_GET['csrf'])) {
    if (verify_csrf_token($_GET['csrf'])) {
        $deleteId = (int)$_GET['delete'];
        
        // فقط یادداشت‌های خودش را می‌تواند حذف کند
        $note = db()->selectOne(
            "SELECT id FROM notes WHERE id = :id AND user_id = :user_id",
            [':id' => $deleteId, ':user_id' => $userId]
        );
        
        if ($note) {
            db()->delete('notes', 'id = :id', [':id' => $deleteId]);
            
            db()->insert('logs', [
                'user_id' => $userId,
                'action' => 'delete_note',
                'module' => 'notes',
                'record_id' => $deleteId,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            redirect(SITE_URL . '/notes.php?msg=deleted');
        }
    }
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$filterType = sanitize_input($_GET['filter'] ?? 'my'); // my, shared, all
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT n.*, 
        u.fullname as creator_name
        FROM notes n
        LEFT JOIN users u ON u.id = n.user_id
        WHERE 1=1";

$params = [];

// فیلتر بر اساس نوع
if ($filterType === 'my') {
    $sql .= " AND n.user_id = :user_id";
    $params[':user_id'] = $userId;
} elseif ($filterType === 'shared') {
    $sql .= " AND (n.shared_with LIKE :user_search OR n.user_id = :user_id2) AND n.is_private = 0";
    $params[':user_search'] = '%"' . $userId . '"%';
    $params[':user_id2'] = $userId;
}

if ($search) {
    $sql .= " AND (n.title LIKE :search OR n.content LIKE :search OR n.tags LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($category) {
    $sql .= " AND n.category = :category";
    $params[':category'] = $category;
}

$sql .= " ORDER BY n.updated_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$notes = $result['data'];
$totalPages = $result['total_pages'];

// دریافت دسته‌بندی‌ها
$categories = db()->select(
    "SELECT DISTINCT category 
     FROM notes 
     WHERE user_id = :user_id AND category IS NOT NULL AND category != '' 
     ORDER BY category",
    [':user_id' => $userId]
);

// آمار
$stats = db()->selectOne(
    "SELECT 
        COUNT(*) as my_notes,
        SUM(CASE WHEN is_private = 0 THEN 1 ELSE 0 END) as shared_notes
     FROM notes 
     WHERE user_id = :user_id",
    [':user_id' => $userId]
);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>یادداشت‌های من - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            font-size: 36px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        .stat-icon.my { background: #e3f2fd; }
        .stat-icon.shared { background: #f3e5f5; }
        
        .stat-content h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #666;
            font-size: 14px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .filter-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .filter-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            color: #555;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
        }
        
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .note-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .note-card.private {
            border-right: 4px solid #f44336;
        }
        
        .note-card.shared {
            border-right: 4px solid #4caf50;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .note-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .note-meta {
            font-size: 12px;
            color: #999;
        }
        
        .note-actions {
            display: flex;
            gap: 5px;
        }
        
        .note-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: #f5f5f5;
            color: #666;
        }
        
        .note-btn:hover {
            background: #e0e0e0;
        }
        
        .note-btn.edit:hover {
            background: #2196f3;
            color: white;
        }
        
        .note-btn.delete:hover {
            background: #f44336;
            color: white;
        }
        
        .note-content {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .note-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .note-category {
            display: inline-block;
            padding: 4px 10px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 12px;
            font-size: 11px;
        }
        
        .note-tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .note-tag {
            padding: 3px 8px;
            background: #f3e5f5;
            color: #7b1fa2;
            border-radius: 10px;
            font-size: 11px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .no-data p {
            font-size: 18px;
            margin-top: 20px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
        }
        
        .page-link {
            padding: 8px 15px;
            border: 2px solid #667eea;
            border-radius: 6px;
            color: #667eea;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .page-link:hover,
        .page-link.active {
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .notes-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-tabs {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 یادداشت‌های من</h1>
            <a href="note.php?action=add" class="btn btn-primary">
                ➕ یادداشت جدید
            </a>
        </div>
        
        <?php echo $message; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon my">📌</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['my_notes'] ?? 0); ?></h3>
                    <p>یادداشت‌های من</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon shared">👥</div>
                <div class="stat-content">
                    <h3><?php echo en2fa($stats['shared_notes'] ?? 0); ?></h3>
                    <p>اشتراک‌گذاری شده</p>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-tabs">
                <a href="?filter=my" class="filter-tab <?php echo $filterType === 'my' ? 'active' : ''; ?>">
                    یادداشت‌های من
                </a>
                <a href="?filter=shared" class="filter-tab <?php echo $filterType === 'shared' ? 'active' : ''; ?>">
                    اشتراک‌گذاری شده
                </a>
                <a href="?filter=all" class="filter-tab <?php echo $filterType === 'all' ? 'active' : ''; ?>">
                    همه
                </a>
            </div>
            
            <form method="GET" action="">
                <input type="hidden" name="filter" value="<?php echo h($filterType); ?>">
                
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="عنوان، محتوا، برچسب..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>دسته‌بندی</label>
                    <select name="category">
                        <option value="">همه</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo h($cat['category']); ?>" 
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo h($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <?php if (count($notes) > 0): ?>
            <div class="notes-grid">
                <?php foreach ($notes as $note): ?>
                    <div class="note-card <?php echo $note['is_private'] ? 'private' : 'shared'; ?>"
                         onclick="window.location='note.php?action=view&id=<?php echo $note['id']; ?>'">
                        <div class="note-header">
                            <div>
                                <div class="note-title"><?php echo h($note['title']); ?></div>
                                <div class="note-meta">
                                    <?php echo h($note['creator_name']); ?> • 
                                    <?php echo en2fa(date('Y/m/d H:i', strtotime($note['updated_at']))); ?>
                                </div>
                            </div>
                            <div class="note-actions" onclick="event.stopPropagation();">
                                <a href="note.php?action=edit&id=<?php echo $note['id']; ?>" 
                                   class="note-btn edit" title="ویرایش">✏️</a>
                                <a href="?delete=<?php echo $note['id']; ?>&csrf=<?php echo generate_csrf_token(); ?>" 
                                   class="note-btn delete" title="حذف"
                                   onclick="return confirm('آیا از حذف این یادداشت اطمینان دارید؟')">🗑️</a>
                            </div>
                        </div>
                        
                        <div class="note-content">
                            <?php echo nl2br(h(mb_substr($note['content'], 0, 200))); ?>
                            <?php if (mb_strlen($note['content']) > 200): ?>...<?php endif; ?>
                        </div>
                        
                        <div class="note-footer">
                            <div>
                                <?php if ($note['category']): ?>
                                    <span class="note-category"><?php echo h($note['category']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="note-tags">
                                <?php if ($note['tags']): ?>
                                    <?php foreach (explode(',', $note['tags']) as $tag): ?>
                                        <?php if (trim($tag)): ?>
                                            <span class="note-tag">#<?php echo h(trim($tag)); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filterType); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" 
                           class="page-link">قبلی</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filterType); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo en2fa($i); ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filterType); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" 
                           class="page-link">بعدی</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-data">
                📝
                <p>هیچ یادداشتی یافت نشد.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'footer.php'; ?>