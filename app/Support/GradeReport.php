<?php

namespace App\Support;

use App\Models\CourseGrade;
use App\Models\StudentProfile;

/**
 * Credit-weighted GPA per term and CGPA over all published grades.
 */
class GradeReport
{
    public static function for(StudentProfile $student): array
    {
        $grades = CourseGrade::with('course')
            ->where('student_profile_id', $student->id)
            ->where('is_published', true)
            ->get();

        $terms = $grades
            ->groupBy(fn ($g) => $g->semester . '|' . $g->academic_year)
            ->map(function ($termGrades) {
                $first = $termGrades->first();

                return [
                    'semester'      => $first->semester,
                    'academic_year' => $first->academic_year,
                    'credits'       => $termGrades->sum(fn ($g) => self::credits($g)),
                    'gpa'           => self::weightedGpa($termGrades),
                    'courses'       => $termGrades->map(fn ($g) => [
                        'course_id'    => $g->course_id,
                        'code'         => $g->course?->code,
                        'name'         => $g->course?->name,
                        'credits'      => self::credits($g),
                        'total_marks'  => $g->total_marks !== null ? (float) $g->total_marks : null,
                        'grade_letter' => $g->grade_letter,
                        'grade_point'  => $g->grade_point !== null ? (float) $g->grade_point : null,
                    ])->values(),
                ];
            })
            ->sortBy(fn ($t) => AcademicTerm::sortKey($t['semester']))
            ->values();

        return [
            'cgpa'           => self::weightedGpa($grades),
            'earned_credits' => $grades->filter(fn ($g) => (float) $g->grade_point > 0)->sum(fn ($g) => self::credits($g)),
            'terms'          => $terms,
        ];
    }

    private static function credits(CourseGrade $grade): int
    {
        return (int) ($grade->course?->credit_hours ?? 3);
    }

    private static function weightedGpa($grades): ?float
    {
        $graded = $grades->whereNotNull('grade_point');
        $credits = $graded->sum(fn ($g) => self::credits($g));
        if ($credits === 0) {
            return null;
        }

        return round($graded->sum(fn ($g) => (float) $g->grade_point * self::credits($g)) / $credits, 2);
    }
}
