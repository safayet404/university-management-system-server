<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseGrade;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Traits\LogsPageVisit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/reports/overview ─────────────────────────────
    public function overview()
    {
        self::logVisit('reports', 'overview', 'visited', 'Viewed reports overview');

        return response()->json(['success' => true, 'data' => [
            'students'     => StudentProfile::count(),
            'faculty'      => \App\Models\FacultyProfile::count(),
            'courses'      => \App\Models\Course::count(),
            'enrollments'  => Enrollment::where('status', 'approved')->count(),
            'revenue'      => FeeInvoice::sum('paid_amount'),
            'pending_fees' => FeeInvoice::whereIn('status', ['unpaid', 'overdue', 'partial'])->sum(DB::raw('amount - discount + fine - paid_amount')),
            'admissions'   => Admission::whereYear('created_at', date('Y'))->count(),
            'exams'        => Exam::where('status', 'completed')->count(),
        ]]);
    }

    // ── GET /api/reports/students ─────────────────────────────
    public function students(Request $request)
    {
        self::logVisit('reports', 'students', 'visited', 'Viewed student report');

        $query = StudentProfile::with(['user', 'department', 'program'])
            ->withCount(['enrollments as active_enrollments' => fn($q) => $q->where('status', 'approved')]);

        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('academic_status')) $query->where('academic_status', $request->academic_status);

        $students = $query->get();

        $byDept = $students->groupBy('department_id')->map(fn($g) => [
            'department' => $g->first()->department?->name,
            'code'       => $g->first()->department?->code,
            'count'      => $g->count(),
            'male'       => $g->filter(fn($s) => $s->user?->gender === 'male')->count(),
            'female'     => $g->filter(fn($s) => $s->user?->gender === 'female')->count(),
        ])->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total'    => $students->count(),
                'active'   => $students->where('academic_status', 'regular')->count(),
                'male'     => $students->filter(fn($s) => $s->user?->gender === 'male')->count(),
                'female'   => $students->filter(fn($s) => $s->user?->gender === 'female')->count(),
                'avg_cgpa' => round($students->whereNotNull('cgpa')->avg('cgpa') ?? 0, 2),
            ],
            'by_department' => $byDept,
            'data'          => $students->take(50)->map(fn($s) => [
                'student_id'   => $s->student_id,
                'name'         => $s->user?->name,
                'department'   => $s->department?->code,
                'program'      => $s->program?->code,
                'semester'     => $s->semester,
                'cgpa'         => $s->cgpa,
                'status'       => $s->academic_status,
                'enrollments'  => $s->active_enrollments,
            ]),
        ]);
    }

    // ── GET /api/reports/fees ─────────────────────────────────
    public function fees(Request $request)
    {
        self::logVisit('reports', 'fees', 'visited', 'Viewed fee report');

        $semester    = $request->get('semester', 'Fall 2024');
        $academicYear= $request->get('academic_year', '2024-2025');

        $invoices = FeeInvoice::where('semester', $semester)
            ->where('academic_year', $academicYear)
            ->get();

        $payments = FeePayment::whereHas('invoice', fn($q) => $q
            ->where('semester', $semester)
            ->where('academic_year', $academicYear)
        )->get();

        $byType = $invoices->groupBy('fee_type')->map(fn($g, $type) => [
            'fee_type'  => $type,
            'invoiced'  => $g->sum('amount'),
            'collected' => $g->sum('paid_amount'),
            'pending'   => $g->sum('amount') - $g->sum('paid_amount'),
            'count'     => $g->count(),
        ])->values();

        $byMonth = $payments->groupBy(fn($p) => $p->payment_date?->format('Y-m'))->map(fn($g, $month) => [
            'month'     => $month,
            'collected' => $g->sum('amount'),
            'count'     => $g->count(),
        ])->sortKeys()->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_invoiced'  => $invoices->sum('amount'),
                'total_collected' => $invoices->sum('paid_amount'),
                'total_pending'   => $invoices->sum('amount') - $invoices->sum('paid_amount'),
                'paid_count'      => $invoices->where('status', 'paid')->count(),
                'unpaid_count'    => $invoices->whereIn('status', ['unpaid', 'overdue'])->count(),
                'collection_rate' => $invoices->sum('amount') > 0
                    ? round(($invoices->sum('paid_amount') / $invoices->sum('amount')) * 100, 1) : 0,
            ],
            'by_type'    => $byType,
            'by_month'   => $byMonth,
        ]);
    }

    // ── GET /api/reports/attendance ───────────────────────────
    public function attendance(Request $request)
    {
        self::logVisit('reports', 'attendance', 'visited', 'Viewed attendance report');

        $semester    = $request->get('semester', 'Spring 2025');
        $academicYear= $request->get('academic_year', '2024-2025');

        $sessions = AttendanceSession::where('semester', $semester)
            ->where('academic_year', $academicYear)
            ->withCount('records')
            ->get();

        $records = AttendanceRecord::whereHas('session', fn($q) => $q
            ->where('semester', $semester)
            ->where('academic_year', $academicYear)
        )->get();

        $total   = $records->count();
        $present = $records->where('status', 'present')->count();
        $absent  = $records->where('status', 'absent')->count();
        $late    = $records->where('status', 'late')->count();

        $byCourse = $sessions->groupBy('course_id')->map(fn($g) => [
            'course_id'    => $g->first()->course_id,
            'sessions'     => $g->count(),
            'total_records'=> $g->sum('records_count'),
        ])->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_sessions'  => $sessions->count(),
                'total_records'   => $total,
                'present'         => $present,
                'absent'          => $absent,
                'late'            => $late,
                'attendance_rate' => $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0,
            ],
            'by_course' => $byCourse,
        ]);
    }

    // ── GET /api/reports/exams ────────────────────────────────
    public function exams(Request $request)
    {
        self::logVisit('reports', 'exams', 'visited', 'Viewed exam report');

        $semester    = $request->get('semester', 'Fall 2024');
        $academicYear= $request->get('academic_year', '2024-2025');

        $grades = CourseGrade::where('semester', $semester)
            ->where('academic_year', $academicYear)
            ->where('is_published', true)
            ->with(['course', 'student.user'])
            ->get();

        $gradeDistribution = $grades->whereNotNull('grade_letter')
            ->groupBy('grade_letter')
            ->map(fn($g, $letter) => ['grade' => $letter, 'count' => $g->count()])
            ->sortByDesc('count')->values();

        $topStudents = $grades->whereNotNull('grade_point')
            ->groupBy('student_profile_id')
            ->map(fn($g) => [
                'name'       => $g->first()->student?->user?->name,
                'student_id' => $g->first()->student?->student_id,
                'avg_gpa'    => round($g->avg('grade_point'), 2),
                'courses'    => $g->count(),
            ])
            ->sortByDesc('avg_gpa')->take(10)->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_grades'    => $grades->count(),
                'avg_gpa'         => round($grades->whereNotNull('grade_point')->avg('grade_point') ?? 0, 2),
                'pass_count'      => $grades->where('grade_point', '>=', 2.0)->count(),
                'fail_count'      => $grades->where('grade_point', '<', 2.0)->where('grade_point', '>', 0)->count(),
                'distinction'     => $grades->where('grade_point', '>=', 3.75)->count(),
            ],
            'grade_distribution' => $gradeDistribution,
            'top_students'       => $topStudents,
        ]);
    }

    // ── GET /api/reports/admissions ───────────────────────────
    public function admissions(Request $request)
    {
        self::logVisit('reports', 'admissions', 'visited', 'Viewed admissions report');

        $year = $request->get('year', date('Y'));

        $admissions = Admission::whereYear('created_at', $year)->get();

        $byDept = $admissions->groupBy('department_id')->map(fn($g) => [
            'department' => $g->first()->department?->name ?? 'Unknown',
            'code'       => $g->first()->department?->code ?? '—',
            'applied'    => $g->count(),
            'accepted'   => $g->whereIn('status', ['accepted', 'enrolled'])->count(),
            'rejected'   => $g->where('status', 'rejected')->count(),
            'enrolled'   => $g->where('status', 'enrolled')->count(),
        ])->values();

        $byMonth = $admissions->groupBy(fn($a) => $a->created_at->format('M'))->map(fn($g, $m) => [
            'month' => $m, 'count' => $g->count(),
        ])->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'total'      => $admissions->count(),
                'accepted'   => $admissions->whereIn('status', ['accepted', 'enrolled'])->count(),
                'rejected'   => $admissions->where('status', 'rejected')->count(),
                'enrolled'   => $admissions->where('status', 'enrolled')->count(),
                'pending'    => $admissions->whereIn('status', ['applied', 'under_review', 'shortlisted'])->count(),
                'accept_rate'=> $admissions->count() > 0
                    ? round(($admissions->whereIn('status', ['accepted','enrolled'])->count() / $admissions->count()) * 100, 1) : 0,
            ],
            'by_department' => $byDept,
            'by_month'      => $byMonth,
        ]);
    }

    // ── GET /api/reports/pdf/{type} ───────────────────────────
    public function exportPdf(Request $request, string $type)
    {
        self::logVisit('reports', 'pdf', 'exported', "Exported PDF report: {$type}");

        $data = match($type) {
            'students'   => $this->getStudentReportData($request),
            'fees'       => $this->getFeeReportData($request),
            'attendance' => $this->getAttendanceReportData($request),
            'exams'      => $this->getExamReportData($request),
            'admissions' => $this->getAdmissionReportData($request),
            default      => []
        };

        $data['title']       = ucfirst($type) . ' Report';
        $data['generated_at']= now()->format('d M Y, h:i A');
        $data['generated_by']= auth()->user()->name;

        $pdf = Pdf::loadView("reports.{$type}", $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download("{$type}-report-" . now()->format('Y-m-d') . ".pdf");
    }

    // ── Private helpers ───────────────────────────────────────
    private function getStudentReportData(Request $request): array
    {
        $students = StudentProfile::with(['user', 'department', 'program'])->get();
        return [
            'students'      => $students,
            'total'         => $students->count(),
            'by_department' => $students->groupBy('department_id')->map(fn($g) => ['name' => $g->first()->department?->name, 'count' => $g->count()])->values(),
        ];
    }

    private function getFeeReportData(Request $request): array
    {
        $semester = $request->get('semester', 'Fall 2024');
        $invoices = FeeInvoice::with('student.user')->where('semester', $semester)->get();
        return ['invoices' => $invoices, 'semester' => $semester, 'total_invoiced' => $invoices->sum('amount'), 'total_collected' => $invoices->sum('paid_amount')];
    }

    private function getAttendanceReportData(Request $request): array
    {
        $semester = $request->get('semester', 'Spring 2025');
        $records  = AttendanceRecord::with(['student.user', 'session.course'])
            ->whereHas('session', fn($q) => $q->where('semester', $semester))->get();
        return ['records' => $records, 'semester' => $semester, 'total' => $records->count(), 'present' => $records->where('status', 'present')->count()];
    }

    private function getExamReportData(Request $request): array
    {
        $semester = $request->get('semester', 'Fall 2024');
        $grades   = CourseGrade::with(['student.user', 'course'])->where('semester', $semester)->where('is_published', true)->get();
        return ['grades' => $grades, 'semester' => $semester, 'avg_gpa' => round($grades->avg('grade_point') ?? 0, 2)];
    }

    private function getAdmissionReportData(Request $request): array
    {
        $year       = $request->get('year', date('Y'));
        $admissions = Admission::with('department')->whereYear('created_at', $year)->get();
        return ['admissions' => $admissions, 'year' => $year, 'total' => $admissions->count()];
    }
}
