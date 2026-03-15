<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $faker    = \Faker\Factory::create();
        $students = StudentProfile::with('department')->get();
        $admin    = User::role('admin')->first() ?? User::role('super-admin')->first();
        $count    = 0;

        $semesters     = ['Spring 2024', 'Fall 2024', 'Spring 2025'];
        $academicYears = ['2023-2024', '2024-2025'];
        $statuses      = ['approved', 'approved', 'approved', 'pending', 'completed'];

        foreach ($students as $student) {
            $courses = Course::where('department_id', $student->department_id)
                ->inRandomOrder()->limit(5)->get();

            foreach ($courses as $course) {
                $semester     = $faker->randomElement($semesters);
                $academicYear = $faker->randomElement($academicYears);
                $status       = $faker->randomElement($statuses);

                if (Enrollment::where([
                    'student_profile_id' => $student->id,
                    'course_id'          => $course->id,
                    'semester'           => $semester,
                    'academic_year'      => $academicYear,
                ])->exists()) continue;

                $grade = null;
                $gradeLetter = null;
                if ($status === 'completed') {
                    $grade = round($faker->randomFloat(2, 2.0, 4.0), 2);
                    $gradeLetter = $grade >= 4.0 ? 'A+' : ($grade >= 3.75 ? 'A' : ($grade >= 3.5 ? 'A-' : ($grade >= 3.25 ? 'B+' : ($grade >= 3.0 ? 'B' : ($grade >= 2.75 ? 'B-' : 'C+')))));
                }

                Enrollment::create([
                    'student_profile_id' => $student->id,
                    'course_id'          => $course->id,
                    'semester'           => $semester,
                    'academic_year'      => $academicYear,
                    'section'            => $faker->randomElement(['A', 'B', 'C']),
                    'status'             => $status,
                    'grade'              => $grade,
                    'grade_letter'       => $gradeLetter,
                    'approved_by'        => in_array($status, ['approved', 'completed']) ? $admin?->id : null,
                    'approved_at'        => in_array($status, ['approved', 'completed']) ? now()->subDays(rand(1, 30)) : null,
                ]);
                $count++;
            }
        }

        $this->command->info("✅ EnrollmentSeeder: {$count} enrollments created");
    }
}
