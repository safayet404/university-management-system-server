<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── General ──────────────────────────────────────
            ['group' => 'general', 'key' => 'university_name',     'value' => 'UniCore University',              'type' => 'string',  'label' => 'University Name',         'is_public' => true],
            ['group' => 'general', 'key' => 'university_short',    'value' => 'UCU',                             'type' => 'string',  'label' => 'Short Name / Abbreviation','is_public' => true],
            ['group' => 'general', 'key' => 'university_tagline',  'value' => 'Excellence in Education',         'type' => 'string',  'label' => 'Tagline',                 'is_public' => true],
            ['group' => 'general', 'key' => 'university_address',  'value' => 'Dhaka, Bangladesh',               'type' => 'string',  'label' => 'Address',                 'is_public' => true],
            ['group' => 'general', 'key' => 'university_email',    'value' => 'info@unicore.edu',                'type' => 'string',  'label' => 'Contact Email',           'is_public' => true],
            ['group' => 'general', 'key' => 'university_phone',    'value' => '+880-2-1234567',                  'type' => 'string',  'label' => 'Contact Phone',           'is_public' => true],
            ['group' => 'general', 'key' => 'university_website',  'value' => 'https://unicore.edu',             'type' => 'string',  'label' => 'Website',                 'is_public' => true],
            ['group' => 'general', 'key' => 'established_year',    'value' => '2005',                            'type' => 'string',  'label' => 'Established Year',        'is_public' => true],
            ['group' => 'general', 'key' => 'timezone',            'value' => 'Asia/Dhaka',                      'type' => 'string',  'label' => 'Timezone',                'is_public' => false],
            ['group' => 'general', 'key' => 'date_format',         'value' => 'd M Y',                           'type' => 'string',  'label' => 'Date Format',             'is_public' => false],
            ['group' => 'general', 'key' => 'currency',            'value' => 'BDT',                             'type' => 'string',  'label' => 'Currency',                'is_public' => true],
            ['group' => 'general', 'key' => 'currency_symbol',     'value' => '৳',                               'type' => 'string',  'label' => 'Currency Symbol',         'is_public' => true],

            // ── Academic ─────────────────────────────────────
            ['group' => 'academic', 'key' => 'current_semester',    'value' => 'Spring 2025',   'type' => 'string',  'label' => 'Current Semester',         'is_public' => true],
            ['group' => 'academic', 'key' => 'current_academic_year','value' => '2024-2025',    'type' => 'string',  'label' => 'Current Academic Year',    'is_public' => true],
            ['group' => 'academic', 'key' => 'max_credit_load',     'value' => '21',            'type' => 'integer', 'label' => 'Max Credit Load Per Semester', 'is_public' => false],
            ['group' => 'academic', 'key' => 'min_attendance_pct',  'value' => '75',            'type' => 'integer', 'label' => 'Minimum Attendance %',     'is_public' => true],
            ['group' => 'academic', 'key' => 'passing_grade_point', 'value' => '2.0',           'type' => 'string',  'label' => 'Minimum Passing GPA',      'is_public' => true],
            ['group' => 'academic', 'key' => 'grading_scale',       'value' => 'A+=4.0,A=3.75,A-=3.5,B+=3.25,B=3.0,B-=2.75,C+=2.5,C=2.25,D=2.0,F=0.0', 'type' => 'string', 'label' => 'Grading Scale', 'is_public' => true],
            ['group' => 'academic', 'key' => 'registration_open',   'value' => '1',             'type' => 'boolean', 'label' => 'Course Registration Open', 'is_public' => true],
            ['group' => 'academic', 'key' => 'result_publication',  'value' => '1',             'type' => 'boolean', 'label' => 'Allow Result Publication', 'is_public' => false],

            // ── Security ─────────────────────────────────────
            ['group' => 'security', 'key' => 'min_password_length', 'value' => '8',    'type' => 'integer', 'label' => 'Minimum Password Length',    'is_public' => false],
            ['group' => 'security', 'key' => 'session_timeout',     'value' => '120',  'type' => 'integer', 'label' => 'Session Timeout (minutes)',  'is_public' => false],
            ['group' => 'security', 'key' => 'max_login_attempts',  'value' => '5',    'type' => 'integer', 'label' => 'Max Login Attempts',         'is_public' => false],
            ['group' => 'security', 'key' => 'require_2fa',         'value' => '0',    'type' => 'boolean', 'label' => 'Require Two-Factor Auth',    'is_public' => false],
            ['group' => 'security', 'key' => 'password_expiry_days','value' => '0',    'type' => 'integer', 'label' => 'Password Expiry (days, 0=never)', 'is_public' => false],
            ['group' => 'security', 'key' => 'allow_registration',  'value' => '0',    'type' => 'boolean', 'label' => 'Allow Public Registration',  'is_public' => false],

            // ── Notifications ─────────────────────────────────
            ['group' => 'notifications', 'key' => 'notify_enrollment',  'value' => '1', 'type' => 'boolean', 'label' => 'Enrollment Notifications'],
            ['group' => 'notifications', 'key' => 'notify_fee_due',     'value' => '1', 'type' => 'boolean', 'label' => 'Fee Due Reminders'],
            ['group' => 'notifications', 'key' => 'notify_exam_results','value' => '1', 'type' => 'boolean', 'label' => 'Exam Result Notifications'],
            ['group' => 'notifications', 'key' => 'notify_attendance',  'value' => '1', 'type' => 'boolean', 'label' => 'Low Attendance Alerts'],
            ['group' => 'notifications', 'key' => 'notify_admission',   'value' => '1', 'type' => 'boolean', 'label' => 'Admission Status Updates'],
            ['group' => 'notifications', 'key' => 'notify_library',     'value' => '1', 'type' => 'boolean', 'label' => 'Library Return Reminders'],
            ['group' => 'notifications', 'key' => 'fee_reminder_days',  'value' => '7', 'type' => 'integer', 'label' => 'Fee Reminder Days Before Due'],
            ['group' => 'notifications', 'key' => 'attendance_threshold','value' => '75','type' => 'integer','label' => 'Attendance Alert Threshold %'],

            // ── Appearance ────────────────────────────────────
            ['group' => 'appearance', 'key' => 'primary_color',    'value' => '#4f46e5', 'type' => 'string',  'label' => 'Primary Color',       'is_public' => true],
            ['group' => 'appearance', 'key' => 'sidebar_style',    'value' => 'default', 'type' => 'string',  'label' => 'Sidebar Style',       'is_public' => false],
            ['group' => 'appearance', 'key' => 'items_per_page',   'value' => '15',      'type' => 'integer', 'label' => 'Items Per Page',      'is_public' => false],
            ['group' => 'appearance', 'key' => 'show_breadcrumbs', 'value' => '1',       'type' => 'boolean', 'label' => 'Show Breadcrumbs',    'is_public' => false],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(['key' => $s['key']], array_merge($s, ['is_public' => $s['is_public'] ?? false]));
        }

        $this->command->info('✅ SettingsSeeder: ' . count($settings) . ' settings created');
    }
}
