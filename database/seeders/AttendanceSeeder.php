<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();
        $admin = User::role('super-admin')->first();
        $count = 0;

        // Find course+semester+year combos that actually have approved enrollments
        $combos = Enrollment::where('status', 'approved')
            ->selectRaw('course_id, semester, academic_year, count(*) as enrollment_count')
            ->groupBy('course_id', 'semester', 'academic_year')
            ->havingRaw('count(*) >= 1')
            ->take(5)
            ->get();

        foreach ($combos as $combo) {
            $enrollments = Enrollment::where('course_id', $combo->course_id)
                ->where('semester', $combo->semester)
                ->where('academic_year', $combo->academic_year)
                ->where('status', 'approved')
                ->with('student')
                ->get();

            if ($enrollments->isEmpty()) continue;

            $date = now()->subWeeks(4);
            $sessionsCreated = 0;

            while ($sessionsCreated < 12 && $date->lessThan(now())) {
                if ($date->isWeekend()) { $date->addDay(); continue; }

                if (AttendanceSession::where([
                    'course_id'     => $combo->course_id,
                    'date'          => $date->format('Y-m-d'),
                    'semester'      => $combo->semester,
                    'academic_year' => $combo->academic_year,
                ])->exists()) { $date->addDay(); continue; }

                $session = AttendanceSession::create([
                    'course_id'     => $combo->course_id,
                    'taken_by'      => $admin->id,
                    'date'          => $date->format('Y-m-d'),
                    'semester'      => $combo->semester,
                    'academic_year' => $combo->academic_year,
                    'topic'         => $faker->sentence(4),
                    'is_finalized'  => true,
                    'start_time'    => '09:00:00',
                    'end_time'      => '10:30:00',
                ]);

                foreach ($enrollments as $enrollment) {
                    $rand   = rand(1, 100);
                    $status = $rand <= 75 ? 'present' : ($rand <= 85 ? 'late' : ($rand <= 95 ? 'absent' : 'excused'));
                    AttendanceRecord::create([
                        'attendance_session_id' => $session->id,
                        'student_profile_id'    => $enrollment->student_profile_id,
                        'status'                => $status,
                    ]);
                    $count++;
                }

                $sessionsCreated++;
                $date->addDay();
            }
        }

        $this->command->info("✅ AttendanceSeeder: {$count} records across " . AttendanceSession::count() . " sessions");
    }
}
