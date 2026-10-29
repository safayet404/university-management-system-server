<?php

namespace App\Support;

use App\Models\ExamMark;
use App\Models\ExamRemark;
use App\Models\StudentGroup;
use App\Models\TermExam;
use Illuminate\Support\Collection;

/**
 * Builds the result sheet of a term exam: each student's subjects, total,
 * GPA and merit position in the section and in the whole class.
 *
 * Modes: gpa5 (Class 1–12), remarks (pre-primary: marks and teacher's
 * remarks, no GPA or position), marks (coaching: merit by total marks).
 */
class ExamResults
{
    public static function mode(TermExam $exam): string
    {
        if ($exam->student_group_id) {
            return 'marks';
        }

        return $exam->classLevel?->grading === 'remarks' ? 'remarks' : 'gpa5';
    }

    /** Subjects a member sits: the group's compulsory subjects plus their own optional (4th) subject. */
    public static function subjectsFor(Collection $subjects, $member): Collection
    {
        return $subjects->filter(fn ($s) => ! $s->is_optional || (int) $s->id === (int) $member->optional_subject_id)->values();
    }

    /** The group's subjects in display order. */
    public static function groupSubjects(StudentGroup $group): Collection
    {
        return $group->groupSubjects->pluck('subject')->filter()->sortBy('sort_order')->values();
    }

    /**
     * @return array<int, array{group: StudentGroup, subjects: Collection, students: array}> keyed by group id
     */
    public static function build(TermExam $exam, Collection $groups): array
    {
        $mode = self::mode($exam);
        $groups->load(['groupSubjects.subject', 'activeMembers.student.user']);

        $studentIds = $groups->flatMap->activeMembers->pluck('student_profile_id');
        $marks = ExamMark::where('term_exam_id', $exam->id)->whereIn('student_profile_id', $studentIds)->get()
            ->groupBy('student_profile_id')->map->keyBy('subject_id');
        $remarks = ExamRemark::where('term_exam_id', $exam->id)->whereIn('student_profile_id', $studentIds)->pluck('remarks', 'student_profile_id');

        $sheets = [];
        foreach ($groups as $group) {
            $subjects = self::groupSubjects($group);
            $students = [];
            foreach ($group->activeMembers->sortBy('roll_number') as $member) {
                $taken = self::subjectsFor($subjects, $member)->map(function ($subject) use ($exam, $marks, $member) {
                    [$full, $pass] = $exam->marksFor($subject);
                    $mark = $marks[$member->student_profile_id][$subject->id] ?? null;

                    return [
                        'subject_id' => $subject->id,
                        'name'       => $subject->name,
                        'full_marks' => $full,
                        'pass_marks' => $pass,
                        'marks'      => $mark?->is_absent ? null : $mark?->marks,
                        'is_absent'  => (bool) $mark?->is_absent,
                        'is_fourth'  => (bool) $subject->is_optional,
                    ];
                })->all();

                $result = Gpa5::result($taken);
                $students[$member->student_profile_id] = [
                    'student_profile_id' => $member->student_profile_id,
                    'roll_number'        => $member->roll_number,
                    'name'               => $member->student?->user?->name,
                    'student_id'         => $member->student?->student_id,
                    'subjects'           => array_map(fn ($r) => array_diff_key($r, ['grade' => 0]) + [
                        'letter' => $r['grade']['letter'] ?? null,
                        'point'  => $r['grade']['point'] ?? null,
                    ], $result['subjects']),
                    'total'              => $result['total'],
                    'full_total'         => $result['full_total'],
                    'percent'            => $result['full_total'] ? round($result['total'] / $result['full_total'] * 100, 2) : null,
                    'status'             => $result['status'],
                    'gpa'                => $mode === 'gpa5' ? $result['gpa'] : null,
                    'letter'             => $mode === 'gpa5' ? $result['letter'] : null,
                    'missing'            => $result['missing'],
                    'section_position'   => null,
                    'class_position'     => null,
                    'remarks'            => $remarks[$member->student_profile_id] ?? null,
                ];
            }

            if ($mode !== 'remarks') {
                foreach (Gpa5::merit($students, byTotal: $mode === 'marks') as $id => $position) {
                    $students[$id]['section_position'] = $position;
                }
            }
            $sheets[$group->id] = ['group' => $group, 'subjects' => $subjects, 'students' => $students];
        }

        // Class position: every section of the class (same stream) ranked together
        if ($mode === 'gpa5') {
            foreach (collect($sheets)->groupBy(fn ($s) => $s['group']->stream ?? '') as $streamSheets) {
                $all = $streamSheets->flatMap(fn ($s) => collect($s['students'])->mapWithKeys(fn ($row, $id) => ["{$s['group']->id}:{$id}" => $row]))->all();
                foreach (Gpa5::merit($all) as $key => $position) {
                    [$groupId, $id] = explode(':', $key);
                    $sheets[$groupId]['students'][$id]['class_position'] = $position;
                }
            }
        }

        foreach ($sheets as &$sheet) {
            $sheet['students'] = array_values($sheet['students']);
        }

        return $sheets;
    }
}
