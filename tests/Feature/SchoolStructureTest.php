<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\GroupMember;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\SchoolStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.institution_type' => 'school']);
        $this->seed(RolesPermissionsSeeder::class);
        $this->seed(SchoolStructureSeeder::class);
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
    }

    private function level(string $code): ClassLevel
    {
        return ClassLevel::where('code', $code)->first();
    }

    private function section(string $code = 'C6', array $extra = [])
    {
        return $this->postJson('/api/student-groups', array_merge([
            'kind' => 'section', 'name' => 'A', 'class_level_id' => $this->level($code)->id, 'academic_year' => '2026', 'shift' => 'morning',
        ], $extra));
    }

    private function students(int $n): array
    {
        return collect(range(1, $n))->map(fn () => StudentProfile::create([
            'user_id' => User::factory()->create()->id, 'student_id' => 'S-' . uniqid(),
        ])->id)->all();
    }

    public function test_app_config_reports_the_institution_type(): void
    {
        $this->getJson('/api/app-config')->assertOk()->assertJsonPath('data.institution_type', 'school');
    }

    public function test_class_levels_cover_play_to_class_twelve(): void
    {
        $levels = $this->getJson('/api/class-levels')->assertOk()->json('data');

        $this->assertCount(15, $levels);
        $this->assertSame(['Play', 'Class 12'], [$levels[0]['name'], $levels[14]['name']]);
        $this->assertSame('remarks', $levels[0]['grading']);
        $this->assertSame('gpa5', $levels[3]['grading']);
    }

    public function test_every_class_has_the_core_subjects_with_distinct_codes(): void
    {
        foreach (ClassLevel::all() as $level) {
            $names = Subject::where('class_level_id', $level->id)->pluck('name');
            foreach (['Bangla', 'English'] as $core) {
                $this->assertContains($core, $names, "{$level->name} is missing {$core}");
            }
        }
        $this->assertSame(Subject::count(), Subject::distinct()->count('code'));
    }

    public function test_school_students_need_no_department_but_university_students_do(): void
    {
        $payload = ['name' => 'Ayesha', 'email' => 'ayesha@example.com', 'student_id' => 'SCH-001'];
        $this->postJson('/api/students', $payload)->assertCreated();

        config(['app.institution_type' => 'university']);
        $this->postJson('/api/students', ['email' => 'other@example.com', 'student_id' => 'UNI-001'] + $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id', 'program_id']);
    }

    public function test_a_new_section_gets_the_subjects_of_its_class_and_stream(): void
    {
        $id = $this->section('C9', ['stream' => 'science'])->assertCreated()->json('data.id');

        $subjects = collect($this->getJson("/api/student-groups/{$id}")->json('data.subjects'))->pluck('name');
        $this->assertContains('Physics', $subjects);
        $this->assertContains('Bangla', $subjects);
        $this->assertContains('Higher Mathematics', $subjects);
        $this->assertNotContains('History', $subjects);
        $this->assertNotContains('Accounting', $subjects);
    }

    public function test_the_same_section_cannot_be_created_twice(): void
    {
        $this->section()->assertCreated();
        $this->section()->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->section('C6', ['shift' => 'day'])->assertCreated();
    }

    public function test_students_get_consecutive_roll_numbers_and_capacity_is_respected(): void
    {
        $id = $this->section('C6', ['capacity' => 3])->json('data.id');
        [$a, $b, $c, $d] = $this->students(4);

        $this->postJson("/api/student-groups/{$id}/members", ['student_profile_ids' => [$a, $b]])->assertOk();
        $this->postJson("/api/student-groups/{$id}/members", ['student_profile_ids' => [$c, $d]])
            ->assertUnprocessable()->assertJsonValidationErrors('student_profile_ids');
        $this->postJson("/api/student-groups/{$id}/members", ['student_profile_ids' => [$c]])->assertOk();

        $this->assertSame([1, 2, 3], GroupMember::where('student_group_id', $id)->orderBy('roll_number')->pluck('roll_number')->all());
    }

    public function test_a_student_belongs_to_one_section_per_year_but_many_batches(): void
    {
        [$student] = $this->students(1);
        $sectionA = $this->section()->json('data.id');
        $sectionB = $this->section('C6', ['name' => 'B'])->json('data.id');

        $this->postJson("/api/student-groups/{$sectionA}/members", ['student_profile_ids' => [$student]])->assertOk();
        $this->postJson("/api/student-groups/{$sectionB}/members", ['student_profile_ids' => [$student]])->assertUnprocessable();

        $batch = fn ($name) => $this->postJson('/api/student-groups', ['kind' => 'batch', 'name' => $name, 'academic_year' => '2026', 'monthly_fee' => 2500])->json('data.id');
        $this->postJson('/api/student-groups/' . $batch('Physics – Evening') . '/members', ['student_profile_ids' => [$student]])->assertOk();
        $this->postJson('/api/student-groups/' . $batch('Chemistry – Evening') . '/members', ['student_profile_ids' => [$student]])->assertOk();
    }

    public function test_roll_numbers_are_unique_and_the_fourth_subject_must_be_an_optional_one_of_the_class(): void
    {
        $id = $this->section('C9', ['stream' => 'science'])->json('data.id');
        $this->postJson("/api/student-groups/{$id}/members", ['student_profile_ids' => $this->students(2)]);
        [$first, $second] = GroupMember::where('student_group_id', $id)->orderBy('roll_number')->get();

        $this->patchJson("/api/student-groups/{$id}/members/{$second->id}", ['roll_number' => 1])->assertUnprocessable();
        $this->patchJson("/api/student-groups/{$id}/members/{$second->id}", ['roll_number' => 7])->assertOk();

        $subject = fn ($name) => Subject::where('class_level_id', $this->level('C9')->id)->where('name', $name)->value('id');
        $this->patchJson("/api/student-groups/{$id}/members/{$first->id}", ['optional_subject_id' => $subject('History')])->assertUnprocessable();
        $this->patchJson("/api/student-groups/{$id}/members/{$first->id}", ['optional_subject_id' => $subject('Higher Mathematics')])->assertOk();
        $this->patchJson("/api/student-groups/{$id}/members/{$first->id}", ['optional_subject_id' => $subject('Agriculture Studies')])->assertOk();
    }

    public function test_removing_a_student_frees_the_roll_and_re_adding_restores_the_record(): void
    {
        $id = $this->section()->json('data.id');
        [$student] = $this->students(1);
        $this->postJson("/api/student-groups/{$id}/members", ['student_profile_ids' => [$student]]);
        $member = GroupMember::first();

        $this->deleteJson("/api/student-groups/{$id}/members/{$member->id}")->assertOk();
        $this->assertSame('left', $member->fresh()->status);
        $this->assertNull($member->fresh()->roll_number);

        $this->deleteJson("/api/student-groups/{$id}")->assertOk();
    }

    public function test_a_section_with_students_cannot_be_deleted(): void
    {
        $id = $this->section()->json('data.id');
        $this->postJson("/api/student-groups/{$id}/members", ['student_profile_ids' => $this->students(1)]);

        $this->deleteJson("/api/student-groups/{$id}")->assertUnprocessable();
    }

    public function test_sections_only_teach_subjects_of_their_class(): void
    {
        $id = $this->section()->json('data.id');
        $classNine = Subject::where('class_level_id', $this->level('C9')->id)->value('id');

        $this->putJson("/api/student-groups/{$id}/subjects", ['subjects' => [['subject_id' => $classNine]]])
            ->assertUnprocessable()->assertJsonValidationErrors('subjects');
    }

    public function test_teachers_can_view_but_not_change_and_students_cannot_view(): void
    {
        $id = $this->section()->json('data.id');

        Sanctum::actingAs(User::factory()->create()->assignRole('faculty'));
        $this->getJson("/api/student-groups/{$id}")->assertOk();
        $this->postJson("/api/student-groups/{$id}/members", ['student_profile_ids' => $this->students(1)])->assertForbidden();

        Sanctum::actingAs(User::factory()->create()->assignRole('student'));
        $this->getJson('/api/student-groups')->assertForbidden();
    }

    public function test_a_subject_in_use_cannot_be_deleted(): void
    {
        $this->section();
        $used = Subject::where('class_level_id', $this->level('C6')->id)->first();

        $this->deleteJson("/api/subjects/{$used->id}")->assertUnprocessable();
    }
}
