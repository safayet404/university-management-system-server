<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\StudentProfile;
use App\Models\TimetableSlot;
use App\Support\AcademicTerm;
use App\Support\GradeReport;
use Illuminate\Support\Collection;

/**
 * Self-service endpoints for the signed-in student. Every query is scoped to
 * the caller's own student profile; nothing here takes a student id.
 */
class StudentPortalController extends Controller
{
    private const DAYS = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    // GET /api/me/student/overview
    public function overview()
    {
        $student = $this->student()->load(['user', 'department', 'program']);
        $term    = AcademicTerm::current();
        $courses = $this->currentEnrollments($student);
        $report  = GradeReport::for($student);
        $fees    = FeeInvoice::where('student_profile_id', $student->id)->whereNotIn('status', ['paid', 'waived'])->get();

        $attendance = $this->attendanceByCourse($student, $courses);
        $held       = $attendance->sum('total');

        return response()->json(['success' => true, 'data' => [
            'profile' => [
                'name'                   => $student->user?->name,
                'email'                  => $student->user?->email,
                'avatar_url'             => $student->user?->avatar_url,
                'student_id'             => $student->student_id,
                'department'             => $student->department?->name,
                'program'                => $student->program?->name,
                'batch'                  => $student->batch,
                'semester'               => $student->semester,
                'section'                => $student->section,
                'academic_status'        => $student->academic_status,
                'total_credits_required' => $student->total_credits_required,
            ],
            'term'               => $term,
            'cgpa'               => $report['cgpa'],
            'earned_credits'     => $report['earned_credits'],
            'current_courses'    => $courses->count(),
            'current_credits'    => $courses->sum(fn ($e) => (int) $e->course?->credit_hours),
            'attendance_percent' => $held ? round($attendance->sum('attended') / $held * 100) : null,
            'fees_due'           => round($fees->sum(fn ($i) => $i->due_amount), 2),
            'next_fee_due_date'  => $fees->min('due_date')?->format('Y-m-d'),
            'latest_term'        => $report['terms']->last(),
        ]]);
    }

    // GET /api/me/student/courses
    public function courses()
    {
        $student    = $this->student();
        $courses    = $this->currentEnrollments($student, ['pending', 'approved', 'completed']);
        $attendance = $this->attendanceByCourse($student, $courses)->keyBy('course_id');

        return response()->json(['success' => true, 'term' => AcademicTerm::current(), 'data' => $courses->map(fn ($e) => [
            'enrollment_id' => $e->id,
            'status'        => $e->status,
            'section'       => $e->section,
            'course'        => [
                'id'      => $e->course?->id,
                'code'    => $e->course?->code,
                'name'    => $e->course?->name,
                'credits' => (int) $e->course?->credit_hours,
                'type'    => $e->course?->course_type,
            ],
            'faculty'            => $e->course?->faculty?->user?->name,
            'attendance_percent' => $attendance[$e->course_id]['percent'] ?? null,
        ])->values()]);
    }

    // GET /api/me/student/grades
    public function grades()
    {
        return response()->json(['success' => true, 'data' => GradeReport::for($this->student())]);
    }

    // GET /api/me/student/attendance
    public function attendance()
    {
        $student = $this->student();
        $courses = $this->currentEnrollments($student);

        return response()->json([
            'success' => true,
            'term'    => AcademicTerm::current(),
            'data'    => $this->attendanceByCourse($student, $courses)->values(),
        ]);
    }

