<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacultyPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        Setting::create(['group' => 'academic', 'key' => 'current_semester', 'value' => 'Spring 2025', 'type' => 'string', 'label' => 'Current Semester']);
        Setting::create(['group' => 'academic', 'key' => 'current_academic_year', 'value' => '2024-2025', 'type' => 'string', 'label' => 'Current Academic Year']);

        $department = Department::create(['name' => 'Computer Science', 'code' => 'CSE']);
        foreach (range(1, 12) as $i) {
            Course::create(['department_id' => $department->id, 'code' => "CSE{$i}00", 'name' => "Course {$i}", 'credit_hours' => 3]);
        }
        foreach (range(1, 4) as $i) {
            StudentProfile::create(['user_id' => User::factory()->create()->id, 'department_id' => $department->id, 'student_id' => "S-{$i}"]);
        }

        User::factory()->create(['email' => 'student@unicore.edu'])->assignRole('student');
        $this->faculty = User::factory()->create(['email' => 'faculty@unicore.edu'])->assignRole('faculty');
        User::factory()->create()->assignRole('accountant');

        $this->seed(DemoAccountsSeeder::class);
        Sanctum::actingAs($this->faculty);
    }

    public function test_overview_summarises_the_faculty_members_classes(): void
    {
        $this->getJson('/api/me/faculty/overview')
            ->assertOk()
            ->assertJsonPath('data.profile.employee_id', 'FAC-DEMO-01')
            ->assertJsonPath('data.courses_count', 2)
            ->assertJsonPath('data.students_count', 10)
            ->assertJsonPath('data.sessions_held', 16)
            ->assertJsonPath('data.grading_pending', 2);
    }

    public function test_course_list_shows_roster_size_attendance_and_grading_state(): void
    {
        $courses = collect($this->getJson('/api/me/faculty/courses')->assertOk()->json('data'))->keyBy('code');

        $this->assertSame(['CSE100', 'CSE200'], $courses->keys()->all());
        $this->assertSame(5, $courses['CSE100']['students']);
        $this->assertSame(5, $courses['CSE100']['grades_entered']);
        $this->assertSame(0, $courses['CSE200']['grades_entered']);
        $this->assertNotNull($courses['CSE100']['attendance_percent']);
    }

    public function test_publishing_grades_clears_the_pending_count(): void
    {
        $this->postJson('/api/grades/publish', ['course_id' => Course::where('code', 'CSE100')->value('id'), 'semester' => 'Spring 2025', 'academic_year' => '2024-2025'])
            ->assertOk();

        $this->getJson('/api/me/faculty/overview')->assertJsonPath('data.grading_pending', 1);
        $courses = collect($this->getJson('/api/me/faculty/courses')->json('data'))->keyBy('code');
        $this->assertTrue($courses['CSE100']['grades_published']);
    }

    public function test_timetable_lists_only_own_classes(): void
    {
        $slots = $this->getJson('/api/me/faculty/timetable')->assertOk()->json('data');

        $this->assertCount(4, $slots);
        $this->assertEqualsCanonicalizing(['CSE100', 'CSE200'], array_unique(array_column(array_column($slots, 'course'), 'code')));
    }

    public function test_other_faculty_and_non_faculty_cannot_see_these_classes(): void
    {
        $other = User::factory()->create()->assignRole('faculty');
        FacultyProfile::create(['user_id' => $other->id, 'employee_id' => 'F-OTHER', 'designation' => 'Lecturer']);
        Sanctum::actingAs($other);
        $this->getJson('/api/me/faculty/courses')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs(User::where('email', 'student@unicore.edu')->first());
        $this->getJson('/api/me/faculty/overview')->assertForbidden();

        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
        $this->getJson('/api/me/faculty/overview')->assertNotFound();
    }

    public function test_published_course_stays_in_the_students_current_term_and_cgpa_is_credit_weighted(): void
    {
        $this->postJson('/api/grades/publish', ['course_id' => Course::where('code', 'CSE100')->value('id'), 'semester' => 'Spring 2025', 'academic_year' => '2024-2025'])
            ->assertOk();

        $student = User::where('email', 'student@unicore.edu')->first();
        Sanctum::actingAs($student);

        $this->getJson('/api/me/student/overview')->assertJsonPath('data.current_courses', 4);
        $this->assertSame(
            \App\Support\GradeReport::for($student->studentProfile)['cgpa'],
            (float) $student->studentProfile->fresh()->cgpa
        );
    }

    public function test_roster_tools_keep_students_after_grades_are_published(): void
    {
        $course = Course::where('code', 'CSE100')->value('id');
        $term   = ['course_id' => $course, 'semester' => 'Spring 2025', 'academic_year' => '2024-2025'];
        $this->postJson('/api/grades/publish', $term)->assertOk();

        $query = http_build_query($term);
        $this->getJson("/api/grades/course?{$query}")->assertOk()->assertJsonCount(5, 'data');
        $this->getJson("/api/attendance/report?{$query}")->assertOk()->assertJsonCount(5, 'data');
        $this->getJson("/api/attendance/students?{$query}")->assertOk()->assertJsonCount(5, 'students');
    }
}
