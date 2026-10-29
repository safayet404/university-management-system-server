<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendanceRecord;
use App\Models\FeeInvoice;
use App\Models\GroupMember;
use App\Models\StudentProfile;
use App\Models\TermExam;
use App\Support\ExamResults;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The student portal of schools and coaching centres (guardians use the
 * student's login): their section or batches, daily attendance, published
 * results with report cards, and fee dues. Always the caller's own data.
 */
class SchoolPortalController extends Controller
{
    // GET /api/me/student/overview-school
    public function overview()
    {
        $student = $this->student();
        $groups  = $this->memberships($student);
        $month   = $this->attendance($student, today()->startOfMonth(), today());
        $today   = DailyAttendanceRecord::where('student_profile_id', $student->id)
            ->whereHas('attendance', fn ($q) => $q->whereDate('date', today()))
            ->value('status');
        $latest  = collect($this->results($student))->first();
        $dues    = FeeInvoice::where('student_profile_id', $student->id)->whereIn('status', ['unpaid', 'partial', 'overdue'])->get();

        return response()->json(['success' => true, 'data' => [
            'name'       => $student->user?->name,
            'student_id' => $student->student_id,
            'groups'     => $groups->map(fn ($m) => $this->groupSummary($m))->values(),
            'attendance' => ['today' => $today, 'month' => $month['summary']],
            'latest_result' => $latest,
            'fees'       => [
                'due'       => round($dues->sum(fn ($i) => max(0, $i->due_amount)), 2),
                'invoices'  => $dues->count(),
                'next_due_date' => $dues->min('due_date')?->toDateString(),
            ],
        ]]);
    }

    // GET /api/me/student/class
    public function myClass()
    {
        $groups = $this->memberships($this->student())->load(['group.groupSubjects.subject', 'group.groupSubjects.teacher.user']);

        return response()->json(['success' => true, 'data' => $groups->map(fn ($m) => $this->groupSummary($m) + [
            'subjects' => ExamResults::subjectsFor(ExamResults::groupSubjects($m->group), $m)->map(fn ($s) => [
                'name'      => $s->name,
                'code'      => $s->code,
                'is_fourth' => (bool) $s->is_optional,
                'teacher'   => $m->group->groupSubjects->firstWhere('subject_id', $s->id)?->teacher?->user?->name,
            ])->values(),
        ])->values()]);
    }

    // GET /api/me/student/daily-attendance?month=YYYY-MM
    public function dailyAttendance(Request $request)
    {
        $month = $request->validate(['month' => 'nullable|date_format:Y-m'])['month'] ?? today()->format('Y-m');
        $from  = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        $to    = $from->copy()->endOfMonth();

        $student = $this->student();
        $data    = $this->attendance($student, $from, $to);
        $year    = $this->attendance($student, today()->startOfYear(), today());

        return response()->json(['success' => true, 'data' => [
            'month'   => $month,
            'days'    => $data['days'],
            'summary' => $data['summary'],
            'year'    => $year['summary'],
        ]]);
    }

    // GET /api/me/student/exam-results
    public function examResults()
    {
        return response()->json(['success' => true, 'data' => $this->results($this->student())]);
    }

    // GET /api/me/student/exam-results/{examId}/report-card
    public function reportCard(Request $request, $examId)
    {
        $student = $this->student();
        $exam    = TermExam::with(['classLevel', 'batch'])->where('status', 'published')->findOrFail($examId);
        $member  = $this->memberships($student)->first(fn ($m) => $exam->covers($m->group));
        abort_unless($member, 404, 'This result is not available.');

        $row = collect(ExamResults::build($exam, $exam->groups())[$member->group->id]['students'])->where('student_profile_id', $student->id);
        $pdf = ExamResults::reportCards($exam, $member->group, $row);
        $name = Str::slug("{$exam->name} {$student->user?->name}") . '.pdf';

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    private function student(): StudentProfile
    {
        $student = auth()->user()->studentProfile;
        abort_unless($student, 404, 'No student profile is linked to this account.');

        return $student;
    }

    /** Current sections/batches of the student. */
    private function memberships(StudentProfile $student): Collection
    {
        return GroupMember::with(['group.classLevel', 'group.classTeacher.user', 'optionalSubject'])
            ->where('student_profile_id', $student->id)
            ->where('status', 'active')
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->get();
    }

    private function groupSummary(GroupMember $m): array
    {
        $g = $m->group;

        return [
            'id'            => $g->id,
            'kind'          => $g->kind,
            'display_name'  => $g->display_name,
            'academic_year' => $g->academic_year,
            'roll_number'   => $m->roll_number,
            'class_teacher' => $g->classTeacher?->user?->name,
            'fourth_subject' => $m->optionalSubject?->name,
            'schedule_note' => $g->schedule_note,
            'monthly_fee'   => $g->monthly_fee !== null ? (float) $m->group->monthly_fee - (float) $m->monthly_discount : null,
        ];
    }

    /** Day-by-day statuses and the percentage (late counts as attended, leave is left out). */
    private function attendance(StudentProfile $student, Carbon $from, Carbon $to): array
    {
        $records = DailyAttendanceRecord::with('attendance.group')
            ->where('student_profile_id', $student->id)
            ->whereHas('attendance', fn ($q) => $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to))  // whereDate: dates may carry a time part
            ->get()
            ->sortBy(fn ($r) => $r->attendance->date)
            ->values();

        $counts  = $records->countBy('status');
        $counted = ($counts['present'] ?? 0) + ($counts['late'] ?? 0) + ($counts['absent'] ?? 0);

        return [
            'days' => $records->map(fn ($r) => [
                'date'    => $r->attendance->date->toDateString(),
                'status'  => $r->status,
                'remarks' => $r->remarks,
                'group'   => $r->attendance->group?->display_name,
            ]),
            'summary' => collect(DailyAttendanceRecord::STATUSES)->mapWithKeys(fn ($s) => [$s => $counts[$s] ?? 0])->all() + [
                'days'    => $records->count(),
                'percent' => $counted ? (int) round((($counts['present'] ?? 0) + ($counts['late'] ?? 0)) / $counted * 100) : null,
            ],
        ];
    }

    /** The student's rows of every published exam of their current groups, newest first. */
    private function results(StudentProfile $student): array
    {
        $out = [];
        foreach ($this->memberships($student) as $member) {
            $group = $member->group;
            $exams = TermExam::with(['classLevel', 'batch'])
                ->where('status', 'published')
                ->where(fn ($q) => $group->kind === 'batch'
                    ? $q->where('student_group_id', $group->id)
                    : $q->where('class_level_id', $group->class_level_id)->where('academic_year', $group->academic_year))
                ->get()
                ->filter(fn ($e) => $e->covers($group));

            foreach ($exams as $exam) {
                $row = collect(ExamResults::build($exam, $exam->groups())[$group->id]['students'])->firstWhere('student_profile_id', $student->id);
                if (! $row) {
                    continue;
                }
                $out[] = [
                    'exam' => [
                        'id' => $exam->id, 'name' => $exam->name, 'kind' => $exam->kind, 'academic_year' => $exam->academic_year,
                        'starts_on' => $exam->starts_on?->toDateString(), 'published_at' => $exam->published_at?->toIso8601String(),
                    ],
                    'mode'         => ExamResults::mode($exam),
                    'group'        => $group->display_name,
                    'group_size'   => $group->activeMembers()->count(),
                    'result'       => $row,
                ];
            }
        }

        usort($out, fn ($a, $b) => strcmp($b['exam']['published_at'] ?? '', $a['exam']['published_at'] ?? ''));

        return $out;
    }
}
