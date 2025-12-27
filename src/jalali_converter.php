<?php
/**
 * تبدیل تاریخ میلادی به شمسی و برعکس
 * Jalali (Shamsi) Date Converter
 */

/**
 * تبدیل تاریخ میلادی به شمسی
 */
function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) +
            ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    $jy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

/**
 * تبدیل تاریخ شمسی به میلادی
 */
function jalaliToGregorian($jy, $jm, $jd) {
    $gy = ($jy <= 979) ? 621 : 1600;
    $jy -= ($jy <= 979) ? 0 : 979;
    $days = (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) +
            78 + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy += 400 * ((int)($days / 146097));
    $days %= 146097;
    $leap = true;
    if ($days >= 36525) {
        $days--;
        $gy += 100 * ((int)($days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
        else $leap = false;
    }
    $gy += 4 * ((int)($days / 1461));
    $days %= 1461;
    $gy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $sal_a = [0, 31, (($leap || (($gy % 100 != 0) && ($gy % 4 == 0))) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    while ($gm < 13 && $days > $sal_a[$gm]) $days -= $sal_a[$gm++];
    return [$gy, $gm, $days];
}

/**
 * تبدیل تاریخ میلادی به فرمت شمسی
 */
function gregorianToJalaliDate($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    
    $parts = explode(' ', $date);
    $datePart = $parts[0];
    $timePart = $parts[1] ?? '';
    
    list($gy, $gm, $gd) = explode('-', $datePart);
    list($jy, $jm, $jd) = gregorianToJalali((int)$gy, (int)$gm, (int)$gd);
    
    $result = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    
    if ($timePart) {
        $result .= ' ' . $timePart;
    }
    
    return $result;
}

/**
 * تبدیل تاریخ شمسی به فرمت میلادی
 */
function jalaliToGregorianDate($date) {
    if (empty($date)) {
        return null;
    }
    
    $parts = explode(' ', $date);
    $datePart = $parts[0];
    $timePart = $parts[1] ?? '';
    
    $dateParts = explode('/', $datePart);
    if (count($dateParts) !== 3) {
        return null;
    }
    
    list($jy, $jm, $jd) = $dateParts;
    list($gy, $gm, $gd) = jalaliToGregorian((int)$jy, (int)$jm, (int)$jd);
    
    $result = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    
    if ($timePart) {
        $result .= ' ' . $timePart;
    }
    
    return $result;
}

/**
 * نام ماه‌های شمسی
 */
function getJalaliMonthName($month) {
    $months = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند'
    ];
    return $months[$month] ?? '';
}

/**
 * نام روزهای هفته به فارسی
 */
function getPersianDayName($dayNumber) {
    $days = [
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنج‌شنبه',
        5 => 'جمعه',
        6 => 'شنبه'
    ];
    return $days[$dayNumber] ?? '';
}

/**
 * چک کردن سال کبیسه شمسی
 */
function isJalaliLeapYear($year) {
    $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    $jp = $breaks[0];
    $jump = 0;
    
    for ($i = 1; $i < count($breaks); $i++) {
        $jm = $breaks[$i];
        $jump = $jm - $jp;
        if ($year < $jm) break;
        $jp = $jm;
    }
    
    $n = $year - $jp;
    if ($jump - $n < 6) $n = $n - $jump + ((int)($jump / 33)) * 33;
    
    $leap = (($n + 1) % 33) - 1;
    if ($leap === -1) $leap = 32;
    
    return ($leap % 4 === 0);
}

/**
 * تعداد روزهای یک ماه شمسی
 */
function getJalaliMonthDays($month, $year) {
    if ($month <= 6) {
        return 31;
    } elseif ($month <= 11) {
        return 30;
    } else {
        return isJalaliLeapYear($year) ? 30 : 29;
    }
}

/**
 * فرمت کردن تاریخ شمسی
 */
function formatJalaliDate($date, $format = 'Y/m/d') {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    
    // اگر تاریخ میلادی است، ابتدا تبدیل کن
    if (strpos($date, '-') !== false) {
        $date = gregorianToJalaliDate($date);
    }
    
    $parts = explode(' ', $date);
    $datePart = $parts[0];
    $timePart = $parts[1] ?? '';
    
    list($y, $m, $d) = explode('/', $datePart);
    
    $replacements = [
        'Y' => $y,
        'y' => substr($y, -2),
        'm' => str_pad($m, 2, '0', STR_PAD_LEFT),
        'n' => $m,
        'd' => str_pad($d, 2, '0', STR_PAD_LEFT),
        'j' => $d,
        'F' => getJalaliMonthName((int)$m),
        'H' => substr($timePart, 0, 2),
        'i' => substr($timePart, 3, 2),
        's' => substr($timePart, 6, 2)
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $format);
}

/**
 * تاریخ امروز به شمسی
 */
function jalaliNow($format = 'Y/m/d H:i:s') {
    $now = new DateTime();
    list($jy, $jm, $jd) = gregorianToJalali(
        (int)$now->format('Y'),
        (int)$now->format('m'),
        (int)$now->format('d')
    );
    
    $jalaliDate = sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $now->format('H:i:s'));
    
    return formatJalaliDate($jalaliDate, $format);
}

/**
 * تاریخ امروز به شمسی (فقط تاریخ)
 */
function jalaliToday($format = 'Y/m/d') {
    return jalaliNow($format);
}
?>