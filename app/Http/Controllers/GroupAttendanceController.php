<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendance;
use App\Models\DailyAttendanceRecord;
use App\Models\GroupMember;
use App\Models\StudentGroup;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Once-a-day attendance for a school section or coaching batch, taken by its
 * class teacher. Late counts as attended; leave is left out of the percentage.
 */
class GroupAttendanceController extends Controller
{
    use LogsPageVisit;

    // GET /api/student-groups/{id}/attendance?date=YYYY-MM-DD
    public function sheet(Request $request, $id)
    {
        $group = StudentGroup::findOrFail($id);
        Gate::authorize('take-group-attendance', $group);
        $date = $request->validate(['date' => 'nullable|date|before_or_equal:today'])['date'] ?? today()->toDateString();

        $existing = DailyAttendance::with('records')->where('student_group_id', $group->id)->whereDate('date', $date)->first();
        $marks    = $existing?->records->keyBy('student_profile_id') ?? collect();

        $students = $this->roster($group)->map(fn ($m) => [
            'student_profile_id' => $m->student_profile_id,
            'roll_number'        => $m->roll_number,
            'name'               => $m->student?->user?->name,
            'student_id'         => $m->student?->student_id,
            'status'             => $marks[$m->student_profile_id]->status ?? 'present',
            'remarks'            => $marks[$m->student_profile_id]->remarks ?? null,
        ]);

        return response()->json(['success' => true, 'data' => [
            'date'     => $date,
            'taken'    => (bool) $existing,
            'taken_by' => $existing?->takenBy?->name,
            'note'     => $existing?->note,
            'students' => $students,
        ]]);
    }

    // PUT /api/student-groups/{id}/attendance
    public function save(Request $request, $id)
    {
        $group = StudentGroup::findOrFail($id);
        Gate::authorize('take-group-attendance', $group);

        $data = $request->validate([
            'date'                       => 'required|date|before_or_equal:today',
            'note'                       => 'nullable|string|max:255',
            'records'                    => 'required|array|min:1',
            'records.*.student_profile_id' => 'required|integer|distinct',
            'records.*.status'           => ['required', Rule::in(DailyAttendanceRecord::STATUSES)],
            'records.*.remarks'          => 'nullable|string|max:255',
        ], ['date.before_or_equal' => 'Attendance cannot be taken for a future date.']);

        // Only current students of this section or batch
        $members  = $this->roster($group)->pluck('student_profile_id');
        $outsiders = collect($data['records'])->pluck('student_profile_id')->diff($members);
        if ($outsiders->isNotEmpty()) {
            throw ValidationException::withMessages(['records' => 'Some students are not in this ' . $group->kind . '.']);
        }

        $attendance = DB::transaction(function () use ($group, $data, $request) {
            // Re-taking the same day updates it (whereDate: dates may be stored with a time part)
            $attendance = DailyAttendance::where('student_group_id', $group->id)->whereDate('date', $data['date'])->lockForUpdate()->first()
                ?? new DailyAttendance(['student_group_id' => $group->id, 'date' => $data['date']]);
            $attendance->fill(['taken_by' => $request->user()->id, 'note' => $data['note'] ?? null])->save();

            foreach ($data['records'] as $row) {
                DailyAttendanceRecord::updateOrCreate(
                    ['daily_attendance_id' => $attendance->id, 'student_profile_id' => $row['student_profile_id']],
                    ['status' => $row['status'], 'remarks' => $row['remarks'] ?? null]
                );
            }

            return $attendance;
        });

        $counts = collect($data['records'])->countBy('status');
        self::logVisit('attendance', 'daily', 'created', "Attendance for {$group->display_name} on {$data['date']}", [], $counts->all(), DailyAttendance::class, $attendance->id);

        return response()->json([
            'success' => true,
            'message' => 'Attendance saved.',
            'summary' => collect(DailyAttendanceRecord::STATUSES)->mapWithKeys(fn ($s) => [$s => $counts[$s] ?? 0]),
        ]);
    }

    // GET /api/student-groups/{id}/attendance/report?month=YYYY-MM
    public function report(Request $request, $id)
    {
        $group = StudentGroup::findOrFail($id);
        abort_unless($this->canView($request, $group), 403);

        $month = $request->validate(['month' => 'nullable|date_format:Y-m'])['month'] ?? today()->format('Y-m');
        $from  = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $to    = $from->copy()->endOfMonth();

        $days = DailyAttendance::with('records')
            ->where('student_group_id', $group->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $byStudent = $days->flatMap->records->groupBy('student_profile_id');

        $rows = $this->roster($group)->map(function ($m) use ($byStudent) {
            $counts  = ($byStudent[$m->student_profile_id] ?? collect())->countBy('status');
            $counted = ($counts['present'] ?? 0) + ($counts['late'] ?? 0) + ($counts['absent'] ?? 0);

            return [
                'student_profile_id' => $m->student_profile_id,
                'roll_number'        => $m->roll_number,
                'name'               => $m->student?->user?->name,
                'student_id'         => $m->student?->student_id,
                'present'            => $counts['present'] ?? 0,
                'late'               => $counts['late'] ?? 0,
                'absent'             => $counts['absent'] ?? 0,
                'leave'              => $counts['leave'] ?? 0,
                // Leave is excused, so it does not count against the student
                'percent'            => $counted ? (int) round((($counts['present'] ?? 0) + ($counts['late'] ?? 0)) / $counted * 100) : null,
            ];
        });

        return response()->json(['success' => true, 'data' => [
            'month'     => $month,
            'days_held' => $days->count(),
            'days'      => $days->map(fn ($d) => [
                'date'    => $d->date->toDateString(),
                'present' => $d->records->whereIn('status', ['present', 'late'])->count(),
                'absent'  => $d->records->where('status', 'absent')->count(),
            ]),
            'students'  => $rows,
        ]]);
    }

    // GET /api/me/teaching-groups
    public function myGroups(Request $request)
    {
        $facultyId = $request->user()->facultyProfile?->id;
        abort_unless($facultyId, 404, 'No teacher profile is linked to this account.');

        $groups = StudentGroup::with('classLevel')
            ->withCount('activeMembers')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('class_teacher_id', $facultyId)
                ->orWhereHas('groupSubjects', fn ($s) => $s->where('faculty_profile_id', $facultyId)))
            ->get()
            ->sortBy(fn ($g) => [$g->classLevel?->sort_order ?? 999, $g->name])
            ->values();

        $takenToday = DailyAttendance::whereIn('student_group_id', $groups->pluck('id'))->whereDate('date', today())->pluck('student_group_id');

        return response()->json(['success' => true, 'data' => $groups->map(fn ($g) => [
            'id'              => $g->id,
            'kind'            => $g->kind,
            'display_name'    => $g->display_name,
            'students_count'  => $g->active_members_count,
            'is_class_teacher' => (int) $g->class_teacher_id === (int) $facultyId,
            'can_take_attendance' => Gate::allows('take-group-attendance', $g),
            'taken_today'     => $takenToday->contains($g->id),
        ])]);
    }

    private function roster(StudentGroup $group)
    {
        return GroupMember::with('student.user')
            ->where('student_group_id', $group->id)
            ->where('status', 'active')
            ->orderBy('roll_number')
            ->get();
    }

    /** Takers, plus any teacher of a subject in the group, can read the report. */
    private function canView(Request $request, StudentGroup $group): bool
    {
        if (Gate::allows('take-group-attendance', $group)) {
            return true;
        }
        $facultyId = $request->user()->facultyProfile?->id;

        return $facultyId && $group->groupSubjects()->where('faculty_profile_id', $facultyId)->exists();
    }
}
