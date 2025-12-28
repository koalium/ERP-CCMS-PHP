<?php
/**
 * صفحه گزارشات جامع
 */

require_once 'config.php';
require_once 'dbc.php';

$pageTitle = 'گزارشات جامع';
require_once 'header.php';

check_login();
?>

<style>
    .reports-container {
        max-width: 1600px;
        margin: 0 auto;
    }
    
    .reports-header {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(79, 172, 254, 0.3);
    }
    
    .reports-header h1 {
        font-size: 32px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .filter-panel {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-group label {
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
        font-size: 14px;
    }
    
    .filter-group select,
    .filter-group input {
        padding: 10px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .btn-generate {
        padding: 12px 30px;
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
    }
    
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .chart-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .chart-header {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .chart-canvas {
        width: 100% !important;
        height: 300px !important;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .summary-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-right: 5px solid #4facfe;
    }
    
    .summary-label {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .summary-value {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .summary-change {
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .summary-change.positive {
        color: #4caf50;
    }
    
    .summary-change.negative {
        color: #f44336;
    }
    
    @media (max-width: 768px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="reports-container">
    <!-- Reports Header -->
    <div class="reports-header">
        <h1>
            <span>📈</span>
            <span>گزارشات و تحلیل‌ها</span>
        </h1>
        <p>مشاهده گزارشات جامع و تحلیل داده‌های سیستم</p>
    </div>
    
    <!-- Filter Panel -->
    <div class="filter-panel">
        <div class="filter-row">
            <div class="filter-group">
                <label>نوع گزارش</label>
                <select id="reportType">
                    <option value="all">همه گزارش‌ها</option>
                    <option value="financial">مالی</option>
                    <option value="projects">پروژه‌ها</option>
                    <option value="hr">منابع انسانی</option>
                    <option value="operations">عملیاتی</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>از تاریخ</label>
                <input type="date" id="dateFrom">
            </div>
            
            <div class="filter-group">
                <label>تا تاریخ</label>
                <input type="date" id="dateTo">
            </div>
            
            <div class="filter-group">
                <button class="btn-generate" onclick="generateReports()">
                    📊 تولید گزارش
                </button>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">کل درآمد ماه جاری</div>
            <div class="summary-value" id="totalIncome">-</div>
            <div class="summary-change positive" id="incomeChange">
                <span>↑</span>
                <span>+۱۲٪ نسبت به ماه قبل</span>
            </div>
        </div>
        
        <div class="summary-card" style="border-right-color: #f44336;">
            <div class="summary-label">کل هزینه ماه جاری</div>
            <div class="summary-value" id="totalExpense">-</div>
            <div class="summary-change negative" id="expenseChange">
                <span>↓</span>
                <span>-۵٪ نسبت به ماه قبل</span>
            </div>
        </div>
        
        <div class="summary-card" style="border-right-color: #4caf50;">
            <div class="summary-label">تراز مالی</div>
            <div class="summary-value" id="balance">-</div>
            <div class="summary-change positive">
                <span>✓</span>
                <span>وضعیت مناسب</span>
            </div>
        </div>
        
        <div class="summary-card" style="border-right-color: #ff9800;">
            <div class="summary-label">پروژه‌های فعال</div>
            <div class="summary-value" id="activeProjects">-</div>
            <div class="summary-change">
                <span>📊</span>
                <span>در حال اجرا</span>
            </div>
        </div>
    </div>
    
    <!-- Charts Grid -->
    <div class="charts-grid">
        <!-- Financial Trend Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <span>💰</span>
                <span>روند مالی ۶ ماه اخیر</span>
            </div>
            <canvas id="financialTrendChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Projects by Status -->
        <div class="chart-card">
            <div class="chart-header">
                <span>📊</span>
                <span>پروژه‌ها بر اساس وضعیت</span>
            </div>
            <canvas id="projectsChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Monthly Income/Expense -->
        <div class="chart-card">
            <div class="chart-header">
                <span>📈</span>
                <span>درآمد و هزینه ماهانه</span>
            </div>
            <canvas id="monthlyChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Tasks by Priority -->
        <div class="chart-card">
            <div class="chart-header">
                <span>✅</span>
                <span>وظایف بر اساس اولویت</span>
            </div>
            <canvas id="tasksChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- User Activity -->
        <div class="chart-card">
            <div class="chart-header">
                <span>👥</span>
                <span>فعالیت کاربران (۷ روز)</span>
            </div>
            <canvas id="activityChart" class="chart-canvas"></canvas>
        </div>
        
        <!-- Module Usage -->
        <div class="chart-card">
            <div class="chart-header">
                <span>📱</span>
                <span>استفاده از ماژول‌ها</span>
            </div>
            <canvas id="moduleChart" class="chart-canvas"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // تنظیمات پیش‌فرض Chart.js برای RTL
    Chart.defaults.font.family = 'Tahoma, Arial';
    
    let charts = {};
    
    // بارگذاری داده‌های اولیه
    window.addEventListener('load', function() {
        loadChart('financialTrendChart', 'financial_trend', 'line');
        loadChart('projectsChart', 'projects_by_status', 'doughnut');
        loadChart('monthlyChart', 'monthly_income_expense', 'bar');
        loadChart('tasksChart', 'tasks_by_priority', 'pie');
        loadChart('activityChart', 'user_activity', 'line');
        loadChart('moduleChart', 'module_usage', 'bar');
        
        // بارگذاری آمار
        loadSummary();
    });
    
    function loadChart(canvasId, dataType, chartType) {
        fetch(`dashboard_charts.php?type=${dataType}`)
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById(canvasId).getContext('2d');
                
                if (charts[canvasId]) {
                    charts[canvasId].destroy();
                }
                
                charts[canvasId] = new Chart(ctx, {
                    type: chartType,
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                rtl: true
                            }
                        },
                        scales: chartType === 'line' || chartType === 'bar' ? {
                            y: {
                                beginAtZero: true
                            }
                        } : undefined
                    }
                });
            })
            .catch(error => console.error('Error loading chart:', error));
    }
    
    function loadSummary() {
        // در نسخه واقعی از API دریافت می‌شود
        // فعلاً از داده‌های ثابت استفاده می‌کنیم
        document.getElementById('totalIncome').textContent = '۱۲,۵۰۰,۰۰۰ ریال';
        document.getElementById('totalExpense').textContent = '۸,۳۰۰,۰۰۰ ریال';
        document.getElementById('balance').textContent = '۴,۲۰۰,۰۰۰ ریال';
        document.getElementById('activeProjects').textContent = '۱۵';
    }
    
    function generateReports() {
        const reportType = document.getElementById('reportType').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        console.log('Generating report:', reportType, dateFrom, dateTo);
        
        // بارگذاری مجدد نمودارها با فیلترهای جدید
        Object.keys(charts).forEach(chartId => {
            const dataType = chartId.replace('Chart', '').replace('Chart', '');
            const chartType = charts[chartId].config.type;
            loadChart(chartId, dataType, chartType);
        });
        
        loadSummary();
        
        alert('گزارش با موفقیت تولید شد!');
    }
</script>

<?php require_once 'footer.php'; ?>