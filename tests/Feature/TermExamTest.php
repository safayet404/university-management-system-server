<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\FacultyProfile;
use App\Models\GroupMember;
use App\Models\GroupSubject;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TermExam;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\SchoolStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TermExamTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $classTeacher;
    private User $mathTeacher;
    private array $subjects;   // bangla, math, agri (optional)
    private StudentGroup $sectionA;
    private StudentGroup $sectionB;
    private array $a = [];     // student ids in section A, by roll
    private array $b = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.institution_type' => 'school']);
        $this->seed(RolesPermissionsSeeder::class);
        $this->seed(SchoolStructureSeeder::class);

        $this->admin        = User::factory()->create()->assignRole('admin');
        $this->classTeacher = $this->teacher();
        $this->mathTeacher  = $this->teacher();

        $level = ClassLevel::where('code', 'C6')->value('id');
        foreach (['Bangla' => false, 'Math' => false, 'Agri' => true] as $name => $optional) {
            $this->subjects[strtolower($name)] = Subject::create([
                'name' => $name, 'code' => 'T-' . $name, 'class_level_id' => $level, 'full_marks' => 100, 'pass_marks' => 33, 'is_optional' => $optional,
            ]);
        }

        $this->sectionA = $this->section('A', $this->classTeacher, $this->a, 3);
        $this->sectionB = $this->section('B', $this->teacher(), $this->b, 2);
    }

    private function teacher(): User
    {
        $user = User::factory()->create()->assignRole('faculty');
        FacultyProfile::create(['user_id' => $user->id, 'employee_id' => 'T-' . $user->id, 'designation' => 'Teacher']);

        return $user;
    }

    private function section(string $name, User $classTeacher, array &$ids, int $size): StudentGroup
    {
        $group = StudentGroup::create([
            'kind' => 'section', 'name' => $name, 'class_level_id' => ClassLevel::where('code', 'C6')->value('id'),
            'academic_year' => '2026', 'class_teacher_id' => $classTeacher->facultyProfile->id,
        ]);
        foreach ($this->subjects as $key => $subject) {
            GroupSubject::create([
                'student_group_id' => $group->id, 'subject_id' => $subject->id,
                'faculty_profile_id' => $key === 'math' ? $this->mathTeacher->facultyProfile->id : null,
            ]);
        }
        foreach (range(1, $size) as $roll) {
            $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_id' => "{$name}-{$roll}"]);
            GroupMember::create([
                'student_group_id' => $group->id, 'student_profile_id' => $student->id, 'roll_number' => $roll, 'status' => 'active',
                'optional_subject_id' => $roll === 1 ? $this->subjects['agri']->id : null,
            ]);
            $ids[$roll] = $student->id;
        }

        return $group;
    }

    private function exam(): TermExam
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/term-exams', [
            'name' => 'Half Yearly 2026', 'kind' => 'half_yearly', 'academic_year' => '2026',
            'class_level_id' => ClassLevel::where('code', 'C6')->value('id'),
        ])->assertCreated()->json('data.id');

        return TermExam::find($id);
    }

    private function marks(TermExam $exam, StudentGroup $group, string $subject, array $rows)
    {
        return $this->putJson("/api/term-exams/{$exam->id}/groups/{$group->id}/marks", [
            'subject_id' => $this->subjects[$subject]->id,
            'records'    => collect($rows)->map(fn ($marks, $id) => is_string($marks)
                ? ['student_profile_id' => $id, 'is_absent' => true]
                : ['student_profile_id' => $id, 'marks' => $marks])->values()->all(),
        ]);
    }

    /** Fill every subject for every student so results are complete. */
    private function fillAll(TermExam $exam): void
    {
        Sanctum::actingAs($this->admin);
        // Section A: roll 1 = 90/85 + agri 95 (4th), roll 2 = 75/72, roll 3 = 95/20 (fails math)
        $this->marks($exam, $this->sectionA, 'bangla', [$this->a[1] => 90, $this->a[2] => 75, $this->a[3] => 95])->assertOk();
        $this->marks($exam, $this->sectionA, 'math',   [$this->a[1] => 85, $this->a[2] => 72, $this->a[3] => 20])->assertOk();
        $this->marks($exam, $this->sectionA, 'agri',   [$this->a[1] => 95])->assertOk();
        // Section B: roll 1 = 99/99 + agri absent, roll 2 = 60/65
        $this->marks($exam, $this->sectionB, 'bangla', [$this->b[1] => 99, $this->b[2] => 60])->assertOk();
        $this->marks($exam, $this->sectionB, 'math',   [$this->b[1] => 99, $this->b[2] => 65])->assertOk();
        $this->marks($exam, $this->sectionB, 'agri',   [$this->b[1] => 'absent'])->assertOk();
    }

    public function test_a_school_exam_needs_a_class_and_the_show_page_lists_its_sections(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/term-exams', ['name' => 'X', 'kind' => 'annual', 'academic_year' => '2026'])->assertJsonValidationErrors('class_level_id');
        $this->postJson('/api/term-exams', ['name' => 'X', 'kind' => 'annual', 'academic_year' => '2026', 'class_level_id' => 1, 'student_group_id' => $this->sectionA->id])
            ->assertJsonValidationErrors('student_group_id');

        $exam = $this->exam();
        $groups = $this->getJson("/api/term-exams/{$exam->id}")->assertOk()->json('data.groups');

        $this->assertSame([$this->sectionA->id, $this->sectionB->id], array_column($groups, 'id'));
        $agri = collect($groups[0]['subjects'])->firstWhere('name', 'Agri');
        $this->assertSame(1, $agri['expected']); // only roll 1 chose it
    }

    public function test_only_the_subject_teacher_enters_marks_for_their_subject(): void
    {
        $exam = $this->exam();

        Sanctum::actingAs($this->mathTeacher);
        $this->marks($exam, $this->sectionA, 'math', [$this->a[1] => 80])->assertOk();
        $this->marks($exam, $this->sectionA, 'bangla', [$this->a[1] => 80])->assertForbidden();

        // The class teacher does not teach math
        Sanctum::actingAs($this->classTeacher);
        $this->marks($exam, $this->sectionA, 'math', [$this->a[1] => 10])->assertForbidden();
        $this->assertDatabaseHas('exam_marks', ['student_profile_id' => $this->a[1], 'marks' => 80]);

        // Teachers only see sections they are linked to
        Sanctum::actingAs($this->classTeacher);
        $this->assertSame([$this->sectionA->id], array_column($this->getJson("/api/term-exams/{$exam->id}")->json('data.groups'), 'id'));
    }

    public function test_marks_are_validated_against_full_marks_and_the_subject_takers(): void
    {
        $exam = $this->exam();

        $this->marks($exam, $this->sectionA, 'math', [$this->a[1] => 101])->assertJsonValidationErrors('records.0.marks');
        $this->marks($exam, $this->sectionA, 'math', [$this->b[1] => 50])->assertJsonValidationErrors('records');
        // The optional subject only for students who chose it
        $this->marks($exam, $this->sectionA, 'agri', [$this->a[2] => 50])->assertJsonValidationErrors('records');

        $sheet = $this->getJson("/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/marks?subject_id={$this->subjects['agri']->id}")->assertOk()->json('data');
        $this->assertSame([$this->a[1]], array_column($sheet['students'], 'student_profile_id'));
    }

    public function test_saving_a_blank_row_clears_the_mark(): void
    {
        $exam = $this->exam();
        $this->marks($exam, $this->sectionA, 'math', [$this->a[1] => 50])->assertOk();

        $this->putJson("/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/marks", [
            'subject_id' => $this->subjects['math']->id, 'records' => [['student_profile_id' => $this->a[1], 'marks' => null]],
        ])->assertOk();

        $this->assertDatabaseCount('exam_marks', 0);
    }

    public function test_results_compute_gpa_with_the_fourth_subject_and_positions_in_section_and_class(): void
    {
        $exam = $this->exam();
        $this->fillAll($exam);

        $a = collect($this->getJson("/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/results")->assertOk()->json('data.students'))->keyBy('roll_number');
        $b = collect($this->getJson("/api/term-exams/{$exam->id}/groups/{$this->sectionB->id}/results")->assertOk()->json('data.students'))->keyBy('roll_number');

        // A1: (5 + 5 + (5 − 2)) / 2 → capped 5.00
        $this->assertEquals([5.0, 'A+', 'passed'], [$a[1]['gpa'], $a[1]['letter'], $a[1]['status']]);
        $this->assertEquals(270, $a[1]['total']);
        // A2: (4 + 4) / 2
        $this->assertEquals(4.0, $a[2]['gpa']);
        // A3 failed math → GPA 0 though the total is higher than A2
        $this->assertEquals([0.0, 'failed'], [$a[3]['gpa'], $a[3]['status']]);
        // B1: absent in the 4th subject does not fail; B2: (3.5 + 3.5) / 2
        $this->assertEquals([5.0, 'passed'], [$b[1]['gpa'], $b[1]['status']]);
        $this->assertEquals(3.5, $b[2]['gpa']);

        $this->assertSame([1, 2, 3], [$a[1]['section_position'], $a[2]['section_position'], $a[3]['section_position']]);
        // Whole class: A1 (5.00, 270) beats B1 (5.00, 198), then A2, B2, then the failed A3
        $this->assertSame([1, 3, 5], [$a[1]['class_position'], $a[2]['class_position'], $a[3]['class_position']]);
        $this->assertSame([2, 4], [$b[1]['class_position'], $b[2]['class_position']]);
    }

    public function test_publishing_needs_complete_marks_and_locks_them_until_unpublished(): void
    {
        $exam = $this->exam();
        $this->marks($exam, $this->sectionA, 'math', [$this->a[1] => 50])->assertOk();
        $this->postJson("/api/term-exams/{$exam->id}/publish")->assertJsonValidationErrors('exam');

        $this->fillAll($exam);
        $this->postJson("/api/term-exams/{$exam->id}/publish")->assertOk()->assertJsonPath('data.status', 'published');

        $this->marks($exam, $this->sectionA, 'math', [$this->a[1] => 10])->assertJsonValidationErrors('exam');
        $this->putJson("/api/term-exams/{$exam->id}", ['name' => 'Renamed', 'kind' => 'annual'])->assertJsonValidationErrors('exam');
        $this->deleteJson("/api/term-exams/{$exam->id}")->assertJsonValidationErrors('exam');

        // Teachers cannot publish or unpublish
        Sanctum::actingAs($this->classTeacher);
        $this->postJson("/api/term-exams/{$exam->id}/unpublish")->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/term-exams/{$exam->id}/unpublish")->assertOk()->assertJsonPath('data.status', 'draft');
        $this->marks($exam, $this->sectionA, 'math', [$this->a[1] => 10])->assertOk();
    }

    public function test_only_the_class_teacher_writes_remarks(): void
    {
        $exam = $this->exam();
        $url  = "/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/remarks";
        $body = ['records' => [['student_profile_id' => $this->a[1], 'remarks' => 'Excellent, keep it up.']]];

        Sanctum::actingAs($this->mathTeacher);
        $this->putJson($url, $body)->assertForbidden();

        Sanctum::actingAs($this->classTeacher);
        $this->putJson($url, $body)->assertOk();
        $this->putJson($url, ['records' => [['student_profile_id' => $this->b[1], 'remarks' => 'x']]])->assertJsonValidationErrors('records');

        $row = collect($this->getJson("/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/results")->json('data.students'))->firstWhere('roll_number', 1);
        $this->assertSame('Excellent, keep it up.', $row['remarks']);
    }

    public function test_results_are_hidden_from_unrelated_teachers_and_other_classes(): void
    {
        $exam = $this->exam();

        Sanctum::actingAs($this->teacher());
        $this->getJson("/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/results")->assertForbidden();
        $this->getJson("/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/report-cards")->assertForbidden();

        $other = StudentGroup::create(['kind' => 'section', 'name' => 'A', 'class_level_id' => ClassLevel::where('code', 'C7')->value('id'), 'academic_year' => '2026']);
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/term-exams/{$exam->id}/groups/{$other->id}/results")->assertNotFound();

        // Students cannot read the class's results
        Sanctum::actingAs(User::factory()->create()->assignRole('student'));
        $this->getJson("/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/results")->assertForbidden();
    }

    public function test_report_cards_download_as_pdf_for_one_student_or_the_whole_section(): void
    {
        $exam = $this->exam();
        $this->fillAll($exam);
        $base = "/api/term-exams/{$exam->id}/groups/{$this->sectionA->id}/report-cards";

        $bulk = $this->get($base)->assertOk();
        $this->assertSame('application/pdf', $bulk->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $bulk->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $bulk->getContent());

        // Inline for printing
        $single = $this->get("{$base}?student_profile_id={$this->a[1]}&inline=1")->assertOk();
        $this->assertStringContainsString('inline', $single->headers->get('Content-Disposition'));

        $this->getJson("{$base}?student_profile_id={$this->b[1]}")->assertNotFound();

        // The class teacher can print their own section's cards
        Sanctum::actingAs($this->classTeacher);
        $this->get($base)->assertOk();
    }

    public function test_coaching_model_tests_belong_to_a_batch_and_rank_by_total_marks(): void
    {
        config(['app.institution_type' => 'coaching']);
        $teacher = $this->teacher();
        $batch = StudentGroup::create(['kind' => 'batch', 'name' => 'HSC Physics – Evening', 'academic_year' => '2026']);
        $physics = Subject::create(['name' => 'Physics', 'code' => 'B-PHY', 'full_marks' => 100, 'pass_marks' => 40]);
        GroupSubject::create(['student_group_id' => $batch->id, 'subject_id' => $physics->id, 'faculty_profile_id' => $teacher->facultyProfile->id]);
        $ids = [];
        foreach ([1, 2, 3] as $roll) {
            $s = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_id' => "P-{$roll}"]);
            GroupMember::create(['student_group_id' => $batch->id, 'student_profile_id' => $s->id, 'roll_number' => $roll, 'status' => 'active']);
            $ids[$roll] = $s->id;
        }

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/term-exams', ['name' => 'Model Test 1', 'kind' => 'model_test', 'class_level_id' => 1])->assertJsonValidationErrors(['class_level_id', 'student_group_id']);
        $examId = $this->postJson('/api/term-exams', ['name' => 'Model Test 1', 'kind' => 'model_test', 'student_group_id' => $batch->id, 'full_marks' => 50])
            ->assertCreated()->assertJsonPath('data.academic_year', '2026')->json('data.id');

        // Batch teachers enter marks out of the test's full marks
        Sanctum::actingAs($teacher);
        $this->putJson("/api/term-exams/{$examId}/groups/{$batch->id}/marks", ['subject_id' => $physics->id, 'records' => [
            ['student_profile_id' => $ids[1], 'marks' => 30], ['student_profile_id' => $ids[2], 'marks' => 45], ['student_profile_id' => $ids[3], 'marks' => 51],
        ]])->assertJsonValidationErrors('records.2.marks');
        $this->putJson("/api/term-exams/{$examId}/groups/{$batch->id}/marks", ['subject_id' => $physics->id, 'records' => [
            ['student_profile_id' => $ids[1], 'marks' => 30], ['student_profile_id' => $ids[2], 'marks' => 45], ['student_profile_id' => $ids[3], 'marks' => 12],
        ]])->assertOk();

        $data = $this->getJson("/api/term-exams/{$examId}/groups/{$batch->id}/results")->assertOk()->json('data');
        $rows = collect($data['students'])->keyBy('roll_number');

        $this->assertSame('marks', $data['mode']);
        $this->assertNull($rows[1]['gpa']);
        $this->assertSame([2, 1, 3], [$rows[1]['section_position'], $rows[2]['section_position'], $rows[3]['section_position']]);
        $this->assertSame('failed', $rows[3]['status']); // pass marks scale to 20 of 50
        $this->assertEquals(90.0, $rows[2]['percent']);
    }
}
