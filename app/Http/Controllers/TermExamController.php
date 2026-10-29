<?php

namespace App\Http\Controllers;

use App\Models\ExamMark;
use App\Models\ExamRemark;
use App\Models\GroupMember;
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Models\TermExam;
use App\Support\ExamResults;
use App\Support\Institution;
use App\Traits\LogsPageVisit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * School term exams and coaching model tests: subject teachers enter marks,
 * the class teacher writes remarks, an admin publishes, and report cards
 * come out as PDF. Marks and remarks are locked once published.
 */
class TermExamController extends Controller
{
    use LogsPageVisit;

    // GET /api/term-exams
    public function index(Request $request)
    {
        $exams = TermExam::with(['classLevel', 'batch'])
            ->when($request->academic_year, fn ($q, $y) => $q->where('academic_year', $y))
            ->when($request->class_level_id, fn ($q, $id) => $q->where('class_level_id', $id))
            ->when($request->student_group_id, fn ($q, $id) => $q->where('student_group_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('academic_year')->orderByDesc('starts_on')->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $exams->map(fn ($e) => $this->summary($e))]);
    }

    // POST /api/term-exams
    public function store(Request $request)
    {
        $coaching = Institution::isCoaching();
        $data = $request->validate($this->rules() + [
            'class_level_id'   => [$coaching ? 'prohibited' : 'required', 'integer', 'exists:class_levels,id'],
            'student_group_id' => [$coaching ? 'required' : 'prohibited', 'integer', Rule::exists('student_groups', 'id')->where('kind', 'batch')->whereNull('deleted_at')],
            'academic_year'    => [$coaching ? 'nullable' : 'required', 'string', 'max:20'],
        ]);

        if ($coaching) {
            $data['academic_year'] ??= StudentGroup::find($data['student_group_id'])->academic_year ?? (string) now()->year;
        }

        $exam = TermExam::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);

        return response()->json(['success' => true, 'message' => 'Exam created.', 'data' => $this->summary($exam->load('classLevel', 'batch'))], 201);
    }

    // PUT /api/term-exams/{id}
    public function update(Request $request, $id)
    {
        $exam = TermExam::findOrFail($id);
        $this->ensureDraft($exam);

        // Which class or batch sits it is fixed once created
        $exam->update($request->validate($this->rules()));

        return response()->json(['success' => true, 'message' => 'Exam updated.', 'data' => $this->summary($exam->load('classLevel', 'batch'))]);
    }

    // DELETE /api/term-exams/{id}
    public function destroy($id)
    {
        $exam = TermExam::findOrFail($id);
        $this->ensureDraft($exam);
        $exam->delete();

        return response()->json(['success' => true, 'message' => 'Exam deleted.']);
    }

    // GET /api/term-exams/{id}
    public function show(Request $request, $id)
    {
        $exam   = TermExam::with(['classLevel', 'batch'])->findOrFail($id);
        $groups = $exam->groups()->load(['groupSubjects.subject', 'groupSubjects.teacher.user', 'activeMembers']);
        $groups = $groups->filter(fn ($g) => Gate::allows('view-group', $g))->values();

        // Marks entered per student and subject, to show progress
        $entered = ExamMark::where('term_exam_id', $exam->id)
            ->whereIn('student_profile_id', $groups->flatMap->activeMembers->pluck('student_profile_id'))
            ->get(['student_profile_id', 'subject_id'])
            ->groupBy('subject_id')->map(fn ($rows) => $rows->pluck('student_profile_id')->flip());

        return response()->json(['success' => true, 'data' => $this->summary($exam) + [
            'mode'   => ExamResults::mode($exam),
            'groups' => $groups->map(fn ($g) => [
                'id'                => $g->id,
                'display_name'      => $g->display_name,
                'students_count'    => $g->activeMembers->count(),
                'can_write_remarks' => Gate::allows('take-group-attendance', $g),
                'subjects'          => ExamResults::groupSubjects($g)->map(function ($subject) use ($g, $entered, $exam) {
                    $takers = $g->activeMembers->filter(fn ($m) => ! $subject->is_optional || (int) $m->optional_subject_id === (int) $subject->id);
                    [$full] = $exam->marksFor($subject);

                    return [
                        'id'         => $subject->id,
                        'name'       => $subject->name,
                        'code'       => $subject->code,
                        'full_marks' => $full,
                        'teacher'    => $g->groupSubjects->firstWhere('subject_id', $subject->id)?->teacher?->user?->name,
                        'expected'   => $takers->count(),
                        'entered'    => $takers->filter(fn ($m) => isset($entered[$subject->id][$m->student_profile_id]))->count(),
                        'can_enter'  => Gate::allows('enter-exam-marks', [$g, $subject->id]),
                    ];
                })->values(),
            ]),
        ]]);
    }

    // GET /api/term-exams/{id}/groups/{groupId}/marks?subject_id=
    public function marksheet(Request $request, $id, $groupId)
    {
        [$exam, $group, $subject] = $this->marksContext($request, $id, $groupId);
        [$full, $pass] = $exam->marksFor($subject);
        $marks = ExamMark::where('term_exam_id', $exam->id)->where('subject_id', $subject->id)->get()->keyBy('student_profile_id');

        return response()->json(['success' => true, 'data' => [
            'subject'    => ['id' => $subject->id, 'name' => $subject->name, 'full_marks' => $full, 'pass_marks' => $pass],
            'group'      => ['id' => $group->id, 'display_name' => $group->display_name],
            'locked'     => $exam->isPublished(),
            'students'   => $this->takers($group, $subject)->map(fn ($m) => [
                'student_profile_id' => $m->student_profile_id,
                'roll_number'        => $m->roll_number,
                'name'               => $m->student?->user?->name,
                'student_id'         => $m->student?->student_id,
                'marks'              => $marks[$m->student_profile_id]->marks ?? null,
                'is_absent'          => (bool) ($marks[$m->student_profile_id]->is_absent ?? false),
            ])->values(),
        ]]);
    }

    // PUT /api/term-exams/{id}/groups/{groupId}/marks
    public function saveMarks(Request $request, $id, $groupId)
    {
        [$exam, $group, $subject] = $this->marksContext($request, $id, $groupId);
        $this->ensureDraft($exam);
        [$full] = $exam->marksFor($subject);

        $data = $request->validate([
            'records'                      => 'required|array|min:1',
            'records.*.student_profile_id' => 'required|integer|distinct',
            'records.*.marks'              => "nullable|numeric|min:0|max:{$full}|decimal:0,2",
            'records.*.is_absent'          => 'boolean',
        ], ['records.*.marks.max' => "Marks cannot be more than {$full}."]);

        $takers   = $this->takers($group, $subject)->pluck('student_profile_id');
        $outsiders = collect($data['records'])->pluck('student_profile_id')->diff($takers);
        if ($outsiders->isNotEmpty()) {
            throw ValidationException::withMessages(['records' => "Some students do not take {$subject->name} in this {$group->kind}."]);
        }

        DB::transaction(function () use ($data, $exam, $subject, $request) {
            foreach ($data['records'] as $row) {
                $key    = ['term_exam_id' => $exam->id, 'student_profile_id' => $row['student_profile_id'], 'subject_id' => $subject->id];
                $absent = (bool) ($row['is_absent'] ?? false);

                // A blank, not-absent row clears the entry
                if (! $absent && ($row['marks'] ?? null) === null) {
                    ExamMark::where($key)->delete();
                    continue;
                }
                ExamMark::updateOrCreate($key, ['marks' => $absent ? null : $row['marks'], 'is_absent' => $absent, 'entered_by' => $request->user()->id]);
            }
        });

        self::logVisit('exams', 'marks', 'updated', "{$subject->name} marks for {$group->display_name} in {$exam->name}", [], ['count' => count($data['records'])], TermExam::class, $exam->id);

        return response()->json(['success' => true, 'message' => 'Marks saved.']);
    }

    // GET /api/term-exams/{id}/groups/{groupId}/results
    public function results($id, $groupId)
    {
        [$exam, $group] = $this->groupContext($id, $groupId);
        $sheet = ExamResults::build($exam, $exam->groups())[$group->id];

        return response()->json(['success' => true, 'data' => [
            'exam'              => $this->summary($exam),
            'mode'              => ExamResults::mode($exam),
            'group'             => ['id' => $group->id, 'display_name' => $group->display_name],
            'can_write_remarks' => Gate::allows('take-group-attendance', $group),
            'subjects'          => $sheet['subjects']->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'is_optional' => (bool) $s->is_optional])->values(),
            'students'          => $sheet['students'],
        ]]);
    }

    // PUT /api/term-exams/{id}/groups/{groupId}/remarks
    public function saveRemarks(Request $request, $id, $groupId)
    {
        [$exam, $group] = $this->groupContext($id, $groupId);
        Gate::authorize('take-group-attendance', $group); // class teacher's job
        $this->ensureDraft($exam);

        $data = $request->validate([
            'records'                      => 'required|array|min:1',
            'records.*.student_profile_id' => 'required|integer|distinct',
            'records.*.remarks'            => 'nullable|string|max:500',
        ]);

        $members = $group->activeMembers()->pluck('student_profile_id');
        if (collect($data['records'])->pluck('student_profile_id')->diff($members)->isNotEmpty()) {
            throw ValidationException::withMessages(['records' => "Some students are not in this {$group->kind}."]);
        }

        DB::transaction(function () use ($data, $exam, $request) {
            foreach ($data['records'] as $row) {
                $key  = ['term_exam_id' => $exam->id, 'student_profile_id' => $row['student_profile_id']];
                $text = trim((string) ($row['remarks'] ?? ''));
                $text === ''
                    ? ExamRemark::where($key)->delete()
                    : ExamRemark::updateOrCreate($key, ['remarks' => $text, 'entered_by' => $request->user()->id]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Remarks saved.']);
    }

    // POST /api/term-exams/{id}/publish
    public function publish($id)
    {
        $exam = TermExam::with('classLevel')->findOrFail($id);
        $this->ensureDraft($exam);

        $incomplete = collect(ExamResults::build($exam, $exam->groups()))->flatMap(fn ($s) => $s['students'])->where('status', 'incomplete')->count();
        if ($incomplete) {
            throw ValidationException::withMessages(['exam' => "{$incomplete} " . Str::plural('student', $incomplete) . ' still have missing marks. Enter them (or mark absent) before publishing.']);
        }

        $exam->update(['status' => 'published', 'published_at' => now()]);
        self::logVisit('exams', 'results', 'updated', "Published results of {$exam->name}", ['status' => 'draft'], ['status' => 'published'], TermExam::class, $exam->id);

        return response()->json(['success' => true, 'message' => 'Results published.', 'data' => $this->summary($exam)]);
    }

    // POST /api/term-exams/{id}/unpublish
    public function unpublish($id)
    {
        $exam = TermExam::findOrFail($id);
        abort_unless($exam->isPublished(), 422, 'This exam is not published.');

        $exam->update(['status' => 'draft', 'published_at' => null]);
        self::logVisit('exams', 'results', 'updated', "Unpublished results of {$exam->name}", ['status' => 'published'], ['status' => 'draft'], TermExam::class, $exam->id);

        return response()->json(['success' => true, 'message' => 'Results unpublished — marks can be edited again.', 'data' => $this->summary($exam)]);
    }

    // GET /api/term-exams/{id}/groups/{groupId}/report-cards[?student_profile_id=][&inline=1]
    public function reportCards(Request $request, $id, $groupId)
    {
        [$exam, $group] = $this->groupContext($id, $groupId);
        $studentId = $request->validate(['student_profile_id' => 'nullable|integer'])['student_profile_id'] ?? null;

        $students = collect(ExamResults::build($exam, $exam->groups())[$group->id]['students']);
        if ($studentId) {
            $students = $students->where('student_profile_id', (int) $studentId)->values();
            abort_if($students->isEmpty(), 404, "That student is not in this {$group->kind}.");
        }
        abort_if($students->isEmpty(), 422, "This {$group->kind} has no students.");

        $pdf = Pdf::loadView('exams.report-card', [
            'institution'  => config('app.name'),
            'exam'         => $exam,
            'group'        => $group,
            'mode'         => ExamResults::mode($exam),
            'students'     => $students,
            'sectionSize'  => $group->activeMembers()->count(),
            'generated_at' => now()->format('d M Y'),
        ])->setPaper('a4', 'portrait');

        $name = Str::slug($studentId ? "{$exam->name} {$students[0]['name']}" : "{$exam->name} {$group->display_name}") . '.pdf';

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    private function rules(): array
    {
        return [
            'name'       => 'required|string|max:120',
            'kind'       => ['required', Rule::in(TermExam::KINDS)],
            'full_marks' => 'nullable|integer|min:1|max:1000',
            'starts_on'  => 'nullable|date',
            'ends_on'    => 'nullable|date|after_or_equal:starts_on',
        ];
    }

    private function summary(TermExam $exam): array
    {
        return [
            'id'               => $exam->id,
            'name'             => $exam->name,
            'kind'             => $exam->kind,
            'academic_year'    => $exam->academic_year,
            'class_level_id'   => $exam->class_level_id,
            'student_group_id' => $exam->student_group_id,
            'sits'             => $exam->batch?->display_name ?? $exam->classLevel?->name,
            'full_marks'       => $exam->full_marks,
            'starts_on'        => $exam->starts_on?->toDateString(),
            'ends_on'          => $exam->ends_on?->toDateString(),
            'status'           => $exam->status,
            'published_at'     => $exam->published_at?->toIso8601String(),
        ];
    }

    private function ensureDraft(TermExam $exam): void
    {
        if ($exam->isPublished()) {
            throw ValidationException::withMessages(['exam' => 'Results are published. Unpublish them first to make changes.']);
        }
    }

    /** @return array{TermExam, StudentGroup} after checking the user may see this group's results */
    private function groupContext($id, $groupId): array
    {
        $exam  = TermExam::with(['classLevel', 'batch'])->findOrFail($id);
        $group = StudentGroup::with('classLevel')->findOrFail($groupId);
        abort_unless($exam->covers($group), 404, "This exam is not for that {$group->kind}.");
        Gate::authorize('view-group', $group);

        return [$exam, $group];
    }

    /** @return array{TermExam, StudentGroup, Subject} after checking the user teaches this subject here */
    private function marksContext(Request $request, $id, $groupId): array
    {
        $exam  = TermExam::with(['classLevel', 'batch'])->findOrFail($id);
        $group = StudentGroup::with('classLevel')->findOrFail($groupId);
        abort_unless($exam->covers($group), 404, "This exam is not for that {$group->kind}.");

        $subjectId = (int) $request->validate(['subject_id' => 'required|integer'])['subject_id'];
        abort_unless($group->groupSubjects()->where('subject_id', $subjectId)->exists(), 404, "That subject is not taught in this {$group->kind}.");
        Gate::authorize('enter-exam-marks', [$group, $subjectId]);

        return [$exam, $group, Subject::findOrFail($subjectId)];
    }

    /** Current students who sit this subject (an optional subject only for those who chose it). */
    private function takers(StudentGroup $group, Subject $subject)
    {
        return GroupMember::with('student.user')
            ->where('student_group_id', $group->id)
            ->where('status', 'active')
            ->when($subject->is_optional, fn ($q) => $q->where('optional_subject_id', $subject->id))
            ->orderBy('roll_number')
            ->get();
    }
}
