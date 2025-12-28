<?php
/**
 * فیش حقوقی
 */

require_once 'config.php';
require_once 'dbc.php';

check_login();

$salaryId = (int)($_GET['id'] ?? 0);

if ($salaryId <= 0) {
    die('شناسه حقوق نامعتبر است');
}

// دریافت اطلاعات حقوق
$salary = db()->selectOne(
    "SELECT s.*, e.employee_code, c.name, c.national_id, e.position, e.department, e.employment_date,
     si.base_salary as original_base, si.housing_allowance, si.transportation_allowance, 
     si.food_allowance, si.family_allowance, si.other_allowances,
     si.insurance_deduction, si.tax_deduction, si.other_deductions
     FROM hr_monthly_salaries s
     INNER JOIN hr_employees e ON e.id = s.employee_id
     INNER JOIN contacts c ON c.id = e.contact_id
     LEFT JOIN hr_salary_info si ON si.employee_id = e.id AND si.effective_date <= CONCAT(s.year, '-', LPAD(s.month, 2, '0'), '-01')
     WHERE s.id = :id
     ORDER BY si.effective_date DESC
     LIMIT 1",
    [':id' => $salaryId]
);

if (!$salary) {
    die('فیش حقوقی یافت نشد');
}

// نام ماه فارسی
$monthNames = [
    1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
    4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
    7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
    10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
];
$monthName = $monthNames[$salary['month']] ?? $salary['month'];

// محاسبه وام کسر شده
$loanDeduction = 0;
$loanInfo = db()->selectOne(
    "SELECT l.amount, l.monthly_amount, l.remaining_amount
     FROM hr_loans l
     WHERE l.employee_id = :emp AND l.status = 'active'
     LIMIT 1",
    [':emp' => $salary['employee_id']]
);

