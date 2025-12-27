/**
 * کتابخانه DatePicker فارسی جلالی
 * Persian Jalali DatePicker Library
 */

// تبدیل تاریخ میلادی به جلالی
function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    let jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    let gy2 = (gm > 2) ? (gy + 1) : gy;
    let days = (365 * gy) + (parseInt((gy2 + 3) / 4)) - (parseInt((gy2 + 99) / 100)) + (parseInt((gy2 + 399) / 400)) - 80 + gd + g_d_m[gm - 1];
    jy += 33 * (parseInt(days / 12053));
    days %= 12053;
    jy += 4 * (parseInt(days / 1461));
    days %= 1461;
    jy += parseInt((days - 1) / 365);
    if (days > 365) days = (days - 1) % 365;
    const jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
    const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
    return [jy, jm, jd];
}

// تبدیل تاریخ جلالی به میلادی
function jalaliToGregorian(jy, jm, jd) {
    let gy = (jy <= 979) ? 621 : 1600;
    jy -= (jy <= 979) ? 0 : 979;
    let days = (365 * jy) + ((parseInt(jy / 33)) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy += 400 * (parseInt(days / 146097));
    days %= 146097;
    let flag = (days >= 36525);
    if (flag) {
        days--;
        gy += 100 * (parseInt(days / 36524));
        days %= 36524;
        if (days >= 365) days++;
    }
    gy += 4 * (parseInt(days / 1461));
    days %= 1461;
    flag = (days >= 366);
    if (flag) {
        days--;
        gy += parseInt(days / 365);
        days %= 365;
    }
    const g_d_m = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm, gd;
    for (gm = 0; gm < 13 && days >= g_d_m[gm]; gm++) days -= g_d_m[gm];
    gd = days + 1;
    return [gy, gm, gd];
}

// تعداد روزهای ماه
function getJalaliMonthLength(year, month) {
    if (month <= 6) return 31;
    if (month <= 11) return 30;
    // بررسی سال کبیسه
    const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    let jp = breaks[0];
    let jump;
    for (let i = 1; i < breaks.length; i++) {
        const jm = breaks[i];
        jump = jm - jp;
        if (year < jm) break;
        jp = jm;
    }
    let n = year - jp;
    if (jump - n < 6) n = n - jump + (parseInt(jump / 33) * 33);
    let leap = (((n + 1) % 33 - 1) % 4 === 0);
    return leap ? 30 : 29;
}

// نام ماه‌های فارسی
const persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const persianWeekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
const persianWeekDaysFull = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

// تبدیل اعداد به فارسی
function toPersianDigits(num) {
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return num.toString().replace(/\d/g, d => persianDigits[d]);
}

// تبدیل اعداد فارسی به انگلیسی
function toEnglishDigits(str) {
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    let result = str;
    for (let i = 0; i < 10; i++) {
        result = result.replace(new RegExp(persianDigits[i], 'g'), i)
                       .replace(new RegExp(arabicDigits[i], 'g'), i);
    }
    return result;
}

class JalaliDatePicker {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = {
            format: 'YYYY/MM/DD',
            placeholder: 'تاریخ را انتخاب کنید',
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
        
        // ایجاد picker
        this.createPicker();
        
        // Event listeners
        this.input.addEventListener('click', () => this.toggle());
        document.addEventListener('click', (e) => {
            if (!this.picker.contains(e.target) && e.target !== this.input) {
                this.close();
            }
        });
        
        // تنظیم تاریخ اولیه
        const today = new Date();
        const [jy, jm, jd] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
        this.currentYear = jy;
        this.currentMonth = jm;
        
        // بررسی مقدار اولیه input
        if (this.input.value) {
            this.parseInputValue();
        }
    }
    
    createPicker() {
        this.picker = document.createElement('div');
        this.picker.className = 'jalali-datepicker';
        this.picker.style.cssText = `
            position: absolute;
            background: white;
            border: 2px solid #667eea;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 15px;
            z-index: 10000;
            display: none;
            min-width: 280px;
            font-family: Tahoma, Arial, sans-serif;
        `;
        
        document.body.appendChild(this.picker);
    }
    
    render() {
        const daysInMonth = getJalaliMonthLength(this.currentYear, this.currentMonth);
        const [gy, gm, gd] = jalaliToGregorian(this.currentYear, this.currentMonth, 1);
        const firstDayOfWeek = new Date(gy, gm - 1, gd).getDay();
        const adjustedFirstDay = (firstDayOfWeek + 1) % 7; // شنبه = 0
        
        let html = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <button type="button" class="jp-nav-btn jp-prev-year" style="background: #f0f0f0; border: none; border-radius: 6px; padding: 8px 12px; cursor: pointer; font-size: 16px;">«</button>
                <button type="button" class="jp-nav-btn jp-prev-month" style="background: #f0f0f0; border: none; border-radius: 6px; padding: 8px 12px; cursor: pointer; font-size: 16px;">‹</button>
                <div style="font-weight: bold; font-size: 14px; color: #333;">
                    ${persianMonths[this.currentMonth - 1]} ${toPersianDigits(this.currentYear)}
                </div>
                <button type="button" class="jp-nav-btn jp-next-month" style="background: #f0f0f0; border: none; border-radius: 6px; padding: 8px 12px; cursor: pointer; font-size: 16px;">›</button>
                <button type="button" class="jp-nav-btn jp-next-year" style="background: #f0f0f0; border: none; border-radius: 6px; padding: 8px 12px; cursor: pointer; font-size: 16px;">»</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; margin-bottom: 10px;">
        `;
        
        // نمایش نام روزهای هفته
        persianWeekDays.forEach(day => {
            html += `<div style="text-align: center; font-weight: bold; color: #666; font-size: 12px; padding: 5px;">${day}</div>`;
        });
        
        // روزهای خالی قبل از شروع ماه
        for (let i = 0; i < adjustedFirstDay; i++) {
            html += `<div></div>`;
        }
        
        // روزهای ماه
        for (let day = 1; day <= daysInMonth; day++) {
            const isSelected = this.selectedDate && 
                             this.selectedDate.year === this.currentYear && 
                             this.selectedDate.month === this.currentMonth && 
                             this.selectedDate.day === day;
            const isToday = (() => {
                const today = new Date();
                const [ty, tm, td] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
                return ty === this.currentYear && tm === this.currentMonth && td === day;
            })();
            
            const bgColor = isSelected ? '#667eea' : isToday ? '#e3f2fd' : 'white';
            const textColor = isSelected ? 'white' : '#333';
            const border = isToday && !isSelected ? '2px solid #667eea' : 'none';
            
            html += `
                <button type="button" class="jp-day" data-day="${day}" style="
                    background: ${bgColor};
                    color: ${textColor};
                    border: ${border};
                    border-radius: 8px;
                    padding: 8px;
                    cursor: pointer;
                    font-size: 13px;
                    transition: all 0.2s;
                    text-align: center;
                ">${toPersianDigits(day)}</button>
            `;
        }
        
        html += `
            </div>
            <div style="display: flex; justify-content: space-between; gap: 10px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                <button type="button" class="jp-today" style="flex: 1; background: #4caf50; color: white; border: none; border-radius: 6px; padding: 8px; cursor: pointer; font-size: 12px;">امروز</button>
                <button type="button" class="jp-clear" style="flex: 1; background: #f44336; color: white; border: none; border-radius: 6px; padding: 8px; cursor: pointer; font-size: 12px;">پاک کردن</button>
            </div>
        `;
        
        this.picker.innerHTML = html;
        this.attachEventListeners();
    }
    
    attachEventListeners() {
        // دکمه‌های ناوبری
        this.picker.querySelector('.jp-prev-year').addEventListener('click', () => {
            this.currentYear--;
            this.render();
        });
        
        this.picker.querySelector('.jp-next-year').addEventListener('click', () => {
            this.currentYear++;
            this.render();
        });
        
        this.picker.querySelector('.jp-prev-month').addEventListener('click', () => {
            this.currentMonth--;
            if (this.currentMonth < 1) {
                this.currentMonth = 12;
                this.currentYear--;
            }
            this.render();
        });
        
        this.picker.querySelector('.jp-next-month').addEventListener('click', () => {
            this.currentMonth++;
            if (this.currentMonth > 12) {
                this.currentMonth = 1;
                this.currentYear++;
            }
            this.render();
        });
        
        // انتخاب روز
        this.picker.querySelectorAll('.jp-day').forEach(btn => {
            btn.addEventListener('click', () => {
                const day = parseInt(btn.dataset.day);
                this.selectDate(this.currentYear, this.currentMonth, day);
            });
            
            btn.addEventListener('mouseenter', () => {
                if (!btn.style.background.includes('667eea')) {
                    btn.style.background = '#f0f0f0';
                }
            });
            
            btn.addEventListener('mouseleave', () => {
                if (!btn.style.background.includes('667eea') && !btn.style.border.includes('667eea')) {
                    btn.style.background = 'white';
                } else if (btn.style.border.includes('667eea')) {
                    btn.style.background = '#e3f2fd';
                }
            });
        });
        
        // دکمه امروز
        this.picker.querySelector('.jp-today').addEventListener('click', () => {
            const today = new Date();
            const [jy, jm, jd] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
            this.currentYear = jy;
            this.currentMonth = jm;
            this.selectDate(jy, jm, jd);
        });
        
        // دکمه پاک کردن
        this.picker.querySelector('.jp-clear').addEventListener('click', () => {
            this.input.value = '';
            this.selectedDate = null;
            this.close();
        });
    }
    
    selectDate(year, month, day) {
        this.selectedDate = { year, month, day };
        const [gy, gm, gd] = jalaliToGregorian(year, month, day);
        
        // فرمت خروجی
        const formatted = `${toPersianDigits(year)}/${toPersianDigits(String(month).padStart(2, '0'))}/${toPersianDigits(String(day).padStart(2, '0'))}`;
        this.input.value = formatted;
        
        // ذخیره مقدار میلادی در data attribute
        this.input.dataset.gregorian = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;
        
        // رویداد change
        const event = new Event('change', { bubbles: true });
        this.input.dispatchEvent(event);
        
        this.close();
    }
    
    parseInputValue() {
        const value = this.input.value.replace(/\s/g, '');
        if (!value) return;
        
        const cleaned = toEnglishDigits(value);
        const parts = cleaned.split('/');
        
        if (parts.length === 3) {
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]);
            const day = parseInt(parts[2]);
            
            if (year >= 1300 && year <= 1500 && month >= 1 && month <= 12 && day >= 1 && day <= 31) {
                this.selectedDate = { year, month, day };
                this.currentYear = year;
                this.currentMonth = month;
            }
        }
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        this.render();
        
        // محاسبه موقعیت
        const rect = this.input.getBoundingClientRect();
        this.picker.style.top = (rect.bottom + window.scrollY + 5) + 'px';
        this.picker.style.left = (rect.left + window.scrollX) + 'px';
        this.picker.style.display = 'block';
        
        this.isOpen = true;
    }
    
    close() {
        this.picker.style.display = 'none';
        this.isOpen = false;
    }
    
    destroy() {
        this.picker.remove();
    }
}

// تابع helper برای راحتی استفاده
function initJalaliDatePickers() {
    document.querySelectorAll('.jalali-date').forEach(input => {
        if (!input.jalaliPicker) {
            input.jalaliPicker = new JalaliDatePicker(input);
        }
    });
}

// اجرای خودکار بعد از load شدن DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initJalaliDatePickers);
} else {
    initJalaliDatePickers();
}