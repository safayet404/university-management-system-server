<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\DailyAttendance;
use App\Models\FacultyProfile;
use App\Models\GroupMember;
use App\Models\GroupSubject;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\SchoolStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private StudentGroup $section;
    private User $classTeacher;
    private array $students;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.institution_type' => 'school']);
        $this->seed(RolesPermissionsSeeder::class);
        $this->seed(SchoolStructureSeeder::class);

        $this->classTeacher = $this->teacher();
        $this->section = StudentGroup::create([
            'kind' => 'section', 'name' => 'A', 'class_level_id' => ClassLevel::where('code', 'C6')->value('id'),
            'academic_year' => '2026', 'class_teacher_id' => $this->classTeacher->facultyProfile->id,
        ]);

        foreach (range(1, 3) as $roll) {
            $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_id' => "S-{$roll}"]);
            GroupMember::create(['student_group_id' => $this->section->id, 'student_profile_id' => $student->id, 'roll_number' => $roll, 'status' => 'active']);
            $this->students[] = $student->id;
        }

        Sanctum::actingAs($this->classTeacher);
    }

    private function teacher(): User
    {
        $user = User::factory()->create()->assignRole('faculty');
        FacultyProfile::create(['user_id' => $user->id, 'employee_id' => 'T-' . $user->id, 'designation' => 'Teacher']);

        return $user;
    }

    private function take(string $date, array $statuses, ?StudentGroup $group = null)
    {
        $group ??= $this->section;

        return $this->putJson("/api/student-groups/{$group->id}/attendance", [
            'date'    => $date,
            'records' => collect($statuses)->map(fn ($status, $i) => ['student_profile_id' => $this->students[$i], 'status' => $status])->all(),
        ]);
    }

    public function test_the_sheet_lists_students_by_roll_and_defaults_to_present(): void
    {
        $sheet = $this->getJson("/api/student-groups/{$this->section->id}/attendance")->assertOk()->json('data');

        $this->assertFalse($sheet['taken']);
        $this->assertSame([1, 2, 3], array_column($sheet['students'], 'roll_number'));
        $this->assertSame(['present', 'present', 'present'], array_column($sheet['students'], 'status'));
    }

    public function test_class_teacher_takes_attendance_and_retaking_the_same_day_updates_it(): void
    {
        $day = today()->toDateString();
        $this->take($day, ['present', 'absent', 'late'])->assertOk()->assertJsonPath('summary.absent', 1);
        $this->take($day, ['present', 'present', 'late'])->assertOk();

        $this->assertSame(1, DailyAttendance::count());
        $sheet = $this->getJson("/api/student-groups/{$this->section->id}/attendance?date={$day}")->json('data');
        $this->assertTrue($sheet['taken']);
        $this->assertSame(['present', 'present', 'late'], array_column($sheet['students'], 'status'));
    }

    public function test_future_dates_and_outsiders_are_refused(): void
    {
        $this->take(today()->addDay()->toDateString(), ['present', 'present', 'present'])->assertUnprocessable();

        $outsider = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_id' => 'OUT']);
        $this->putJson("/api/student-groups/{$this->section->id}/attendance", [
            'date' => today()->toDateString(), 'records' => [['student_profile_id' => $outsider->id, 'status' => 'present']],
        ])->assertUnprocessable()->assertJsonValidationErrors('records');
    }

    public function test_only_the_class_teacher_or_an_admin_can_take_a_sections_attendance(): void
    {
        Sanctum::actingAs($this->teacher());
        $this->take(today()->toDateString(), ['present', 'present', 'present'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
        $this->take(today()->toDateString(), ['present', 'present', 'present'])->assertOk();

        Sanctum::actingAs(User::factory()->create()->assignRole('student'));
        $this->getJson("/api/student-groups/{$this->section->id}/attendance")->assertForbidden();
    }

    public function test_any_teacher_of_a_batch_can_take_its_attendance(): void
    {
        $batch = StudentGroup::create(['kind' => 'batch', 'name' => 'HSC Physics', 'academic_year' => '2026']);
        GroupMember::create(['student_group_id' => $batch->id, 'student_profile_id' => $this->students[0], 'roll_number' => 1, 'status' => 'active']);
        $physicsTeacher = $this->teacher();
        GroupSubject::create(['student_group_id' => $batch->id, 'subject_id' => Subject::value('id'), 'faculty_profile_id' => $physicsTeacher->facultyProfile->id]);

        Sanctum::actingAs($physicsTeacher);
        $this->take(today()->toDateString(), ['present'], $batch)->assertOk();
    }

    public function test_monthly_report_includes_the_last_day_of_the_month(): void
    {
        $last = today()->subMonth()->endOfMonth()->startOfDay();
        $this->take($last->toDateString(), ['present', 'present', 'present'])->assertOk();

        $report = $this->getJson("/api/student-groups/{$this->section->id}/attendance/report?month=" . $last->format('Y-m'))->assertOk()->json('data');

        $this->assertSame(1, $report['days_held']);
    }

    public function test_monthly_report_counts_late_as_attended_and_leaves_out_leave(): void
    {
        $start = today()->startOfMonth();
        $this->take($start->toDateString(), ['present', 'absent', 'leave'])->assertOk();
        $this->take($start->copy()->addDay()->toDateString(), ['late', 'absent', 'leave'])->assertOk();

        $report = $this->getJson("/api/student-groups/{$this->section->id}/attendance/report?month=" . $start->format('Y-m'))->assertOk()->json('data');

        $this->assertSame(2, $report['days_held']);
        $this->assertSame([100, 0, null], array_column($report['students'], 'percent'));
        $this->assertSame(2, $report['students'][2]['leave']);
    }

    public function test_subject_teachers_can_read_the_report_but_not_take_attendance(): void
    {
        $mathTeacher = $this->teacher();
        GroupSubject::create(['student_group_id' => $this->section->id, 'subject_id' => Subject::value('id'), 'faculty_profile_id' => $mathTeacher->facultyProfile->id]);

        Sanctum::actingAs($mathTeacher);
        $this->getJson("/api/student-groups/{$this->section->id}/attendance/report")->assertOk();
        $this->take(today()->toDateString(), ['present', 'present', 'present'])->assertForbidden();
    }

    public function test_teachers_see_their_groups_and_whether_attendance_is_done_today(): void
    {
        $groups = $this->getJson('/api/me/teaching-groups')->assertOk()->json('data');
        $this->assertSame([false], array_column($groups, 'taken_today'));
        $this->assertTrue($groups[0]['is_class_teacher']);

        $this->take(today()->toDateString(), ['present', 'present', 'present']);
        $this->assertSame([true], array_column($this->getJson('/api/me/teaching-groups')->json('data'), 'taken_today'));
    }
}
