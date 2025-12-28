<?php
/**
 * API برای دریافت داده‌های نمودارها
 */

require_once 'config.php';
require_once 'dbc.php';

header('Content-Type: application/json; charset=utf-8');

check_login();

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'projects_by_status':
        echo json_encode(getProjectsByStatus());
        break;
    
    case 'financial_trend':
        echo json_encode(getFinancialTrend());
        break;
    
    case 'tasks_by_priority':
        echo json_encode(getTasksByPriority());
        break;
    
    case 'user_activity':
        echo json_encode(getUserActivity());
        break;
    
    case 'monthly_income_expense':
        echo json_encode(getMonthlyIncomeExpense());
        break;
    
    case 'module_usage':
        echo json_encode(getModuleUsage());
        break;
    
    default:
        echo json_encode(['error' => 'Invalid chart type']);
        break;
}

/**
 * پروژه‌ها بر اساس وضعیت
 */
function getProjectsByStatus() {
    $data = db()->select(
        "SELECT status, COUNT(*) as count FROM projects GROUP BY status"
    );
    
    $labels = [];
    $values = [];
    $colors = [
        'draft' => '#9e9e9e',
        'planning' => '#ff9800',
        'active' => '#4caf50',
        'on_hold' => '#ff5722',
        'completed' => '#2196f3',
        'cancelled' => '#f44336'
    ];
    
    foreach ($data as $row) {
        $labels[] = $row['status'];
        $values[] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [[
            'data' => $values,
            'backgroundColor' => array_values($colors)
        ]]
    ];
}

/**
 * روند مالی ۶ ماه اخیر
 */
function getFinancialTrend() {
    $data = db()->select(
        "SELECT 
         DATE_FORMAT(transaction_date, '%Y-%m') as month,
         SUM(CASE WHEN type = 'income' AND status = 'confirmed' THEN amount ELSE 0 END) as income,
         SUM(CASE WHEN type = 'expense' AND status = 'confirmed' THEN amount ELSE 0 END) as expense
         FROM transactions
         WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY month
         ORDER BY month"
    );
    
    $labels = [];
    $income = [];
    $expense = [];
    
    foreach ($data as $row) {
        $labels[] = $row['month'];
        $income[] = (float)$row['income'];
        $expense[] = (float)$row['expense'];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'درآمد',
                'data' => $income,
                'borderColor' => '#4caf50',
                'backgroundColor' => 'rgba(76, 175, 80, 0.1)'
            ],
            [
                'label' => 'هزینه',
                'data' => $expense,
                'borderColor' => '#f44336',
                'backgroundColor' => 'rgba(244, 67, 54, 0.1)'
            ]
        ]
    ];
}

/**
 * وظایف بر اساس اولویت
 */
function getTasksByPriority() {
    $data = db()->select(
        "SELECT priority, COUNT(*) as count 
         FROM tasks 
         WHERE status IN ('todo', 'in_progress')
         GROUP BY priority"
    );
    
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = $row['priority'];
        $values[] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [[
            'data' => $values,
            'backgroundColor' => ['#4caf50', '#ff9800', '#f44336', '#ff5722']
        ]]
    ];
}

/**
 * فعالیت کاربران ۷ روز اخیر
 */
function getUserActivity() {
    $data = db()->select(
        "SELECT DATE(created_at) as date, COUNT(*) as count
         FROM logs
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY date
         ORDER BY date"
    );
    
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = $row['date'];
        $values[] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'فعالیت',
            'data' => $values,
            'borderColor' => '#667eea',
            'backgroundColor' => 'rgba(102, 126, 234, 0.1)'
        ]]
    ];
}

/**
 * درآمد و هزینه ماهانه سال جاری
 */
function getMonthlyIncomeExpense() {
    $data = db()->select(
        "SELECT 
         MONTH(transaction_date) as month,
         SUM(CASE WHEN type = 'income' AND status = 'confirmed' THEN amount ELSE 0 END) as income,
         SUM(CASE WHEN type = 'expense' AND status = 'confirmed' THEN amount ELSE 0 END) as expense
         FROM transactions
         WHERE YEAR(transaction_date) = YEAR(CURDATE())
         GROUP BY month
         ORDER BY month"
    );
    
    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 
               'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    
    $income = array_fill(0, 12, 0);
    $expense = array_fill(0, 12, 0);
    
    foreach ($data as $row) {
        $index = (int)$row['month'] - 1;
        $income[$index] = (float)$row['income'];
        $expense[$index] = (float)$row['expense'];
    }
    
    return [
        'labels' => $months,
        'datasets' => [
            [
                'label' => 'درآمد',
                'data' => $income,
                'backgroundColor' => '#4caf50'
            ],
            [
                'label' => 'هزینه',
                'data' => $expense,
                'backgroundColor' => '#f44336'
            ]
        ]
    ];
}

/**
 * استفاده از ماژول‌ها
 */
function getModuleUsage() {
    $data = db()->select(
        "SELECT module, COUNT(*) as count
         FROM logs
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY module
         ORDER BY count DESC
         LIMIT 8"
    );
    
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = $row['module'];
        $values[] = (int)$row['count'];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [[
            'data' => $values,
            'backgroundColor' => [
                '#667eea', '#764ba2', '#f093fb', '#4facfe',
                '#43e97b', '#fa709a', '#30cfd0', '#a8edea'
            ]
        ]]
    ];
}
?>