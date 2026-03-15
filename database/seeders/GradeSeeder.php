<?php

namespace Database\Seeders;

use App\Models\CourseGrade;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\User;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('super-admin')->first();
        $count = 0;

        $combos = Enrollment::where('status', 'approved')
            ->selectRaw('course_id, semester, academic_year')
            ->groupBy('course_id', 'semester', 'academic_year')
            ->take(4)
            ->get();

        foreach ($combos as $combo) {
            $enrollments = Enrollment::where([
                'course_id'     => $combo->course_id,
                'semester'      => $combo->semester,
                'academic_year' => $combo->academic_year,
                'status'        => 'approved',
            ])->get();

            foreach ($enrollments as $enrollment) {
                if (CourseGrade::where('enrollment_id', $enrollment->id)->exists()) continue;

                // Calculate weighted average from exam results
                $results = ExamResult::whereHas('exam', fn($q) => $q
                    ->where('course_id', $combo->course_id)
                    ->where('semester', $combo->semester)
                    ->where('academic_year', $combo->academic_year)
                    ->where('results_published', true)
                )->where('student_profile_id', $enrollment->student_profile_id)
                 ->where('is_absent', false)
                 ->with('exam')
                 ->get();

                if ($results->isEmpty()) continue;

                $totalWeightage = $results->sum(fn($r) => $r->exam->weightage);
                if ($totalWeightage <= 0) continue;

                $weightedMarks = $results->sum(function ($r) {
                    $pct = ($r->marks_obtained / $r->exam->total_marks) * 100;
                    return $pct * ($r->exam->weightage / 100);
                });

                $finalMarks = $totalWeightage > 0 ? round($weightedMarks, 2) : null;
                $grade = $finalMarks !== null ? ExamResult::calculateGrade($finalMarks, 100) : ['letter' => null, 'point' => null];

                CourseGrade::create([
                    'enrollment_id'      => $enrollment->id,
                    'course_id'          => $combo->course_id,
                    'student_profile_id' => $enrollment->student_profile_id,
                    'semester'           => $combo->semester,
                    'academic_year'      => $combo->academic_year,
                    'total_marks'        => $finalMarks,
                    'grade_point'        => $grade['point'],
                    'grade_letter'       => $grade['letter'],
                    'is_published'       => true,
                    'published_at'       => now(),
                    'published_by'       => $admin->id,
                ]);

                // Update enrollment
                $enrollment->update(['grade' => $grade['point'], 'grade_letter' => $grade['letter'], 'status' => 'completed']);
                $count++;
            }

            // Update CGPA for all students in this combo
            foreach ($enrollments as $enrollment) {
                $avg = CourseGrade::where('student_profile_id', $enrollment->student_profile_id)
                    ->where('is_published', true)->whereNotNull('grade_point')->avg('grade_point');
                if ($avg) $enrollment->student?->update(['cgpa' => round($avg, 2)]);
            }
        }

        $this->command->info("✅ GradeSeeder: {$count} course grades created");
    }
}
