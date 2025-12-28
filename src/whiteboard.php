<?php
/**
 * وایت‌برد اشتراکی زنده
 * برای همکاری در گروه‌ها
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

$groupId = (int)($_GET['group_id'] ?? 0);

if ($groupId <= 0) {
    redirect(SITE_URL . '/messenger_advanced.php');
}

// بررسی عضویت در گروه
$group = db()->selectOne(
    "SELECT * FROM message_groups WHERE id = :id",
    [':id' => $groupId]
);

if (!$group) {
    redirect(SITE_URL . '/messenger_advanced.php');
}

$members = json_decode($group['members'] ?? '[]', true);
if (!in_array($_SESSION['user_id'], $members)) {
    die('شما عضو این گروه نیستید');
}

$pageTitle = 'وایت‌برد: ' . $group['name'];
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
        
        body {
            font-family: Tahoma, 'Iranian Sans', Arial, sans-serif;
            overflow: hidden;
            background: #f5f5f5;
        }
        
        .whiteboard-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        /* Header */
        .wb-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .wb-header-left {
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
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .wb-title h2 {
            font-size: 20px;
            margin-bottom: 3px;
        }
        
        .wb-title p {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .wb-header-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .online-users {
            display: flex;
            gap: -8px;
        }
        
        .user-circle {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 2px solid #667eea;
            font-size: 14px;
        }
        
        /* Toolbar */
        .toolbar {
            background: white;
            padding: 15px 25px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .tool-group {
            display: flex;
            gap: 8px;
            padding: 0 10px;
            border-left: 2px solid #e0e0e0;
        }
        
        .tool-group:first-child {
            padding-right: 0;
        }
        
        .tool-btn {
            width: 45px;
            height: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .tool-btn:hover {
            border-color: #667eea;
            background: #f0f2ff;
        }
        
        .tool-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        
        .color-picker {
            width: 45px;
            height: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            overflow: hidden;
        }
        
        .color-picker input {
            width: 100%;
            height: 100%;
            border: none;
            cursor: pointer;
        }
        
        .size-slider {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .size-slider input {
            width: 100px;
        }
        
        .size-display {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            min-width: 40px;
        }
        
        /* Canvas Container */
        .canvas-container {
            flex: 1;
            position: relative;
            background: white;
            overflow: auto;
        }
        
        #whiteboard {
            display: block;
            cursor: crosshair;
            background: white;
        }
        
        /* Participants Panel */
        .participants-panel {
            position: fixed;
            left: 20px;
            top: 100px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 15px;
            max-width: 250px;
            display: none;
        }
        
        .participants-panel.active {
            display: block;
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 5px;
        }
        
        .participant-item:hover {
            background: #f5f5f5;
        }
        
        .participant-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }
        
        .participant-name {
            font-size: 14px;
            color: #333;
        }
        
        .participant-status {
            margin-right: auto;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4caf50;
        }
        
        @media (max-width: 768px) {
            .toolbar {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .tool-group {
                padding: 0 5px;
            }
            
            .participants-panel {
                left: 10px;
                right: 10px;
                max-width: none;
            }
        }
    </style>
</head>
<body>

<div class="whiteboard-container">
    <!-- Header -->
    <div class="wb-header">
        <div class="wb-header-left">
            <button class="back-btn" onclick="window.location.href='chat_advanced.php?type=group&group_id=<?php echo $groupId; ?>'">⬅️</button>
            <div class="wb-title">
                <h2>🎨 <?php echo h($group['name']); ?></h2>
                <p>وایت‌برد اشتراکی - همکاری زنده</p>
            </div>
        </div>
        
        <div class="wb-header-right">
            <div class="online-users" id="onlineUsers">
                <div class="user-circle"><?php echo mb_substr($_SESSION['fullname'], 0, 1); ?></div>
            </div>
            <button class="back-btn" onclick="toggleParticipants()" title="شرکت‌کنندگان">👥</button>
            <button class="back-btn" onclick="clearCanvas()" title="پاک کردن">🗑️</button>
            <button class="back-btn" onclick="saveCanvas()" title="ذخیره">💾</button>
            <button class="back-btn" onclick="shareCanvas()" title="اشتراک‌گذاری">📤</button>
        </div>
    </div>
    
    <!-- Toolbar -->
    <div class="toolbar">
        <div class="tool-group">
            <button class="tool-btn active" data-tool="pen" onclick="selectTool('pen')" title="قلم">✏️</button>
            <button class="tool-btn" data-tool="eraser" onclick="selectTool('eraser')" title="پاک‌کن">🧹</button>
            <button class="tool-btn" data-tool="text" onclick="selectTool('text')" title="متن">📝</button>
        </div>
        
        <div class="tool-group">
            <button class="tool-btn" data-tool="line" onclick="selectTool('line')" title="خط">➖</button>
            <button class="tool-btn" data-tool="rectangle" onclick="selectTool('rectangle')" title="مستطیل">⬜</button>
            <button class="tool-btn" data-tool="circle" onclick="selectTool('circle')" title="دایره">⭕</button>
            <button class="tool-btn" data-tool="arrow" onclick="selectTool('arrow')" title="پیکان">➡️</button>
        </div>
        
        <div class="tool-group">
            <div class="color-picker">
                <input type="color" id="colorPicker" value="#667eea" onchange="changeColor(this.value)">
            </div>
        </div>
        
        <div class="tool-group">
            <div class="size-slider">
                <span>ضخامت:</span>
                <input type="range" id="sizeSlider" min="1" max="20" value="3" onchange="changeSize(this.value)">
                <span class="size-display" id="sizeDisplay">۳</span>
            </div>
        </div>
        
        <div class="tool-group">
            <button class="tool-btn" onclick="undo()" title="برگشت">↶</button>
            <button class="tool-btn" onclick="redo()" title="جلو">↷</button>
        </div>
        
        <div class="tool-group">
            <button class="tool-btn" onclick="uploadImage()" title="آپلود تصویر">🖼️</button>
            <input type="file" id="imageUpload" accept="image/*" style="display: none;" onchange="handleImageUpload(event)">
        </div>
    </div>
    
    <!-- Canvas -->
    <div class="canvas-container">
        <canvas id="whiteboard" width="1920" height="1080"></canvas>
    </div>
</div>

<!-- Participants Panel -->
<div class="participants-panel" id="participantsPanel">
    <h3 style="margin-bottom: 15px; color: #2c3e50;">شرکت‌کنندگان فعال</h3>
    <div id="participantsList">
        <div class="participant-item">
            <div class="participant-avatar"><?php echo mb_substr($_SESSION['fullname'], 0, 1); ?></div>
            <div class="participant-name"><?php echo h($_SESSION['fullname']); ?> (شما)</div>
            <div class="participant-status"></div>
        </div>
    </div>
</div>

<script>
    // متغیرهای سراسری
    const canvas = document.getElementById('whiteboard');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let currentTool = 'pen';
    let currentColor = '#667eea';
    let currentSize = 3;
    let startX, startY;
    let history = [];
    let historyStep = -1;
    
    // تنظیم اندازه کانوس
    function resizeCanvas() {
        const container = canvas.parentElement;
        canvas.width = Math.max(container.clientWidth, 1920);
        canvas.height = Math.max(container.clientHeight, 1080);
        
        if (history.length > 0 && historyStep >= 0) {
            restoreCanvas();
        }
    }
    
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    
    // انتخاب ابزار
    function selectTool(tool) {
        currentTool = tool;
        
        document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.querySelector(`[data-tool="${tool}"]`).classList.add('active');
        
        if (tool === 'eraser') {
            canvas.style.cursor = 'grab';
        } else {
            canvas.style.cursor = 'crosshair';
        }
    }
    
    // تغییر رنگ
    function changeColor(color) {
        currentColor = color;
    }
    
    // تغییر اندازه
    function changeSize(size) {
        currentSize = parseInt(size);
        const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const persianSize = size.toString().split('').map(d => persianNumbers[parseInt(d)]).join('');
        document.getElementById('sizeDisplay').textContent = persianSize;
    }
    
    // شروع رسم
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('touchstart', startDrawing);
    
    function startDrawing(e) {
        isDrawing = true;
        
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.touches[0].clientX) - rect.left;
        const y = (e.clientY || e.touches[0].clientY) - rect.top;
        
        startX = x;
        startY = y;
        
        if (currentTool === 'pen' || currentTool === 'eraser') {
            ctx.beginPath();
            ctx.moveTo(x, y);
        }
        
        if (currentTool === 'text') {
            const text = prompt('متن خود را وارد کنید:');
            if (text) {
                ctx.font = `${currentSize * 8}px Tahoma`;
                ctx.fillStyle = currentColor;
                ctx.textAlign = 'right';
                ctx.fillText(text, x, y);
                saveHistory();
            }
            isDrawing = false;
        }
    }
    
    // ادامه رسم
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('touchmove', draw);
    
    function draw(e) {
        if (!isDrawing) return;
        
        e.preventDefault();
        
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.touches[0].clientX) - rect.left;
        const y = (e.clientY || e.touches[0].clientY) - rect.top;
        
        if (currentTool === 'pen') {
            ctx.strokeStyle = currentColor;
            ctx.lineWidth = currentSize;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.lineTo(x, y);
            ctx.stroke();
        } else if (currentTool === 'eraser') {
            ctx.clearRect(x - currentSize/2, y - currentSize/2, currentSize * 3, currentSize * 3);
        }
    }
    
    // پایان رسم
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('touchend', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    function stopDrawing(e) {
        if (!isDrawing) return;
        
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || (e.changedTouches && e.changedTouches[0].clientX)) - rect.left;
        const y = (e.clientY || (e.changedTouches && e.changedTouches[0].clientY)) - rect.top;
        
        if (currentTool === 'line') {
            ctx.strokeStyle = currentColor;
            ctx.lineWidth = currentSize;
            ctx.beginPath();
            ctx.moveTo(startX, startY);
            ctx.lineTo(x, y);
            ctx.stroke();
        } else if (currentTool === 'rectangle') {
            ctx.strokeStyle = currentColor;
            ctx.lineWidth = currentSize;
            ctx.strokeRect(startX, startY, x - startX, y - startY);
        } else if (currentTool === 'circle') {
            ctx.strokeStyle = currentColor;
            ctx.lineWidth = currentSize;
            ctx.beginPath();
            const radius = Math.sqrt(Math.pow(x - startX, 2) + Math.pow(y - startY, 2));
            ctx.arc(startX, startY, radius, 0, 2 * Math.PI);
            ctx.stroke();
        } else if (currentTool === 'arrow') {
            drawArrow(startX, startY, x, y);
        }
        
        isDrawing = false;
        saveHistory();
    }
    
    // رسم فلش
    function drawArrow(fromX, fromY, toX, toY) {
        const headlen = 20;
        const angle = Math.atan2(toY - fromY, toX - fromX);
        
        ctx.strokeStyle = currentColor;
        ctx.lineWidth = currentSize;
        ctx.beginPath();
        ctx.moveTo(fromX, fromY);
        ctx.lineTo(toX, toY);
        ctx.lineTo(toX - headlen * Math.cos(angle - Math.PI / 6), toY - headlen * Math.sin(angle - Math.PI / 6));
        ctx.moveTo(toX, toY);
        ctx.lineTo(toX - headlen * Math.cos(angle + Math.PI / 6), toY - headlen * Math.sin(angle + Math.PI / 6));
        ctx.stroke();
    }
    
    // ذخیره تاریخچه
    function saveHistory() {
        historyStep++;
        if (historyStep < history.length) {
            history.length = historyStep;
        }
        history.push(canvas.toDataURL());
    }
    
    // بازگردانی کانوس
    function restoreCanvas() {
        if (historyStep >= 0 && historyStep < history.length) {
            const img = new Image();
            img.src = history[historyStep];
            img.onload = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0);
            };
        }
    }
    
    // برگشت
    function undo() {
        if (historyStep > 0) {
            historyStep--;
            restoreCanvas();
        }
    }
    
    // جلو
    function redo() {
        if (historyStep < history.length - 1) {
            historyStep++;
            restoreCanvas();
        }
    }
    
    // پاک کردن کانوس
    function clearCanvas() {
        if (confirm('آیا از پاک کردن وایت‌برد اطمینان دارید؟')) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            saveHistory();
        }
    }
    
    // ذخیره کانوس
    function saveCanvas() {
        const link = document.createElement('a');
        link.download = 'whiteboard_<?php echo date('YmdHis'); ?>.png';
        link.href = canvas.toDataURL();
        link.click();
    }
    
    // اشتراک‌گذاری
    function shareCanvas() {
        alert('تصویر در چت اشتراک‌گذاری می‌شود');
        // در نسخه کامل، تصویر به چت ارسال می‌شود
    }
    
    // آپلود تصویر
    function uploadImage() {
        document.getElementById('imageUpload').click();
    }
    
    function handleImageUpload(e) {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    ctx.drawImage(img, 50, 50, img.width * 0.5, img.height * 0.5);
                    saveHistory();
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
    
    // نمایش شرکت‌کنندگان
    function toggleParticipants() {
        document.getElementById('participantsPanel').classList.toggle('active');
    }
    
    // ذخیره اولیه
    saveHistory();
</script>

</body>
</html>