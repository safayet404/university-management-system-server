<?php

namespace App\Http\Controllers;

use App\Models\ClassLevel;
use App\Models\GroupMember;
use App\Models\GroupSubject;
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * School sections and coaching batches, their students (with roll numbers and
 * the optional 4th subject) and the subjects taught in them.
 */
class StudentGroupController extends Controller
{
    use LogsPageVisit;

    // GET /api/class-levels
    public function levels(Request $request)
    {
        $year = $request->get('academic_year');

        $levels = ClassLevel::withCount(['subjects', 'groups as sections_count' => fn ($q) => $q
            ->where('kind', 'section')->when($year, fn ($w) => $w->where('academic_year', $year))])
            ->orderBy('sort_order')->get();

        return response()->json(['success' => true, 'data' => $levels]);
    }

    // GET /api/student-groups
    public function index(Request $request)
    {
        $groups = StudentGroup::with(['classLevel', 'classTeacher.user'])
            ->withCount('activeMembers')
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->kind))
            ->when($request->filled('class_level_id'), fn ($q) => $q->where('class_level_id', $request->class_level_id))
            ->when($request->filled('academic_year'), fn ($q) => $q->where('academic_year', $request->academic_year))
            ->get()
            ->sortBy(fn ($g) => [$g->classLevel?->sort_order ?? 999, $g->name, $g->shift])
            ->values();

        return response()->json(['success' => true, 'data' => $groups->map(fn ($g) => $this->format($g))]);
    }

    // GET /api/student-groups/{id}
    public function show($id)
    {
        $group = StudentGroup::with([
            'classLevel', 'classTeacher.user',
            'members' => fn ($q) => $q->with(['student.user', 'optionalSubject'])->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('roll_number'),
            'groupSubjects' => fn ($q) => $q->with(['subject', 'teacher.user']),
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->format($group) + [
            'members' => $group->members->map(fn ($m) => [
                'id'               => $m->id,
                'student_profile_id' => $m->student_profile_id,
                'student_id'       => $m->student?->student_id,
                'name'             => $m->student?->user?->name,
                'roll_number'      => $m->roll_number,
                'status'           => $m->status,
                'optional_subject' => $m->optionalSubject ? ['id' => $m->optionalSubject->id, 'name' => $m->optionalSubject->name] : null,
                'joined_on'        => $m->joined_on?->format('Y-m-d'),
            ]),
            'subjects' => $group->groupSubjects->sortBy(fn ($gs) => $gs->subject?->sort_order)->values()->map(fn ($gs) => [
                'subject_id'  => $gs->subject_id,
                'name'        => $gs->subject?->name,
                'is_optional' => (bool) $gs->subject?->is_optional,
                'teacher'     => $gs->teacher ? ['id' => $gs->teacher->id, 'name' => $gs->teacher->user?->name] : null,
            ]),
        ]]);
    }

    // POST /api/student-groups
    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));
        $this->ensureUnique($data);

        $group = DB::transaction(function () use ($data) {
            $group = StudentGroup::create($data);

            // A new section starts with every subject of its class and stream
            if ($group->kind === 'section' && $group->class_level_id) {
                Subject::where('class_level_id', $group->class_level_id)
                    ->where(fn ($q) => $q->whereNull('stream')->orWhere('stream', $group->stream))
                    ->get()
                    ->each(fn ($s) => GroupSubject::create(['student_group_id' => $group->id, 'subject_id' => $s->id]));
            }

            return $group;
        });

        self::logVisit('classes', 'create', 'created', "Created {$group->kind}: {$group->display_name}", [], [], StudentGroup::class, $group->id);

        return response()->json(['success' => true, 'message' => ucfirst($group->kind) . ' created.', 'data' => $this->format($group->load(['classLevel', 'classTeacher.user']))], 201);
    }

    // PUT /api/student-groups/{id}
    public function update(Request $request, $id)
    {
        $group = StudentGroup::findOrFail($id);
        // Validate the group as it will be after the change
        $merged = array_merge($group->only(array_keys($this->rules($request))), $request->only(array_keys($this->rules($request))));
        $data = validator($merged, $this->rules(new Request($merged)))->validate();
        $this->ensureUnique($data, $group->id);

        $group->update($data);

        return response()->json(['success' => true, 'message' => 'Updated.', 'data' => $this->format($group->fresh(['classLevel', 'classTeacher.user']))]);
    }

    // DELETE /api/student-groups/{id}
    public function destroy($id)
    {
        $group = StudentGroup::withCount('activeMembers')->findOrFail($id);
        if ($group->active_members_count > 0) {
            return response()->json(['success' => false, 'message' => "Move or remove its {$group->active_members_count} students before deleting this {$group->kind}."], 422);
        }
        $group->delete();

        return response()->json(['success' => true, 'message' => ucfirst($group->kind) . ' deleted.']);
    }

    // POST /api/student-groups/{id}/members
    public function addMembers(Request $request, $id)
    {
        $request->validate([
            'student_profile_ids'   => 'required|array|min:1',
            'student_profile_ids.*' => 'integer|distinct|exists:student_profiles,id',
        ]);

        $added = DB::transaction(function () use ($request, $id) {
            // Lock the group so concurrent additions cannot overfill it or reuse a roll number
            $group  = StudentGroup::lockForUpdate()->findOrFail($id);
            $ids    = collect($request->student_profile_ids);
            $active = $group->members()->where('status', 'active')->pluck('student_profile_id');
            $new    = $ids->diff($active)->values();

            if ($group->capacity && $active->count() + $new->count() > $group->capacity) {
                throw ValidationException::withMessages(['student_profile_ids' => "Only " . max(0, $group->capacity - $active->count()) . " seats are left in this {$group->kind}."]);
            }

            // A school student belongs to one section per academic year
            if ($group->kind === 'section') {
                $elsewhere = GroupMember::with(['student.user', 'group.classLevel'])
                    ->whereIn('student_profile_id', $new)
                    ->where('status', 'active')
                    ->where('student_group_id', '!=', $group->id)
                    ->whereHas('group', fn ($q) => $q->where('kind', 'section')->where('academic_year', $group->academic_year))
                    ->get();
                if ($elsewhere->isNotEmpty()) {
                    $names = $elsewhere->map(fn ($m) => "{$m->student?->user?->name} ({$m->group->display_name})")->implode(', ');
                    throw ValidationException::withMessages(['student_profile_ids' => "Already in another section this year: {$names}."]);
                }
            }

            $nextRoll = (int) $group->members()->max('roll_number');
            foreach ($new as $studentId) {
                GroupMember::updateOrCreate(
                    ['student_group_id' => $group->id, 'student_profile_id' => $studentId],
                    ['status' => 'active', 'roll_number' => ++$nextRoll, 'joined_on' => today(), 'left_on' => null]
                );
            }

            return $new->count();
        });

        return response()->json(['success' => true, 'message' => "{$added} students added.", 'added' => $added]);
    }

    // PATCH /api/student-groups/{id}/members/{memberId}
    public function updateMember(Request $request, $id, $memberId)
    {
        $group  = StudentGroup::findOrFail($id);
        $member = $group->members()->findOrFail($memberId);

        $data = $request->validate([
            'roll_number'         => ['sometimes', 'nullable', 'integer', 'min:1', 'max:9999',
                Rule::unique('group_members')->where('student_group_id', $group->id)->ignore($member->id)],
            'optional_subject_id' => ['sometimes', 'nullable', 'integer', Rule::exists('subjects', 'id')->where(fn ($q) => $q
                ->where('is_optional', true)
                ->where('class_level_id', $group->class_level_id)
                ->where(fn ($w) => $w->whereNull('stream')->orWhere('stream', $group->stream)))],
        ], [
            'roll_number.unique'         => 'Another student in this ' . $group->kind . ' already has that roll number.',
            'optional_subject_id.exists' => 'Choose an optional subject offered in this class.',
        ]);

        $member->update($data);

        return response()->json(['success' => true, 'message' => 'Updated.']);
    }

    // DELETE /api/student-groups/{id}/members/{memberId}
    public function removeMember($id, $memberId)
    {
        $member = StudentGroup::findOrFail($id)->members()->findOrFail($memberId);
        // Keep the history; free the roll number for someone else
        $member->update(['status' => 'left', 'left_on' => today(), 'roll_number' => null]);

        return response()->json(['success' => true, 'message' => 'Student removed.']);
    }

    // PUT /api/student-groups/{id}/subjects
    public function syncSubjects(Request $request, $id)
    {
        $group = StudentGroup::findOrFail($id);
        $request->validate([
            'subjects'                      => 'present|array',
            'subjects.*.subject_id'         => 'required|integer|distinct|exists:subjects,id',
            'subjects.*.faculty_profile_id' => 'nullable|integer|exists:faculty_profiles,id',
        ]);

        // A section only teaches subjects of its own class and stream
        if ($group->kind === 'section') {
            $foreign = Subject::whereIn('id', collect($request->subjects)->pluck('subject_id'))
                ->where(fn ($q) => $q->where('class_level_id', '!=', $group->class_level_id)
                    ->orWhereNull('class_level_id')
                    ->orWhere(fn ($w) => $w->whereNotNull('stream')->where('stream', '!=', $group->stream)))
                ->pluck('name');
            if ($foreign->isNotEmpty()) {
                throw ValidationException::withMessages(['subjects' => 'Not taught in this class: ' . $foreign->implode(', ') . '.']);
            }
        }

        DB::transaction(function () use ($group, $request) {
            $group->groupSubjects()->whereNotIn('subject_id', collect($request->subjects)->pluck('subject_id'))->delete();
            foreach ($request->subjects as $row) {
                GroupSubject::updateOrCreate(
                    ['student_group_id' => $group->id, 'subject_id' => $row['subject_id']],
                    ['faculty_profile_id' => $row['faculty_profile_id'] ?? null]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Subjects updated.']);
    }

    private function rules(Request $request): array
    {
        $isSection = $request->input('kind') === 'section';

        return [
            'kind'             => 'required|in:section,batch',
            'name'             => 'required|string|max:100',
            'class_level_id'   => [$isSection ? 'required' : 'nullable', 'exists:class_levels,id'],
            'stream'           => 'nullable|in:science,arts,commerce',
            'academic_year'    => 'required|string|max:20',
            'shift'            => 'nullable|in:morning,day,evening',
            'class_teacher_id' => 'nullable|exists:faculty_profiles,id',
            'capacity'         => 'nullable|integer|min:1|max:1000',
            'starts_on'        => 'nullable|date',
            'ends_on'          => 'nullable|date|after_or_equal:starts_on',
            'monthly_fee'      => 'nullable|numeric|min:0',
            'schedule_note'    => 'nullable|string|max:255',
            'is_active'        => 'sometimes|boolean',
        ];
    }

    private function ensureUnique(array $data, ?int $ignoreId = null): void
    {
        $exists = StudentGroup::where('kind', $data['kind'])
            ->where('academic_year', $data['academic_year'])
            ->where('name', $data['name'])
            ->where('class_level_id', $data['class_level_id'] ?? null)
            ->where('shift', $data['shift'] ?? null)
            ->where('stream', $data['stream'] ?? null)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => "That {$data['kind']} already exists for {$data['academic_year']}."]);
        }
    }

    private function format(StudentGroup $g): array
    {
        return [
            'id'             => $g->id,
            'kind'           => $g->kind,
            'name'           => $g->name,
            'display_name'   => $g->display_name,
            'class_level'    => $g->classLevel ? ['id' => $g->classLevel->id, 'name' => $g->classLevel->name, 'has_streams' => $g->classLevel->has_streams] : null,
            'stream'         => $g->stream,
            'academic_year'  => $g->academic_year,
            'shift'          => $g->shift,
            'class_teacher'  => $g->classTeacher ? ['id' => $g->classTeacher->id, 'name' => $g->classTeacher->user?->name] : null,
            'capacity'       => $g->capacity,
            'students_count' => $g->active_members_count ?? $g->activeMembers()->count(),
            'starts_on'      => $g->starts_on?->format('Y-m-d'),
            'ends_on'        => $g->ends_on?->format('Y-m-d'),
            'monthly_fee'    => $g->monthly_fee !== null ? (float) $g->monthly_fee : null,
            'schedule_note'  => $g->schedule_note,
            'is_active'      => $g->is_active,
        ];
    }
}
