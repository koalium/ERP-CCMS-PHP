/**
 * کتابخانه تقویم شمسی جلالی
 * Jalali Date Picker Library
 */

// تبدیل تاریخ میلادی به شمسی
function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    let jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    let gy2 = (gm > 2) ? (gy + 1) : gy;
    let days = (365 * gy) + (parseInt((gy2 + 3) / 4)) - (parseInt((gy2 + 99) / 100)) + 
               (parseInt((gy2 + 399) / 400)) - 80 + gd + g_d_m[gm - 1];
    jy += 33 * (parseInt(days / 12053));
    days %= 12053;
    jy += 4 * (parseInt(days / 1461));
    days %= 1461;
    jy += parseInt((days - 1) / 365);
    if (days > 365) days = (days - 1) % 365;
    
    let jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
    let jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
    
    return [jy, jm, jd];
}

// تبدیل تاریخ شمسی به میلادی
function jalaliToGregorian(jy, jm, jd) {
    let gy = (jy <= 979) ? 621 : 1600;
    jy -= (jy <= 979) ? 0 : 979;
    let days = (365 * jy) + ((parseInt(jy / 33)) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 
               78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy += 400 * (parseInt(days / 146097));
    days %= 146097;
    
    let leap = true;
    if (days >= 36525) {
        days--;
        gy += 100 * (parseInt(days / 36524));
        days %= 36524;
        if (days >= 365) days++;
        else leap = false;
    }
    
    gy += 4 * (parseInt(days / 1461));
    days %= 1461;
    gy += parseInt((days - 1) / 365);
    if (days > 365) days = (days - 1) % 365;
    
    let sal_a = [0, 31, ((leap || ((gy % 100 !== 0) && (gy % 4 === 0))) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 0;
    while (gm < 13 && days > sal_a[gm]) days -= sal_a[gm++];
    
    return [gy, gm, days];
}

// تبدیل اعداد به فارسی
function toPersianDigits(str) {
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return String(str).replace(/\d/g, x => persianDigits[x]);
}

// نام ماه‌های شمسی
const jalaliMonths = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
];

// نام روزهای هفته
const weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
const weekDaysFull = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

// کلاس DatePicker
class JalaliDatePicker {
    constructor(inputElement, options = {}) {
        this.input = typeof inputElement === 'string' ? 
                     document.querySelector(inputElement) : inputElement;
        
        if (!this.input) return;
        
        this.options = {
            minDate: options.minDate || null,
            maxDate: options.maxDate || null,
            format: options.format || 'YYYY/MM/DD',
            placeholder: options.placeholder || 'انتخاب تاریخ',
            onChange: options.onChange || null,
            ...options
        };
        
        this.selectedDate = null;
        this.currentMonth = null;
        this.currentYear = null;
        this.isOpen = false;
        
        this.init();
    }
    
    init() {
        // تنظیم placeholder
        this.input.placeholder = this.options.placeholder;
        this.input.readOnly = true;
        this.input.style.cursor = 'pointer';
        
        // تاریخ امروز
        const today = new Date();
        const [jy, jm, jd] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
        this.currentYear = jy;
        this.currentMonth = jm;
        
        // بررسی مقدار اولیه
        if (this.input.value) {
            this.parseInputValue();
        }
        
        // ایجاد تقویم
        this.createCalendar();
        
        // رویدادها
        this.attachEvents();
    }
    
    parseInputValue() {
        const parts = this.input.value.split('/');
        if (parts.length === 3) {
            this.selectedDate = {
                year: parseInt(parts[0]),
                month: parseInt(parts[1]),
                day: parseInt(parts[2])
            };
            this.currentYear = this.selectedDate.year;
            this.currentMonth = this.selectedDate.month;
        }
    }
    
    createCalendar() {
        // ایجاد wrapper
        this.calendar = document.createElement('div');
        this.calendar.className = 'jalali-datepicker';
        this.calendar.style.display = 'none';
        
        // هدر تقویم
        const header = document.createElement('div');
        header.className = 'jalali-datepicker-header';
        
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'jalali-datepicker-nav jalali-datepicker-prev';
        prevBtn.innerHTML = '◀';
        prevBtn.onclick = () => this.prevMonth();
        
        const title = document.createElement('div');
        title.className = 'jalali-datepicker-title';
        this.titleElement = title;
        
        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'jalali-datepicker-nav jalali-datepicker-next';
        nextBtn.innerHTML = '▶';
        nextBtn.onclick = () => this.nextMonth();
        
        header.appendChild(nextBtn);
        header.appendChild(title);
        header.appendChild(prevBtn);
        
        // روزهای هفته
        const weekDaysRow = document.createElement('div');
        weekDaysRow.className = 'jalali-datepicker-weekdays';
        weekDays.forEach(day => {
            const dayCell = document.createElement('div');
            dayCell.textContent = day;
            weekDaysRow.appendChild(dayCell);
        });
        
        // روزهای ماه
        this.daysContainer = document.createElement('div');
        this.daysContainer.className = 'jalali-datepicker-days';
        
        // دکمه امروز
        const todayBtn = document.createElement('button');
        todayBtn.type = 'button';
        todayBtn.className = 'jalali-datepicker-today';
        todayBtn.textContent = 'امروز';
        todayBtn.onclick = () => this.selectToday();
        
        this.calendar.appendChild(header);
        this.calendar.appendChild(weekDaysRow);
        this.calendar.appendChild(this.daysContainer);
        this.calendar.appendChild(todayBtn);
        
        // افزودن به DOM
        document.body.appendChild(this.calendar);
        
        this.renderCalendar();
    }
    
    renderCalendar() {
        // به‌روزرسانی عنوان
        this.titleElement.textContent = `${jalaliMonths[this.currentMonth - 1]} ${toPersianDigits(this.currentYear)}`;
        
        // پاک کردن روزها
        this.daysContainer.innerHTML = '';
        
        // محاسبه روز اول ماه
        const [gy, gm, gd] = jalaliToGregorian(this.currentYear, this.currentMonth, 1);
        const firstDay = new Date(gy, gm - 1, gd).getDay();
        const startDay = firstDay === 6 ? 0 : firstDay + 1;
        
        // تعداد روزهای ماه
        const daysInMonth = this.currentMonth <= 6 ? 31 : 
                           this.currentMonth <= 11 ? 30 : 
                           this.isLeapYear(this.currentYear) ? 30 : 29;
        
        // روزهای خالی
        for (let i = 0; i < startDay; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'jalali-datepicker-day empty';
            this.daysContainer.appendChild(emptyDay);
        }
        
        // روزهای ماه
        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'jalali-datepicker-day';
            dayCell.textContent = toPersianDigits(day);
            dayCell.dataset.day = day;
            
            // امروز
            const today = new Date();
            const [ty, tm, td] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
            if (this.currentYear === ty && this.currentMonth === tm && day === td) {
                dayCell.classList.add('today');
            }
            
            // انتخاب شده
            if (this.selectedDate && 
                this.selectedDate.year === this.currentYear && 
                this.selectedDate.month === this.currentMonth && 
                this.selectedDate.day === day) {
                dayCell.classList.add('selected');
            }
            
            dayCell.onclick = () => this.selectDate(day);
            
            this.daysContainer.appendChild(dayCell);
        }
    }
    
    isLeapYear(year) {
        const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
        let jp = breaks[0];
        let jump = 0;
        
        for (let i = 1; i < breaks.length; i++) {
            const jm = breaks[i];
            jump = jm - jp;
            if (year < jm) break;
            jp = jm;
        }
        
        let n = year - jp;
        if (jump - n < 6) n = n - jump + (parseInt(jump / 33)) * 33;
        
        let leap = ((n + 1) % 33) - 1;
        if (leap === -1) leap = 32;
        
        return (leap % 4 === 0);
    }
    
    prevMonth() {
        this.currentMonth--;
        if (this.currentMonth < 1) {
            this.currentMonth = 12;
            this.currentYear--;
        }
        this.renderCalendar();
    }
    
    nextMonth() {
        this.currentMonth++;
        if (this.currentMonth > 12) {
            this.currentMonth = 1;
            this.currentYear++;
        }
        this.renderCalendar();
    }
    
    selectDate(day) {
        this.selectedDate = {
            year: this.currentYear,
            month: this.currentMonth,
            day: day
        };
        
        const formattedDate = `${this.currentYear}/${String(this.currentMonth).padStart(2, '0')}/${String(day).padStart(2, '0')}`;
        this.input.value = formattedDate;
        
        if (this.options.onChange) {
            this.options.onChange(formattedDate, this.selectedDate);
        }
        
        this.close();
    }
    
    selectToday() {
        const today = new Date();
        const [jy, jm, jd] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
        this.currentYear = jy;
        this.currentMonth = jm;
        this.selectDate(jd);
    }
    
    attachEvents() {
        // کلیک روی input
        this.input.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });
        
        // کلیک بیرون از تقویم
        document.addEventListener('click', (e) => {
            if (!this.calendar.contains(e.target) && e.target !== this.input) {
                this.close();
            }
        });
    }
    
    toggle() {
        this.isOpen ? this.close() : this.open();
    }
    
    open() {
        const rect = this.input.getBoundingClientRect();
        this.calendar.style.display = 'block';
        this.calendar.style.position = 'absolute';
        this.calendar.style.top = (rect.bottom + window.scrollY + 5) + 'px';
        this.calendar.style.left = rect.left + 'px';
        this.calendar.style.zIndex = '9999';
        this.isOpen = true;
        
        this.renderCalendar();
    }
    
    close() {
        this.calendar.style.display = 'none';
        this.isOpen = false;
    }
    
    destroy() {
        if (this.calendar && this.calendar.parentNode) {
            this.calendar.parentNode.removeChild(this.calendar);
        }
    }
}

