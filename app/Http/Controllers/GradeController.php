<?php

namespace App\Http\Controllers;

use App\Models\CourseGrade;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\StudentProfile;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    use LogsPageVisit;

    // GET /api/grades — list grades
    public function index(Request $request)
    {
        self::logVisit('grades', 'list', 'visited', 'Visited grades list');

        $query = CourseGrade::with(['course', 'student.user'])->latest();

        if ($request->filled('course_id'))         $query->where('course_id', $request->course_id);
        if ($request->filled('semester'))          $query->where('semester', $request->semester);
        if ($request->filled('academic_year'))     $query->where('academic_year', $request->academic_year);
        if ($request->filled('student_profile_id'))$query->where('student_profile_id', $request->student_profile_id);
        if ($request->filled('is_published'))      $query->where('is_published', $request->boolean('is_published'));

        $grades = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success'    => true,
            'data'       => $grades->map(fn($g) => $this->formatGrade($g)),
            'pagination' => ['total' => $grades->total(), 'current_page' => $grades->currentPage(), 'last_page' => $grades->lastPage()],
        ]);
    }

    // GET /api/grades/course — grades for a specific course
    public function forCourse(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'semester'      => 'required|string',
            'academic_year' => 'required|string',
        ]);

        self::logVisit('grades', 'course', 'visited', "Viewed grades for course #{$request->course_id}");

        $enrollments = Enrollment::with('student.user')
            ->where('course_id', $request->course_id)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->where('status', 'approved')
            ->get();

        $existingGrades = CourseGrade::where('course_id', $request->course_id)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->get()->keyBy('student_profile_id');

        $students = $enrollments->map(fn($e) => [
            'enrollment_id'      => $e->id,
            'student_profile_id' => $e->student_profile_id,
            'student_id'         => $e->student?->student_id,
            'name'               => $e->student?->user?->name,
            'avatar_url'         => $e->student?->user?->avatar_url,
            'total_marks'        => $existingGrades[$e->student_profile_id]?->total_marks,
            'grade_point'        => $existingGrades[$e->student_profile_id]?->grade_point,
            'grade_letter'       => $existingGrades[$e->student_profile_id]?->grade_letter,
            'is_published'       => $existingGrades[$e->student_profile_id]?->is_published ?? false,
            'remarks'            => $existingGrades[$e->student_profile_id]?->remarks,
            'grade_id'           => $existingGrades[$e->student_profile_id]?->id,
        ]);

        return response()->json(['success' => true, 'data' => $students]);
    }

    // POST /api/grades/save — save/update grades for a course
    public function save(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'semester'      => 'required|string',
            'academic_year' => 'required|string',
            'grades'        => 'required|array',
            'grades.*.enrollment_id'      => 'required|exists:enrollments,id',
            'grades.*.student_profile_id' => 'required|exists:student_profiles,id',
            'grades.*.total_marks'        => 'nullable|numeric|min:0|max:100',
            'grades.*.remarks'            => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->grades as $gradeData) {
                $marks  = $gradeData['total_marks'] ?? null;
                $gradeCalc = $marks !== null
                    ? \App\Models\ExamResult::calculateGrade((float)$marks, 100)
                    : ['letter' => null, 'point' => null];

                CourseGrade::updateOrCreate(
                    [
                        'enrollment_id'      => $gradeData['enrollment_id'],
                        'student_profile_id' => $gradeData['student_profile_id'],
                    ],
                    [
                        'course_id'     => $request->course_id,
                        'semester'      => $request->semester,
                        'academic_year' => $request->academic_year,
                        'total_marks'   => $marks,
                        'grade_point'   => $gradeCalc['point'],
                        'grade_letter'  => $gradeCalc['letter'],
                        'remarks'       => $gradeData['remarks'] ?? null,
                    ]
                );
            }
        });

        self::logVisit('grades', 'save', 'created', "Grades saved for course #{$request->course_id}");

        return response()->json(['success' => true, 'message' => 'Grades saved successfully.']);
    }

    // POST /api/grades/publish — publish grades for a course
    public function publish(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'semester'      => 'required|string',
            'academic_year' => 'required|string',
        ]);

        $count = CourseGrade::where('course_id', $request->course_id)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->update([
                'is_published' => true,
                'published_at' => now(),
                'published_by' => auth()->id(),
            ]);

        // Update enrollment grades
        CourseGrade::where('course_id', $request->course_id)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->each(function ($grade) {
                Enrollment::where('id', $grade->enrollment_id)->update([
                    'grade'        => $grade->grade_point,
                    'grade_letter' => $grade->grade_letter,
                    'status'       => 'completed',
                ]);

                // Update student CGPA
                $student = StudentProfile::find($grade->student_profile_id);
                if ($student) {
                    $avg = CourseGrade::where('student_profile_id', $student->id)
                        ->where('is_published', true)
                        ->whereNotNull('grade_point')
                        ->avg('grade_point');
                    $student->update(['cgpa' => round($avg, 2)]);
                }
            });

        self::logVisit('grades', 'publish', 'published', "Published {$count} grades for course #{$request->course_id}");

        return response()->json(['success' => true, 'message' => "{$count} grades published."]);
    }

    // GET /api/grades/student/{id} — all grades for a student
    public function studentGrades($id)
    {
        $student = StudentProfile::with('user')->findOrFail($id);
        $grades  = CourseGrade::with('course.department')
            ->where('student_profile_id', $id)
            ->where('is_published', true)
            ->get();

        $cgpa = $grades->whereNotNull('grade_point')->avg('grade_point');

        return response()->json([
            'success' => true,
            'student' => ['id' => $student->id, 'name' => $student->user?->name, 'student_id' => $student->student_id],
            'cgpa'    => round($cgpa ?? 0, 2),
            'data'    => $grades->map(fn($g) => $this->formatGrade($g)),
        ]);
    }

    // GET /api/grades/stats
    public function stats()
    {
        return response()->json(['success' => true, 'data' => [
            'total'     => CourseGrade::count(),
            'published' => CourseGrade::where('is_published', true)->count(),
            'pending'   => CourseGrade::where('is_published', false)->count(),
            'avg_gpa'   => round(CourseGrade::where('is_published', true)->whereNotNull('grade_point')->avg('grade_point') ?? 0, 2),
            'by_grade'  => CourseGrade::where('is_published', true)->whereNotNull('grade_letter')
                ->selectRaw('grade_letter, count(*) as count')
                ->groupBy('grade_letter')
                ->orderBy('grade_letter')
                ->get(),
        ]]);
    }

    private function formatGrade(CourseGrade $g): array
    {
        return [
            'id'            => $g->id,
            'enrollment_id' => $g->enrollment_id,
            'total_marks'   => $g->total_marks,
            'grade_point'   => $g->grade_point,
            'grade_letter'  => $g->grade_letter,
            'is_published'  => $g->is_published,
            'published_at'  => $g->published_at?->format('Y-m-d H:i:s'),
            'remarks'       => $g->remarks,
            'course'        => $g->course ? ['id' => $g->course->id, 'name' => $g->course->name, 'code' => $g->course->code] : null,
            'student'       => $g->student ? ['id' => $g->student->id, 'name' => $g->student->user?->name, 'student_id' => $g->student->student_id, 'avatar_url' => $g->student->user?->avatar_url] : null,
            'semester'      => $g->semester,
            'academic_year' => $g->academic_year,
        ];
    }
}
