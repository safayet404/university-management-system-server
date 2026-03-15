<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();
        $admin = User::role('super-admin')->first();
        $count = 0;

        // Get combos with approved enrollments
        $combos = Enrollment::where('status', 'approved')
            ->selectRaw('course_id, semester, academic_year')
            ->groupBy('course_id', 'semester', 'academic_year')
            ->take(4)
            ->get();

        $examTypes = [
            ['title' => 'Quiz 1',     'type' => 'quiz',       'marks' => 20,  'weightage' => 10, 'days' => -60],
            ['title' => 'Midterm',    'type' => 'midterm',    'marks' => 40,  'weightage' => 30, 'days' => -30],
            ['title' => 'Assignment', 'type' => 'assignment', 'marks' => 20,  'weightage' => 10, 'days' => -15],
            ['title' => 'Final Exam', 'type' => 'final',      'marks' => 100, 'weightage' => 50, 'days' => -5],
        ];

        foreach ($combos as $combo) {
            $enrollments = Enrollment::where('course_id', $combo->course_id)
                ->where('semester', $combo->semester)
                ->where('academic_year', $combo->academic_year)
                ->where('status', 'approved')
                ->with('student')
                ->get();

            if ($enrollments->isEmpty()) continue;

            foreach ($examTypes as $type) {
                $exam = Exam::firstOrCreate(
                    ['course_id' => $combo->course_id, 'exam_type' => $type['type'], 'semester' => $combo->semester, 'academic_year' => $combo->academic_year],
                    [
                        'title'          => $type['title'],
                        'created_by'     => $admin->id,
                        'exam_date'      => now()->addDays($type['days'])->format('Y-m-d'),
                        'start_time'     => '09:00:00',
                        'end_time'       => '11:00:00',
                        'venue'          => 'Exam Hall ' . rand(1, 5),
                        'total_marks'    => $type['marks'],
                        'passing_marks'  => intval($type['marks'] * 0.4),
                        'weightage'      => $type['weightage'],
                        'status'         => 'completed',
                        'results_published' => true,
                        'results_published_at' => now(),
                    ]
                );

                foreach ($enrollments as $enrollment) {
                    if (ExamResult::where(['exam_id' => $exam->id, 'student_profile_id' => $enrollment->student_profile_id])->exists()) continue;

                    $isAbsent = rand(1, 20) === 1;
                    $marks    = $isAbsent ? null : round($faker->randomFloat(2, $type['marks'] * 0.3, $type['marks']), 2);
                    $grade    = $marks !== null ? ExamResult::calculateGrade((float)$marks, $type['marks']) : ['letter' => null, 'point' => null];

                    ExamResult::create([
                        'exam_id'            => $exam->id,
                        'student_profile_id' => $enrollment->student_profile_id,
                        'marks_obtained'     => $marks,
                        'grade_letter'       => $grade['letter'],
                        'grade_point'        => $grade['point'],
                        'is_absent'          => $isAbsent,
                        'entered_by'         => $admin->id,
                        'entered_at'         => now(),
                    ]);
                    $count++;
                }
            }
        }

        $this->command->info("✅ ExamSeeder: " . Exam::count() . " exams, {$count} results created");
    }
}