// استایل پیش‌فرض
const style = document.createElement('style');
style.textContent = `
.jalali-datepicker {
    background: white;
    border: 2px solid #667eea;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    padding: 15px;
    width: 320px;
    font-family: Tahoma, Arial, sans-serif;
    direction: rtl;
}

.jalali-datepicker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.jalali-datepicker-nav {
    background: #667eea;
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s;
}

.jalali-datepicker-nav:hover {
    background: #764ba2;
    transform: scale(1.1);
}

.jalali-datepicker-title {
    font-weight: bold;
    font-size: 16px;
    color: #333;
}

.jalali-datepicker-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 10px;
    text-align: center;
    font-weight: bold;
    color: #667eea;
}

.jalali-datepicker-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 10px;
}

.jalali-datepicker-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.jalali-datepicker-day:not(.empty):hover {
    background: #e3f2fd;
    transform: scale(1.1);
}

.jalali-datepicker-day.today {
    background: #fff3e0;
    font-weight: bold;
    color: #f57c00;
}

.jalali-datepicker-day.selected {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: bold;
}

.jalali-datepicker-day.empty {
    cursor: default;
}

.jalali-datepicker-today {
    width: 100%;
    padding: 10px;
    background: #4caf50;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s;
}

.jalali-datepicker-today:hover {
    background: #388e3c;
    transform: translateY(-2px);
}
`;
document.head.appendChild(style);

// تابع راحتی برای مقداردهی اولیه
function initJalaliDatePickers() {
    document.querySelectorAll('.jalali-date-input').forEach(input => {
        new JalaliDatePicker(input);
    });
}

// اجرا خودکار در صورت بارگذاری DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initJalaliDatePickers);
} else {
    initJalaliDatePickers();
}