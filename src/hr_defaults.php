<?php
/**
 * داده‌های پیش‌فرض منابع انسانی
 * این کد باید به dbdef.php اضافه شود
 */

/**
 * تزریق انواع مرخصی
 */
private function injectLeaveTypes() {
    $leaveTypes = [
        [
            'name' => 'مرخصی استحقاقی',
            'type_code' => 'annual',
            'days_per_year' => 26,
            'requires_approval' => 1,
            'is_paid' => 1,
            'max_consecutive_days' => 15,
            'description' => 'مرخصی سالانه مطابق قانون کار'
        ],
        [
            'name' => 'مرخصی استعلاجی',
            'type_code' => 'sick',
            'days_per_year' => 15,
            'requires_approval' => 1,
            'is_paid' => 1,
            'max_consecutive_days' => 0,
            'description' => 'مرخصی به دلیل بیماری (نیاز به گواهی پزشکی)'
        ],
        [
            'name' => 'مرخصی بدون حقوق',
            'type_code' => 'unpaid',
            'days_per_year' => 0,
            'requires_approval' => 1,
            'is_paid' => 0,
            'max_consecutive_days' => 0,
            'description' => 'مرخصی بدون دریافت حقوق'
        ],
        [
            'name' => 'مرخصی اضطراری',
            'type_code' => 'emergency',
            'days_per_year' => 5,
            'requires_approval' => 0,
            'is_paid' => 1,
            'max_consecutive_days' => 2,
            'description' => 'مرخصی فوری در شرایط اضطراری'
        ],
        [
            'name' => 'مرخصی زایمان',
            'type_code' => 'maternity',
            'days_per_year' => 210,
            'requires_approval' => 1,
            'is_paid' => 1,
            'max_consecutive_days' => 0,
            'description' => 'مرخصی زایمان (9 ماه قبل + 6 ماه بعد)'
        ],
        [
            'name' => 'مرخصی تحصیلی',
            'type_code' => 'study',
            'days_per_year' => 10,
            'requires_approval' => 1,
            'is_paid' => 1,
            'max_consecutive_days' => 0,
            'description' => 'مرخصی برای شرکت در امتحانات'
        ],
        [
            'name' => 'مرخصی فوت',
            'type_code' => 'bereavement',
            'days_per_year' => 3,
            'requires_approval' => 0,
            'is_paid' => 1,
            'max_consecutive_days' => 3,
            'description' => 'مرخصی به دلیل فوت نزدیکان'
        ],
        [
            'name' => 'مرخصی ازدواج',
            'type_code' => 'marriage',
            'days_per_year' => 3,
            'requires_approval' => 1,
            'is_paid' => 1,
            'max_consecutive_days' => 3,
            'description' => 'مرخصی به مناسبت ازدواج'
        ]
    ];
    
    foreach ($leaveTypes as $type) {
        if (!$this->db->exists('hr_leave_types', 'type_code = :code', [':code' => $type['type_code']])) {
            $this->db->insert('hr_leave_types', $type);
        }
    }
    
    echo "انواع مرخصی با موفقیت تزریق شدند.\n";
}

/**
 * تزریق دستگاه ساعت‌زنی نمونه
 */
private function injectAttendanceDevice() {
    $devices = [
        [
            'device_id' => 'DEVICE001',
            'device_name' => 'دستگاه ورودی اصلی',
            'device_type' => 'fingerprint',
            'ip_address' => '192.168.1.100',
            'port' => 4370,
            'location' => 'ورودی ساختمان',
            'is_active' => 1
        ]
    ];
    
    foreach ($devices as $device) {
        if (!$this->db->exists('hr_attendance_devices', 'device_id = :id', [':id' => $device['device_id']])) {
            $this->db->insert('hr_attendance_devices', $device);
        }
    }
    
    echo "دستگاه‌های ساعت‌زنی نمونه تزریق شدند.\n";
}

/**
 * فراخوانی تزریق داده‌های HR
 * این را در متد inject() اصلی قرار دهید:
 */
public function inject() {
    // ... کدهای قبلی ...
    
    $this->injectLeaveTypes();
    $this->injectAttendanceDevice();
    
    echo "تمام داده‌های پیش‌فرض با موفقیت تزریق شدند.\n";
}
?>