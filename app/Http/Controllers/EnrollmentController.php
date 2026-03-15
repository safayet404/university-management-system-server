<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/enrollments ──────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('enrollments', 'list', 'visited', 'Visited enrollments list');

        $query = Enrollment::with(['student.user', 'course.department', 'approvedBy'])
            ->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->whereHas('student', fn($sq) => $sq->where('student_id', 'like', "%{$s}%"))
                ->orWhereHas('student.user', fn($uq) => $uq->where('name', 'like', "%{$s}%"))
                ->orWhereHas('course', fn($cq) => $cq->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            );
        }

        if ($request->filled('status'))            $query->where('status', $request->status);
        if ($request->filled('course_id'))         $query->where('course_id', $request->course_id);
        if ($request->filled('semester'))          $query->where('semester', $request->semester);
        if ($request->filled('academic_year'))     $query->where('academic_year', $request->academic_year);
        if ($request->filled('student_profile_id'))$query->where('student_profile_id', $request->student_profile_id);

        $enrollments = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $enrollments->map(fn($e) => $this->formatEnrollment($e)),
            'pagination' => [
                'total'        => $enrollments->total(),
                'current_page' => $enrollments->currentPage(),
                'last_page'    => $enrollments->lastPage(),
            ],
        ]);
    }

    // ── POST /api/enrollments ─────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'student_profile_id' => 'required|exists:student_profiles,id',
            'course_id'          => 'required|exists:courses,id',
            'semester'           => 'required|string|max:20',
            'academic_year'      => 'required|string|max:10',
            'section'            => 'nullable|string|max:5',
        ]);

        // Check duplicate
        $exists = Enrollment::where([
            'student_profile_id' => $request->student_profile_id,
            'course_id'          => $request->course_id,
            'semester'           => $request->semester,
            'academic_year'      => $request->academic_year,
        ])->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Student already enrolled in this course for this semester.'], 422);
        }

        // Check capacity
        $course   = Course::findOrFail($request->course_id);
        $enrolled = $course->enrollments()->where('status', 'approved')->count();
        if ($enrolled >= $course->max_students) {
            return response()->json(['success' => false, 'message' => 'Course has reached maximum capacity.'], 422);
        }

        $enrollment = Enrollment::create([
            'student_profile_id' => $request->student_profile_id,
            'course_id'          => $request->course_id,
            'semester'           => $request->semester,
            'academic_year'      => $request->academic_year,
            'section'            => $request->section,
            'status'             => 'pending',
        ]);

        $enrollment->load(['student.user', 'course']);
        self::logVisit('enrollments', 'create', 'created', "Enrolled in {$course->name}", [], [], Enrollment::class, $enrollment->id);

        return response()->json(['success' => true, 'message' => 'Enrollment submitted.', 'data' => $this->formatEnrollment($enrollment)], 201);
    }

    // ── PATCH /api/enrollments/{id}/approve ───────────────────
    public function approve($id)
    {
        $enrollment = Enrollment::with(['student.user', 'course'])->findOrFail($id);

        if ($enrollment->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending enrollments can be approved.'], 422);
        }

        $enrollment->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        self::logVisit('enrollments', 'approve', 'approved', "Approved enrollment: {$enrollment->student->user->name} in {$enrollment->course->name}", ['status' => 'pending'], ['status' => 'approved'], Enrollment::class, $enrollment->id);

        return response()->json(['success' => true, 'message' => 'Enrollment approved.', 'data' => $this->formatEnrollment($enrollment->fresh())]);
    }

    // ── PATCH /api/enrollments/{id}/reject ────────────────────
    public function reject(Request $request, $id)
    {
        $enrollment = Enrollment::with(['student.user', 'course'])->findOrFail($id);

        $enrollment->update([
            'status'  => 'rejected',
            'remarks' => $request->reason,
        ]);

        self::logVisit('enrollments', 'reject', 'rejected', "Rejected enrollment: {$enrollment->student->user->name} in {$enrollment->course->name}", ['status' => 'pending'], ['status' => 'rejected'], Enrollment::class, $enrollment->id);

        return response()->json(['success' => true, 'message' => 'Enrollment rejected.']);
    }

    // ── PATCH /api/enrollments/{id}/drop ─────────────────────
    public function drop($id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->update(['status' => 'dropped']);
        self::logVisit('enrollments', 'drop', 'dropped', "Dropped enrollment", [], [], Enrollment::class, $enrollment->id);
        return response()->json(['success' => true, 'message' => 'Enrollment dropped.']);
    }

    // ── POST /api/enrollments/bulk-approve ────────────────────
    public function bulkApprove(Request $request)
    {
        $request->validate(['enrollment_ids' => 'required|array', 'enrollment_ids.*' => 'integer|exists:enrollments,id']);

        $count = Enrollment::whereIn('id', $request->enrollment_ids)
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        self::logVisit('enrollments', 'bulk-approve', 'approved', "Bulk approved {$count} enrollments");

        return response()->json(['success' => true, 'message' => "{$count} enrollments approved."]);
    }

    // ── GET /api/enrollments/stats ────────────────────────────
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total'     => Enrollment::count(),
                'pending'   => Enrollment::where('status', 'pending')->count(),
                'approved'  => Enrollment::where('status', 'approved')->count(),
                'rejected'  => Enrollment::where('status', 'rejected')->count(),
                'dropped'   => Enrollment::where('status', 'dropped')->count(),
                'completed' => Enrollment::where('status', 'completed')->count(),
            ],
        ]);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatEnrollment(Enrollment $e): array
    {
        return [
            'id'            => $e->id,
            'status'        => $e->status,
            'semester'      => $e->semester,
            'academic_year' => $e->academic_year,
            'section'       => $e->section,
            'grade'         => $e->grade,
            'grade_letter'  => $e->grade_letter,
            'remarks'       => $e->remarks,
            'approved_at'   => $e->approved_at?->format('Y-m-d H:i:s'),
            'approved_by'   => $e->approvedBy?->name,
            'created_at'    => $e->created_at?->format('Y-m-d H:i:s'),
            'student'       => $e->student ? [
                'id'         => $e->student->id,
                'student_id' => $e->student->student_id,
                'name'       => $e->student->user?->name,
                'email'      => $e->student->user?->email,
                'avatar_url' => $e->student->user?->avatar_url,
                'department' => $e->student->department?->code,
            ] : null,
            'course'        => $e->course ? [
                'id'          => $e->course->id,
                'name'        => $e->course->name,
                'code'        => $e->course->code,
                'credit_hours'=> $e->course->credit_hours,
                'department'  => $e->course->department?->code,
            ] : null,
        ];
    }
}
