<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    use LogsPageVisit;

    public function index(Request $request)
    {
        self::logVisit('exams', 'list', 'visited', 'Visited exams list');

        $query = Exam::with(['course.department', 'createdBy'])
            ->withCount('results')
            ->latest('exam_date');

        if ($request->filled('search'))      $query->where('title', 'like', "%{$request->search}%");
        if ($request->filled('course_id'))   $query->where('course_id', $request->course_id);
        if ($request->filled('exam_type'))   $query->where('exam_type', $request->exam_type);
        if ($request->filled('semester'))    $query->where('semester', $request->semester);
        if ($request->filled('status'))      $query->where('status', $request->status);

        $exams = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $exams->map(fn($e) => $this->formatExam($e)),
            'pagination' => ['total' => $exams->total(), 'current_page' => $exams->currentPage(), 'last_page' => $exams->lastPage()],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'title'         => 'required|string|max:255',
            'exam_type'     => 'required|in:midterm,final,quiz,assignment,lab,viva',
            'semester'      => 'required|string',
            'academic_year' => 'required|string',
            'exam_date'     => 'nullable|date',
            'start_time'    => 'nullable|string',
            'end_time'      => 'nullable|string',
            'venue'         => 'nullable|string|max:255',
            'total_marks'   => 'nullable|integer|min:1',
            'passing_marks' => 'nullable|integer|min:0',
            'weightage'     => 'nullable|numeric|min:0|max:100',
            'instructions'  => 'nullable|string',
        ]);

        $exam = Exam::create(array_merge($validated, ['created_by' => auth()->id(), 'status' => 'scheduled']));
        self::logVisit('exams', 'create', 'created', "Created exam: {$exam->title}", [], $validated, Exam::class, $exam->id);

        return response()->json(['success' => true, 'message' => 'Exam created.', 'data' => $this->formatExam($exam->load('course'))], 201);
    }

    public function update(Request $request, $id)
    {
        $exam = Exam::findOrFail($id);
        $validated = $request->validate([
            'title'         => 'sometimes|string|max:255',
            'exam_type'     => 'sometimes|in:midterm,final,quiz,assignment,lab,viva',
            'exam_date'     => 'nullable|date',
            'start_time'    => 'nullable|string',
            'end_time'      => 'nullable|string',
            'venue'         => 'nullable|string|max:255',
            'total_marks'   => 'nullable|integer|min:1',
            'passing_marks' => 'nullable|integer|min:0',
            'weightage'     => 'nullable|numeric|min:0|max:100',
            'instructions'  => 'nullable|string',
            'status'        => 'nullable|in:scheduled,ongoing,completed,cancelled',
        ]);

        $exam->update($validated);
        self::logVisit('exams', 'edit', 'updated', "Updated exam: {$exam->title}", [], $validated, Exam::class, $exam->id);

        return response()->json(['success' => true, 'message' => 'Exam updated.', 'data' => $this->formatExam($exam->fresh()->load('course'))]);
    }

    public function destroy($id)
    {
        $exam = Exam::findOrFail($id);
        self::logVisit('exams', 'delete', 'deleted', "Deleted exam: {$exam->title}", [], [], Exam::class, $exam->id);
        $exam->delete();
        return response()->json(['success' => true, 'message' => 'Exam deleted.']);
    }

    // GET /api/exams/{id}/students — get students to enter results
    public function students($id)
    {
        $exam = Exam::with('course')->findOrFail($id);

        $enrollments = Enrollment::with('student.user')
            ->where('course_id', $exam->course_id)
            ->where('semester', $exam->semester)
            ->where('academic_year', $exam->academic_year)
            ->where('status', 'approved')
            ->get();

        $existingResults = ExamResult::where('exam_id', $id)->get()->keyBy('student_profile_id');

        $students = $enrollments->map(fn($e) => [
            'student_profile_id' => $e->student_profile_id,
            'student_id'         => $e->student?->student_id,
            'name'               => $e->student?->user?->name,
            'avatar_url'         => $e->student?->user?->avatar_url,
            'marks_obtained'     => $existingResults[$e->student_profile_id]?->marks_obtained,
            'grade_letter'       => $existingResults[$e->student_profile_id]?->grade_letter,
            'grade_point'        => $existingResults[$e->student_profile_id]?->grade_point,
            'is_absent'          => $existingResults[$e->student_profile_id]?->is_absent ?? false,
            'remarks'            => $existingResults[$e->student_profile_id]?->remarks,
        ]);

        return response()->json(['success' => true, 'exam' => $this->formatExam($exam), 'students' => $students]);
    }

    // POST /api/exams/{id}/results — save exam results
    public function saveResults(Request $request, $id)
    {
        $exam = Exam::findOrFail($id);
        $request->validate([
            'results'                         => 'required|array',
            'results.*.student_profile_id'    => 'required|exists:student_profiles,id',
            'results.*.marks_obtained'        => 'nullable|numeric|min:0',
            'results.*.is_absent'             => 'nullable|boolean',
            'results.*.remarks'               => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $exam, $id) {
            foreach ($request->results as $result) {
                $isAbsent = $result['is_absent'] ?? false;
                $marks    = $isAbsent ? null : ($result['marks_obtained'] ?? null);
                $grade    = $marks !== null ? ExamResult::calculateGrade((float)$marks, $exam->total_marks) : ['letter' => null, 'point' => null];

                ExamResult::updateOrCreate(
                    ['exam_id' => $id, 'student_profile_id' => $result['student_profile_id']],
                    [
                        'marks_obtained' => $marks,
                        'grade_letter'   => $grade['letter'],
                        'grade_point'    => $grade['point'],
                        'is_absent'      => $isAbsent,
                        'remarks'        => $result['remarks'] ?? null,
                        'entered_by'     => auth()->id(),
                        'entered_at'     => now(),
                    ]
                );
            }
            $exam->update(['status' => 'completed']);
        });

        self::logVisit('exams', 'results', 'created', "Results entered for exam: {$exam->title}", [], [], Exam::class, $exam->id);

        return response()->json(['success' => true, 'message' => 'Results saved.']);
    }

    // PATCH /api/exams/{id}/publish — publish results
    public function publishResults($id)
    {
        $exam = Exam::findOrFail($id);
        $exam->update(['results_published' => true, 'results_published_at' => now()]);
        self::logVisit('exams', 'publish', 'published', "Published results for: {$exam->title}", [], [], Exam::class, $exam->id);
        return response()->json(['success' => true, 'message' => 'Results published.']);
    }

    // GET /api/exams/stats
    public function stats()
    {
        return response()->json(['success' => true, 'data' => [
            'total'      => Exam::count(),
            'scheduled'  => Exam::where('status', 'scheduled')->count(),
            'completed'  => Exam::where('status', 'completed')->count(),
            'published'  => Exam::where('results_published', true)->count(),
            'upcoming'   => Exam::where('status', 'scheduled')->where('exam_date', '>=', today())->count(),
        ]]);
    }

    private function formatExam(Exam $e): array
    {
        return [
            'id'                   => $e->id,
            'title'                => $e->title,
            'exam_type'            => $e->exam_type,
            'semester'             => $e->semester,
            'academic_year'        => $e->academic_year,
            'exam_date'            => $e->exam_date?->format('Y-m-d'),
            'exam_date_display'    => $e->exam_date?->format('D, M d Y'),
            'start_time'           => $e->start_time,
            'end_time'             => $e->end_time,
            'venue'                => $e->venue,
            'total_marks'          => $e->total_marks,
            'passing_marks'        => $e->passing_marks,
            'weightage'            => $e->weightage,
            'instructions'         => $e->instructions,
            'status'               => $e->status,
            'results_published'    => $e->results_published,
            'results_published_at' => $e->results_published_at?->format('Y-m-d H:i:s'),
            'results_count'        => $e->results_count ?? 0,
            'course'               => $e->course ? ['id' => $e->course->id, 'name' => $e->course->name, 'code' => $e->course->code, 'department' => $e->course->department?->code] : null,
            'created_by'           => $e->createdBy?->name,
        ];
    }
}
