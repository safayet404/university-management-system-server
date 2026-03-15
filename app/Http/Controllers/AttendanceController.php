<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/attendance/sessions ─────────────────────────
    public function sessions(Request $request)
    {
        self::logVisit('attendance', 'sessions', 'visited', 'Visited attendance sessions');

        $query = AttendanceSession::with(['course', 'takenBy'])
            ->withCount('records')
            ->latest('date');

        if ($request->filled('course_id'))     $query->where('course_id', $request->course_id);
        if ($request->filled('date'))          $query->whereDate('date', $request->date);
        if ($request->filled('date_from'))     $query->whereDate('date', '>=', $request->date_from);
        if ($request->filled('date_to'))       $query->whereDate('date', '<=', $request->date_to);
        if ($request->filled('semester'))      $query->where('semester', $request->semester);
        if ($request->filled('academic_year')) $query->where('academic_year', $request->academic_year);

        $sessions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $sessions->map(fn($s) => [
                'id'           => $s->id,
                'date'         => $s->date->format('Y-m-d'),
                'date_display' => $s->date->format('D, M d Y'),
                'semester'     => $s->semester,
                'academic_year'=> $s->academic_year,
                'section'      => $s->section,
                'topic'        => $s->topic,
                'is_finalized' => $s->is_finalized,
                'records_count'=> $s->records_count,
                'taken_by'     => $s->takenBy?->name,
                'course'       => $s->course ? ['id' => $s->course->id, 'name' => $s->course->name, 'code' => $s->course->code] : null,
            ]),
            'pagination' => [
                'total'        => $sessions->total(),
                'current_page' => $sessions->currentPage(),
                'last_page'    => $sessions->lastPage(),
            ],
        ]);
    }

    // ── GET /api/attendance/students ─────────────────────────
    // Get students for a course to mark attendance
    public function getStudentsForCourse(Request $request)
    {
        $request->validate([
            'course_id'    => 'required|exists:courses,id',
            'semester'     => 'required|string',
            'academic_year'=> 'required|string',
        ]);

        // Get enrolled + approved students
        $enrollments = Enrollment::with('student.user')
            ->where('course_id', $request->course_id)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->where('status', 'approved')
            ->get();

        // Check if session exists for this date
        $existingSession = null;
        $existingRecords = [];

        if ($request->filled('date')) {
            $existingSession = AttendanceSession::where([
                'course_id'     => $request->course_id,
                'date'          => $request->date,
                'semester'      => $request->semester,
                'academic_year' => $request->academic_year,
            ])->with('records')->first();

            if ($existingSession) {
                $existingRecords = $existingSession->records->keyBy('student_profile_id');
            }
        }

        $students = $enrollments->map(fn($e) => [
            'enrollment_id'     => $e->id,
            'student_profile_id'=> $e->student_profile_id,
            'student_id'        => $e->student?->student_id,
            'name'              => $e->student?->user?->name,
            'avatar_url'        => $e->student?->user?->avatar_url,
            'section'           => $e->section,
            'status'            => $existingRecords[$e->student_profile_id]?->status ?? 'present',
            'remarks'           => $existingRecords[$e->student_profile_id]?->remarks ?? '',
        ]);

        return response()->json([
            'success'         => true,
            'students'        => $students,
            'existing_session'=> $existingSession ? ['id' => $existingSession->id, 'is_finalized' => $existingSession->is_finalized] : null,
        ]);
    }

    // ── POST /api/attendance/mark ─────────────────────────────
    public function mark(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'date'          => 'required|date',
            'semester'      => 'required|string',
            'academic_year' => 'required|string',
            'section'       => 'nullable|string',
            'topic'         => 'nullable|string|max:255',
            'records'       => 'required|array',
            'records.*.student_profile_id' => 'required|exists:student_profiles,id',
            'records.*.status' => 'required|in:present,absent,late,excused',
            'records.*.remarks'=> 'nullable|string',
        ]);

        DB::transaction(function () use ($request, &$session) {
            // Create or update session
            $session = AttendanceSession::updateOrCreate(
                [
                    'course_id'     => $request->course_id,
                    'date'          => $request->date,
                    'semester'      => $request->semester,
                    'academic_year' => $request->academic_year,
                ],
                [
                    'faculty_profile_id' => auth()->user()->facultyProfile?->id,
                    'taken_by'           => auth()->id(),
                    'section'            => $request->section,
                    'topic'              => $request->topic,
                    'is_finalized'       => $request->boolean('finalize', false),
                ]
            );

            // Upsert records
            foreach ($request->records as $record) {
                AttendanceRecord::updateOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'student_profile_id'    => $record['student_profile_id'],
                    ],
                    [
                        'status'  => $record['status'],
                        'remarks' => $record['remarks'] ?? null,
                    ]
                );
            }
        });

        $present = collect($request->records)->where('status', 'present')->count();
        $absent  = collect($request->records)->where('status', 'absent')->count();
        $late    = collect($request->records)->where('status', 'late')->count();

        self::logVisit('attendance', 'mark', 'created',
            "Attendance marked for course #{$request->course_id} on {$request->date}. Present: {$present}, Absent: {$absent}",
            [], ['date' => $request->date, 'present' => $present, 'absent' => $absent],
            AttendanceSession::class, $session->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance saved successfully.',
            'summary' => ['present' => $present, 'absent' => $absent, 'late' => $late, 'total' => count($request->records)],
            'session_id' => $session->id,
        ]);
    }

    // ── GET /api/attendance/report ────────────────────────────
    // Per-student attendance summary for a course
    public function report(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'semester'      => 'required|string',
            'academic_year' => 'required|string',
        ]);

        self::logVisit('attendance', 'report', 'visited', "Viewed attendance report for course #{$request->course_id}");

        $course       = Course::findOrFail($request->course_id);
        $totalSessions = AttendanceSession::where([
            'course_id'     => $request->course_id,
            'semester'      => $request->semester,
            'academic_year' => $request->academic_year,
        ])->count();

        $enrollments = Enrollment::with('student.user')
            ->where('course_id', $request->course_id)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->where('status', 'approved')
            ->get();

        $report = $enrollments->map(function ($e) use ($request, $totalSessions) {
            $records = AttendanceRecord::whereHas('session', fn($q) => $q
                ->where('course_id', $request->course_id)
                ->where('semester', $request->semester)
                ->where('academic_year', $request->academic_year)
            )->where('student_profile_id', $e->student_profile_id)->get();

            $present = $records->where('status', 'present')->count();
            $absent  = $records->where('status', 'absent')->count();
            $late    = $records->where('status', 'late')->count();
            $excused = $records->where('status', 'excused')->count();
            $attended = $present + $late;
            $pct     = $totalSessions > 0 ? round(($attended / $totalSessions) * 100, 1) : 0;

            return [
                'student_profile_id' => $e->student_profile_id,
                'student_id'         => $e->student?->student_id,
                'name'               => $e->student?->user?->name,
                'avatar_url'         => $e->student?->user?->avatar_url,
                'present'            => $present,
                'absent'             => $absent,
                'late'               => $late,
                'excused'            => $excused,
                'total_sessions'     => $totalSessions,
                'attended'           => $attended,
                'percentage'         => $pct,
                'status'             => $pct >= 75 ? 'good' : ($pct >= 60 ? 'warning' : 'danger'),
            ];
        });

        return response()->json([
            'success'        => true,
            'course'         => ['id' => $course->id, 'name' => $course->name, 'code' => $course->code],
            'total_sessions' => $totalSessions,
            'data'           => $report->sortBy('name')->values(),
        ]);
    }

    // ── GET /api/attendance/stats ─────────────────────────────
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_sessions'  => AttendanceSession::count(),
                'today_sessions'  => AttendanceSession::whereDate('date', today())->count(),
                'total_records'   => AttendanceRecord::count(),
                'present_today'   => AttendanceRecord::whereHas('session', fn($q) => $q->whereDate('date', today()))->where('status', 'present')->count(),
                'absent_today'    => AttendanceRecord::whereHas('session', fn($q) => $q->whereDate('date', today()))->where('status', 'absent')->count(),
                'avg_attendance'  => round(AttendanceRecord::where('status', 'present')->count() / max(1, AttendanceRecord::count()) * 100, 1),
            ],
        ]);
    }

    // ── GET /api/attendance/calendar ─────────────────────────
    public function calendar(Request $request)
    {
        $request->validate(['course_id' => 'required|exists:courses,id', 'month' => 'nullable|integer', 'year' => 'nullable|integer']);

        $month = $request->month ?? now()->month;
        $year  = $request->year  ?? now()->year;

        $sessions = AttendanceSession::where('course_id', $request->course_id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->withCount(['records', 'records as present_count' => fn($q) => $q->where('status', 'present')])
            ->get()
            ->map(fn($s) => [
                'date'          => $s->date->format('Y-m-d'),
                'total'         => $s->records_count,
                'present'       => $s->present_count,
                'is_finalized'  => $s->is_finalized,
            ])
            ->keyBy('date');

        return response()->json(['success' => true, 'data' => $sessions]);
    }
}
