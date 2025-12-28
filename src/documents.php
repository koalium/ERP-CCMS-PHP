<?php
/**
 * سیستم مدیریت اسناد با کنترل نسخه
 * Document Management System with Version Control
 */

require_once 'config.php';
require_once 'dbc.php';
require_once 'header.php';

check_login();

if (!check_permission('documents', PERMISSION_READ)) {
    die('شما مجوز دسترسی به این بخش را ندارید.');
}

// پارامترهای جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$type = sanitize_input($_GET['type'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$status = sanitize_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ساخت کوئری
$sql = "SELECT d.*, 
        p.title as project_name, p.code as project_code,
        c.title as contract_title,
        u.fullname as uploaded_by_name,
        (SELECT COUNT(*) FROM document_versions WHERE document_id = d.id) as version_count,
        (SELECT MAX(version_number) FROM document_versions WHERE document_id = d.id) as latest_version,
        (SELECT status FROM document_versions WHERE document_id = d.id ORDER BY version_number DESC LIMIT 1) as version_status
        FROM documents d
        LEFT JOIN projects p ON p.id = d.project_id
        LEFT JOIN contracts c ON c.id = d.contract_id
        LEFT JOIN users u ON u.id = d.uploaded_by
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (d.title LIKE :search OR d.doc_number LIKE :search OR d.description LIKE :search OR d.tags LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($type) {
    $sql .= " AND d.type = :type";
    $params[':type'] = $type;
}

if ($category) {
    $sql .= " AND d.category = :category";
    $params[':category'] = $category;
}

if ($project_id > 0) {
    $sql .= " AND d.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

if ($status) {
    $sql .= " AND EXISTS (SELECT 1 FROM document_versions dv WHERE dv.document_id = d.id AND dv.status = :status ORDER BY dv.version_number DESC LIMIT 1)";
    $params[':status'] = $status;
}

$sql .= " ORDER BY d.updated_at DESC";

// دریافت داده‌ها با صفحه‌بندی
$result = db()->paginate($sql, $params, $page, $perPage);
$documents = $result['data'];
$totalPages = $result['total_pages'];

// دریافت لیست پروژه‌ها
$projects = db()->select("SELECT id, code, title FROM projects WHERE status != 'cancelled' ORDER BY created_at DESC LIMIT 50");

// دریافت دسته‌بندی‌ها
$categories = db()->select("SELECT DISTINCT category FROM documents WHERE category IS NOT NULL AND category != '' ORDER BY category");

// دریافت انواع اسناد
$types = db()->select("SELECT DISTINCT type FROM documents WHERE type IS NOT NULL AND type != '' ORDER BY type");

// آمار کلی
$stats = db()->selectOne("
    SELECT 
        COUNT(DISTINCT d.id) as total,
        SUM(CASE WHEN dv.status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN dv.status = 'review' THEN 1 ELSE 0 END) as review,
        SUM(CASE WHEN dv.status = 'approved' THEN 1 ELSE 0 END) as approved,
        COUNT(DISTINCT CASE WHEN d.is_public = 1 THEN d.id END) as public_docs,
        COUNT(DISTINCT dv.id) as total_versions
    FROM documents d
    LEFT JOIN (
        SELECT document_id, status, version_number,
               ROW_NUMBER() OVER (PARTITION BY document_id ORDER BY version_number DESC) as rn
        FROM document_versions
    ) dv ON dv.document_id = d.id AND dv.rn = 1
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت اسناد - <?php echo SITE_TITLE; ?></title>
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
            max-width: 1800px;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.draft { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-icon.review { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.approved { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-icon.public { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .stat-icon.versions { background: linear-gradient(135deg, #fa8bff 0%, #2bd2ff 0%, #2bff88 100%); }
        
        .stat-info h3 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #7f8c8d;
            font-size: 13px;
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
        
        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 13px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: Tahoma, Arial, sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th {
            padding: 15px 12px;
            text-align: right;
            font-weight: bold;
            font-size: 13px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9ff;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-draft {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-review {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-obsolete {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-superseded {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .version-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .doc-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .doc-title {
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .doc-number {
            color: #7f8c8d;
            font-size: 11px;
        }
        
        .file-icon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }
        
        .file-type {
            background: #f0f2f5;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
        }
        
        .file-type.pdf { background: #dc3545; color: white; }
        .file-type.doc, .file-type.docx { background: #2b579a; color: white; }
        .file-type.xls, .file-type.xlsx { background: #1d6f42; color: white; }
        .file-type.jpg, .file-type.png { background: #6f42c1; color: white; }
        .file-type.dwg, .file-type.dxf { background: #e83e8c; color: white; }
        
        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: #4caf50;
            color: white;
        }
        
        .btn-download {
            background: #2196f3;
            color: white;
        }
        
        .btn-versions {
            background: #ff9800;
            color: white;
        }
        
        .btn-edit {
            background: #9c27b0;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
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
            font-size: 13px;
        }
        
        .page-link:hover,
        .page-link.active {
            background: #667eea;
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-data svg {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .tag {
            display: inline-block;
            background: #e7f3ff;
            color: #0066cc;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            margin: 2px;
        }
        
        .visibility-badge {
            font-size: 18px;
            cursor: help;
        }
        
        .project-code {
            background: #e8eaf6;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: #3f51b5;
            font-size: 11px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-actions {
                flex-direction: column;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 1400px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                📄 مدیریت اسناد و مستندات
            </h1>
            <div class="header-actions">
                <?php if (check_permission('documents', PERMISSION_WRITE)): ?>
                    <a href="document.php?action=upload" class="btn btn-primary">
                        ⬆️ آپلود سند جدید
                    </a>
                    <a href="document.php?action=bulk_upload" class="btn btn-secondary">
                        📦 آپلود گروهی
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon total">📁</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['total']); ?></h3>
                    <p>کل اسناد</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon draft">📝</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['draft']); ?></h3>
                    <p>پیش‌نویس</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon review">🔍</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['review']); ?></h3>
                    <p>در حال بررسی</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">✅</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['approved']); ?></h3>
                    <p>تایید شده</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon public">🌐</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['public_docs']); ?></h3>
                    <p>عمومی</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon versions">🔄</div>
                <div class="stat-info">
                    <h3><?php echo en2fa($stats['total_versions']); ?></h3>
                    <p>کل نسخه‌ها</p>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="search" placeholder="عنوان، شماره، توضیحات، برچسب..." 
                           value="<?php echo h($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>نوع سند</label>
                    <select name="type">
                        <option value="">همه انواع</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo h($t['type']); ?>" 
                                    <?php echo $type === $t['type'] ? 'selected' : ''; ?>>
                                <?php echo h($t['type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>دسته‌بندی</label>
                    <select name="category">
                        <option value="">همه دسته‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo h($cat['category']); ?>" 
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo h($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>پروژه</label>
                    <select name="project_id">
                        <option value="">همه پروژه‌ها</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" 
                                    <?php echo $project_id == $project['id'] ? 'selected' : ''; ?>>
                                [<?php echo h($project['code']); ?>] <?php echo h($project['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">همه</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="review" <?php echo $status === 'review' ? 'selected' : ''; ?>>در حال بررسی</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                        <option value="obsolete" <?php echo $status === 'obsolete' ? 'selected' : ''; ?>>منسوخ شده</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (count($documents) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>عنوان / شماره</th>
                            <th>نوع</th>
                            <th>دسته‌بندی</th>
                            <th>پروژه</th>
                            <th>نسخه</th>
                            <th>فایل</th>
                            <th>وضعیت</th>
                            <th>دسترسی</th>
                            <th>برچسب‌ها</th>
                            <th>آپلودکننده</th>
                            <th>تاریخ بروزرسانی</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td>
                                    <div class="doc-info">
                                        <span class="doc-title"><?php echo h($doc['title']); ?></span>
                                        <?php if ($doc['doc_number']): ?>
                                            <span class="doc-number">شماره: <?php echo h($doc['doc_number']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #555;">
                                        <?php echo $doc['type'] ? h($doc['type']) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #555;">
                                        <?php echo $doc['category'] ? h($doc['category']) : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($doc['project_name']): ?>
                                        <span class="project-code"><?php echo h($doc['project_code']); ?></span>
                                        <br>
                                        <small style="font-size: 10px; color: #999;">
                                            <?php echo h(mb_substr($doc['project_name'], 0, 20)); ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="version-badge">
                                        🔄 v<?php echo en2fa($doc['latest_version'] ?? '1.0'); ?>
                                    </span>
                                    <br>
                                    <small style="font-size: 10px; color: #999;">
                                        <?php echo en2fa($doc['version_count']); ?> نسخه
                                    </small>
                                </td>
                                <td>
                                    <div class="file-icon">
                                        <?php
                                        $ext = pathinfo($doc['file_name'], PATHINFO_EXTENSION);
                                        ?>
                                        <span class="file-type <?php echo strtolower($ext); ?>">
                                            <?php echo strtoupper($ext); ?>
                                        </span>
                                        <span style="font-size: 11px; color: #999;">
                                            <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'draft' => 'پیش‌نویس',
                                        'review' => 'در حال بررسی',
                                        'approved' => 'تایید شده',
                                        'obsolete' => 'منسوخ شده',
                                        'superseded' => 'جایگزین شده'
                                    ];
                                    $currentStatus = $doc['version_status'] ?? 'draft';
                                    $statusClass = 'badge-' . str_replace('_', '_', $currentStatus);
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$currentStatus] ?? $currentStatus; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="visibility-badge" title="<?php echo $doc['is_public'] ? 'عمومی - قابل دسترسی برای همه' : 'خصوصی - دسترسی محدود'; ?>">
                                        <?php echo $doc['is_public'] ? '🌐' : '🔒'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($doc['tags']): ?>
                                        <?php
                                        $tags = array_slice(explode(',', $doc['tags']), 0, 3);
                                        foreach ($tags as $tag):
                                        ?>
                                            <span class="tag"><?php echo h(trim($tag)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count(explode(',', $doc['tags'])) > 3): ?>
                                            <span class="tag">...</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 12px;"><?php echo h($doc['uploaded_by_name']); ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 11px; color: #7f8c8d;">
                                        <?php echo en2fa(date('Y/m/d', strtotime($doc['updated_at']))); ?>
                                        <br>
                                        <?php echo en2fa(date('H:i', strtotime($doc['updated_at']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="document.php?action=view&id=<?php echo $doc['id']; ?>" 
                                           class="btn-sm btn-view" title="مشاهده">👁️</a>
                                        <a href="document.php?action=download&id=<?php echo $doc['id']; ?>" 
                                           class="btn-sm btn-download" title="دانلود">⬇️</a>
                                        <a href="document.php?action=versions&id=<?php echo $doc['id']; ?>" 
                                           class="btn-sm btn-versions" title="نسخه‌ها">📋</a>
                                        <?php if (check_permission('documents', PERMISSION_WRITE)): ?>
                                            <a href="document.php?action=edit&id=<?php echo $doc['id']; ?>" 
                                               class="btn-sm btn-edit" title="ویرایش">✏️</a>
                                        <?php endif; ?>
                                        <?php if (check_permission('documents', PERMISSION_FULL)): ?>
                                            <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                                    class="btn-sm btn-delete" title="حذف">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&category=<?php echo urlencode($category); ?>&project_id=<?php echo $project_id; ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link">❮ قبلی</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&category=<?php echo urlencode($category); ?>&project_id=<?php echo $project_id; ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo en2fa($i); ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&category=<?php echo urlencode($category); ?>&project_id=<?php echo $project_id; ?>&status=<?php echo urlencode($status); ?>" 
                               class="page-link">بعدی ❯</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                    </svg>
                    <h3>هیچ سندی یافت نشد</h3>
                    <p>برای آپلود سند جدید از دکمه بالای صفحه استفاده کنید.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function deleteDocument(id) {
            if (confirm('آیا از حذف این سند و تمام نسخه‌های آن اطمینان دارید؟\n\nتوجه: این عملیات قابل بازگشت نیست و تمام نسخه‌های سند نیز حذف خواهند شد.')) {
                if (confirm('آیا کاملاً مطمئن هستید؟ این سند برای همیشه حذف می‌شود.')) {
                    window.location.href = 'document.php?action=delete&id=' + id;
                }
            }
        }
    </script>
</body>
</html>

<?php require_once 'footer.php'; ?>