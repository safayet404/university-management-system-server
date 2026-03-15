<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin   = User::role('super-admin')->first();
        $users   = User::take(15)->get();
        $count   = 0;

        $templates = [
            ['type' => 'info',         'category' => 'enrollment',   'title' => 'Enrollment Approved',         'message' => 'Your enrollment in CSE301 has been approved for Spring 2025.', 'action_url' => '/enrollments', 'action_label' => 'View Enrollments'],
            ['type' => 'warning',      'category' => 'fee',          'title' => 'Fee Payment Due',             'message' => 'Your tuition fee payment of ৳15,000 is due by end of this month.', 'action_url' => '/fees', 'action_label' => 'Pay Now'],
            ['type' => 'success',      'category' => 'exam',         'title' => 'Exam Results Published',      'message' => 'Results for CSE301 Midterm exam have been published. Check your grades.', 'action_url' => '/grades', 'action_label' => 'View Results'],
            ['type' => 'error',        'category' => 'attendance',   'title' => 'Low Attendance Warning',      'message' => 'Your attendance in CSE401 is below 75%. You may be barred from exams.', 'action_url' => '/attendance', 'action_label' => 'View Attendance'],
            ['type' => 'announcement', 'category' => 'general',      'title' => 'University Day Holiday',      'message' => 'Classes are suspended on March 26 for Independence Day celebrations.', 'action_url' => null, 'action_label' => null],
            ['type' => 'info',         'category' => 'library',      'title' => 'Book Return Reminder',        'message' => 'The book "Introduction to Algorithms" is due for return in 2 days.', 'action_url' => '/library', 'action_label' => 'View Books'],
            ['type' => 'success',      'category' => 'admission',    'title' => 'Application Accepted',        'message' => 'Congratulations! Your admission application has been accepted.', 'action_url' => '/admissions', 'action_label' => 'View Application'],
            ['type' => 'warning',      'category' => 'fee',          'title' => 'Overdue Fee Notice',          'message' => 'Your exam fee payment is overdue. Please pay immediately to avoid penalties.', 'action_url' => '/fees', 'action_label' => 'Pay Now'],
            ['type' => 'info',         'category' => 'grade',        'title' => 'Grade Updated',               'message' => 'Your grade for BBA201 Financial Accounting has been updated to A-.', 'action_url' => '/grades', 'action_label' => 'View Grades'],
            ['type' => 'announcement', 'category' => 'system',       'title' => 'System Maintenance',          'message' => 'The system will be under maintenance on Saturday 2:00 AM - 4:00 AM.', 'action_url' => null, 'action_label' => null],
            ['type' => 'success',      'category' => 'enrollment',   'title' => 'Course Registration Open',    'message' => 'Course registration for Fall 2025 is now open. Register before March 31.', 'action_url' => '/enrollments', 'action_label' => 'Register Now'],
            ['type' => 'info',         'category' => 'general',      'title' => 'New Timetable Published',     'message' => 'The class timetable for Spring 2025 has been published.', 'action_url' => '/timetable', 'action_label' => 'View Timetable'],
        ];

        foreach ($users as $user) {
            // Give each user 4-8 random notifications
            $selected = collect($templates)->shuffle()->take(rand(4, 8));
            foreach ($selected as $t) {
                Notification::create([
                    'user_id'      => $user->id,
                    'sent_by'      => $admin?->id,
                    'type'         => $t['type'],
                    'category'     => $t['category'],
                    'title'        => $t['title'],
                    'message'      => $t['message'],
                    'action_url'   => $t['action_url'],
                    'action_label' => $t['action_label'],
                    'is_read'      => rand(0, 1) === 1,
                    'read_at'      => rand(0,1) ? now()->subHours(rand(1,48)) : null,
                    'created_at'   => now()->subHours(rand(1, 72)),
                ]);
                $count++;
            }
        }

        $this->command->info("✅ NotificationSeeder: {$count} notifications created");
    }
}