if ($loanInfo) {
    $loanDeduction = (float)$loanInfo['monthly_amount'];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فیش حقوقی - <?php echo en2fa($monthName); ?> <?php echo en2fa($salary['year']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            background: white;
            padding: 20px;
        }
        
        .payslip {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #333;
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #333;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .payslip-title {
            font-size: 20px;
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        
        .period {
            font-size: 16px;
            color: #666;
        }
        
        .employee-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-item {
            display: flex;
            gap: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            min-width: 120px;
        }
        
        .info-value {
            color: #333;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .salary-table th {
            background: #333;
            color: white;
            padding: 12px;
            text-align: right;
            font-weight: bold;
        }
        
        .salary-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .salary-table tr:hover {
            background: #f8f9fa;
        }
        
        .amount {
            text-align: left;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        
        .section-title {
            background: #555;
            color: white;
            padding: 8px 12px;
            font-weight: bold;
        }
        
        .subtotal-row {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .total-row {
            background: #333;
            color: white;
            font-size: 16px;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #333;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            padding-top: 10px;
            font-size: 14px;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.3);
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .print-btn {
                display: none;
            }
            
            .payslip {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ چاپ فیش</button>
    
    <div class="payslip">
        <!-- هدر -->
        <div class="header">
            <div class="company-name">شرکت eSmartis</div>
            <div class="payslip-title">فیش حقوق و مزایا</div>
            <div class="period">
                دوره: <?php echo en2fa($monthName); ?> ماه <?php echo en2fa($salary['year']); ?>
            </div>
        </div>
        
        <!-- اطلاعات کارمند -->
        <div class="employee-info">
            <div class="info-item">
                <div class="info-label">نام و نام خانوادگی:</div>
                <div class="info-value"><?php echo h($salary['name']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">کد پرسنلی:</div>
                <div class="info-value"><?php echo h($salary['employee_code']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">کد ملی:</div>
                <div class="info-value"><?php echo en2fa($salary['national_id']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">سمت:</div>
                <div class="info-value"><?php echo h($salary['position']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">واحد سازمانی:</div>
                <div class="info-value"><?php echo h($salary['department']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">تاریخ استخدام:</div>
                <div class="info-value"><?php echo en2fa(date('Y/m/d', strtotime($salary['employment_date']))); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">روزهای کاری:</div>
                <div class="info-value"><?php echo en2fa($salary['working_days']); ?> روز</div>
            </div>
            <div class="info-item">
                <div class="info-label">غیبت:</div>
                <div class="info-value"><?php echo en2fa($salary['absent_days']); ?> روز</div>
            </div>
        </div>
        
        <!-- جدول حقوق و مزایا -->
        <table class="salary-table">
            <tr class="section-title">
                <td colspan="2">حقوق و مزایا</td>
            </tr>
            
            <tr>
                <td>حقوق پایه</td>
                <td class="amount"><?php echo en2fa(number_format($salary['base_salary'])); ?> ریال</td>
            </tr>
            
            <?php if ($salary['housing_allowance'] > 0): ?>
            <tr>
                <td>حق مسکن</td>
                <td class="amount"><?php echo en2fa(number_format($salary['housing_allowance'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salary['transportation_allowance'] > 0): ?>
            <tr>
                <td>حق ایاب و ذهاب</td>
                <td class="amount"><?php echo en2fa(number_format($salary['transportation_allowance'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salary['food_allowance'] > 0): ?>
            <tr>
                <td>حق غذا</td>
                <td class="amount"><?php echo en2fa(number_format($salary['food_allowance'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salary['family_allowance'] > 0): ?>
            <tr>
                <td>حق عائله‌مندی</td>
                <td class="amount"><?php echo en2fa(number_format($salary['family_allowance'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salary['other_allowances'] > 0): ?>
            <tr>
                <td>سایر مزایا</td>
                <td class="amount"><?php echo en2fa(number_format($salary['other_allowances'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salary['overtime_amount'] > 0): ?>
            <tr>
                <td>اضافه کاری (<?php echo en2fa(number_format($salary['overtime_hours'], 1)); ?> ساعت)</td>
                <td class="amount"><?php echo en2fa(number_format($salary['overtime_amount'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <tr class="subtotal-row">
                <td>جمع حقوق و مزایا</td>
                <td class="amount"><?php echo en2fa(number_format($salary['gross_salary'])); ?> ریال</td>
            </tr>
        </table>
        
        <!-- کسورات -->
        <table class="salary-table">
            <tr class="section-title">
                <td colspan="2">کسورات</td>
            </tr>
            
            <?php if ($salary['insurance_deduction'] > 0): ?>
            <tr>
                <td>بیمه تأمین اجتماعی (7%)</td>
                <td class="amount"><?php echo en2fa(number_format($salary['insurance_deduction'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salary['tax_deduction'] > 0): ?>
            <tr>
                <td>مالیات حقوق</td>
                <td class="amount"><?php echo en2fa(number_format($salary['tax_deduction'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($loanDeduction > 0): ?>
            <tr>
                <td>کسر وام (مانده: <?php echo en2fa(number_format($loanInfo['remaining_amount'])); ?> ریال)</td>
                <td class="amount"><?php echo en2fa(number_format($loanDeduction)); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <?php if ($salary['other_deductions'] > 0): ?>
            <tr>
                <td>سایر کسورات</td>
                <td class="amount"><?php echo en2fa(number_format($salary['other_deductions'])); ?> ریال</td>
            </tr>
            <?php endif; ?>
            
            <tr class="subtotal-row">
                <td>جمع کسورات</td>
                <td class="amount"><?php echo en2fa(number_format($salary['total_deductions'])); ?> ریال</td>
            </tr>
        </table>
        
        <!-- مبلغ خالص -->
        <table class="salary-table">
            <tr class="total-row">
                <td>خالص پرداختی</td>
                <td class="amount"><?php echo en2fa(number_format($salary['net_salary'])); ?> ریال</td>
            </tr>
        </table>
        
        <!-- امضاها -->
        <div class="footer">
            <div class="signature-box">
                <div>امضاء کارمند</div>
                <div class="signature-line"><?php echo h($salary['name']); ?></div>
            </div>
            <div class="signature-box">
                <div>امضاء مدیر منابع انسانی</div>
                <div class="signature-line">مهر و امضاء</div>
            </div>
        </div>
        
        <!-- توضیحات -->
        <div style="margin-top: 40px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #666;">
            <strong>توضیحات:</strong>
            <ul style="margin-top: 10px; margin-right: 20px;">
                <li>این فیش حقوقی توسط سیستم eSmartis تولید شده است</li>
                <li>در صورت هرگونه ابهام با واحد منابع انسانی تماس حاصل فرمایید</li>
                <li>تاریخ صدور: <?php echo en2fa(date('Y/m/d H:i')); ?></li>
            </ul>
        </div>
    </div>
</body>
</html>