    // GET /api/me/student/fees
    public function fees()
    {
        $invoices = FeeInvoice::with('payments')
            ->where('student_profile_id', $this->student()->id)
            ->orderByDesc('due_date')
            ->get();

        return response()->json(['success' => true, 'data' => [
            'summary' => [
                'total_billed' => round($invoices->sum(fn ($i) => $i->net_amount), 2),
                'total_paid'   => round($invoices->sum(fn ($i) => (float) $i->paid_amount), 2),
                'total_due'    => round($invoices->whereNotIn('status', ['paid', 'waived'])->sum(fn ($i) => $i->due_amount), 2),
            ],
            'invoices' => $invoices->map(fn ($i) => [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'fee_type'       => $i->fee_type,
                'description'    => $i->description,
                'semester'       => $i->semester,
                'academic_year'  => $i->academic_year,
                'net_amount'     => round($i->net_amount, 2),
                'paid_amount'    => (float) $i->paid_amount,
                'due_amount'     => round(max(0, $i->due_amount), 2),
                'due_date'       => $i->due_date?->format('Y-m-d'),
                'status'         => $i->status,
                'payments'       => $i->payments->map(fn ($p) => [
                    'id'             => $p->id,
                    'transaction_id' => $p->transaction_id,
                    'amount'         => (float) $p->amount,
                    'method'         => $p->payment_method,
                    'date'           => $p->payment_date?->format('Y-m-d'),
                ])->values(),
            ])->values(),
        ]]);
    }

    // GET /api/me/student/timetable
    public function timetable()
    {
        $student = $this->student();
        $term    = AcademicTerm::current();
        $courses = $this->currentEnrollments($student);

        $slots = TimetableSlot::with(['course', 'faculty.user'])
            ->whereIn('course_id', $courses->pluck('course_id'))
            ->where('semester', $term['semester'])
            ->where('academic_year', $term['academic_year'])
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($s) => [array_search($s->day_of_week, self::DAYS), $s->start_time])
            ->values();

        return response()->json(['success' => true, 'term' => $term, 'data' => $slots->map(fn ($s) => [
            'day'        => $s->day_of_week,
            'start_time' => substr($s->start_time, 0, 5),
            'end_time'   => substr($s->end_time, 0, 5),
            'room'       => $s->room,
            'building'   => $s->building,
            'type'       => $s->slot_type,
            'course'     => ['code' => $s->course?->code, 'name' => $s->course?->name],
            'faculty'    => $s->faculty?->user?->name,
        ])]);
    }

    private function student(): StudentProfile
    {
        $student = auth()->user()->studentProfile;
        abort_unless($student, 404, 'No student profile is linked to this account.');

        return $student;
    }

    // Publishing grades moves an enrollment to "completed"; it still belongs to this term
    private function currentEnrollments(StudentProfile $student, array $statuses = ['approved', 'completed']): Collection
    {
        $term = AcademicTerm::current();

        return Enrollment::with('course.faculty.user')
            ->where('student_profile_id', $student->id)
            ->where('semester', $term['semester'])
            ->where('academic_year', $term['academic_year'])
            ->whereIn('status', $statuses)
            ->get();
    }

    /** Present and late both count as attended. */
    private function attendanceByCourse(StudentProfile $student, Collection $enrollments): Collection
    {
        $term = AcademicTerm::current();

        $records = AttendanceRecord::with('session')
            ->where('student_profile_id', $student->id)
            ->whereHas('session', fn ($q) => $q
                ->whereIn('course_id', $enrollments->pluck('course_id'))
                ->where('semester', $term['semester'])
                ->where('academic_year', $term['academic_year']))
            ->get()
            ->groupBy(fn ($r) => $r->session->course_id);

        return $enrollments->map(function ($e) use ($records) {
            $rows     = $records->get($e->course_id, collect());
            $counts   = $rows->countBy('status');
            $attended = ($counts['present'] ?? 0) + ($counts['late'] ?? 0);

            return [
                'course_id' => $e->course_id,
                'code'      => $e->course?->code,
                'name'      => $e->course?->name,
                'total'     => $rows->count(),
                'present'   => $counts['present'] ?? 0,
                'late'      => $counts['late'] ?? 0,
                'absent'    => $counts['absent'] ?? 0,
                'excused'   => $counts['excused'] ?? 0,
                'attended'  => $attended,
                'percent'   => $rows->count() ? round($attended / $rows->count() * 100) : null,
            ];
        })->keyBy('course_id');
    }
}
