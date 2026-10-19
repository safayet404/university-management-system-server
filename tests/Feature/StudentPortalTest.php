<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseGrade;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\GradeReport;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

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

        $this->student = User::factory()->create(['email' => 'student@unicore.edu'])->assignRole('student');
        User::factory()->create(['email' => 'faculty@unicore.edu'])->assignRole('faculty');
        User::factory()->create()->assignRole('accountant');

        $this->seed(DemoAccountsSeeder::class);
        Sanctum::actingAs($this->student);
    }

    public function test_overview_summarises_the_students_own_record(): void
    {
        $this->getJson('/api/me/student/overview')
            ->assertOk()
            ->assertJsonPath('data.profile.student_id', '221-0001-001')
            ->assertJsonPath('data.current_courses', 4)
            ->assertJsonPath('data.current_credits', 12)
            ->assertJsonPath('data.cgpa', 3.66)
            ->assertJsonPath('data.attendance_percent', 78)
            ->assertJsonPath('data.fees_due', 26000);
    }

    public function test_grades_are_grouped_by_term_with_credit_weighted_gpa(): void
    {
        $response = $this->getJson('/api/me/student/grades')->assertOk();

        $this->assertSame(['Spring 2024', 'Fall 2024'], array_column($response->json('data.terms'), 'semester'));
        $this->assertSame([3.63, 3.69], array_column($response->json('data.terms'), 'gpa'));
        $this->assertSame(24, $response->json('data.earned_credits'));
    }

    public function test_unpublished_grades_and_other_students_are_excluded(): void
    {
        $other = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_id' => 'OTHER-1']);
        $enrollment = Enrollment::create(['student_profile_id' => $other->id, 'course_id' => Course::first()->id, 'semester' => 'Spring 2024', 'academic_year' => '2023-2024', 'status' => 'completed']);
        CourseGrade::create(['enrollment_id' => $enrollment->id, 'student_profile_id' => $other->id, 'course_id' => $enrollment->course_id, 'semester' => 'Spring 2024', 'academic_year' => '2023-2024', 'grade_point' => 0, 'grade_letter' => 'F', 'is_published' => true]);

        $mine = $this->student->studentProfile;
        CourseGrade::where('student_profile_id', $mine->id)->first()->update(['is_published' => false]);

        $terms = $this->getJson('/api/me/student/grades')->json('data.terms');
        $this->assertSame(7, collect($terms)->sum(fn ($t) => count($t['courses'])));
    }

    public function test_attendance_counts_late_as_attended_but_not_excused(): void
    {
        $rows = collect($this->getJson('/api/me/student/attendance')->assertOk()->json('data'))->keyBy('code');

        $this->assertSame(100, $rows['CSE100']['percent']);
        $this->assertSame(63, $rows['CSE300']['percent']);
        $this->assertSame(1, $rows['CSE300']['excused']);
    }

    public function test_fees_and_timetable_show_only_own_records(): void
    {
        $this->getJson('/api/me/student/fees')
            ->assertOk()
            ->assertJsonPath('data.summary.total_due', 26000)
            ->assertJsonCount(3, 'data.invoices');

        $this->getJson('/api/me/student/timetable')->assertOk()->assertJsonCount(8, 'data');
    }

    public function test_non_students_cannot_use_the_student_portal(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('faculty'));
        $this->getJson('/api/me/student/overview')->assertForbidden();

        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
        $this->getJson('/api/me/student/overview')->assertNotFound();
    }

    public function test_grade_report_weights_gpa_by_credit_hours(): void
    {
        $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_id' => 'W-1']);
        $heavy = Course::create(['department_id' => Department::first()->id, 'code' => 'W4', 'name' => 'Heavy', 'credit_hours' => 4]);
        $light = Course::create(['department_id' => Department::first()->id, 'code' => 'W1', 'name' => 'Light', 'credit_hours' => 1]);

        foreach ([[$heavy, 4.0], [$light, 2.0]] as [$course, $point]) {
            $e = Enrollment::create(['student_profile_id' => $student->id, 'course_id' => $course->id, 'semester' => 'Fall 2024', 'academic_year' => '2024-2025', 'status' => 'completed']);
            CourseGrade::create(['enrollment_id' => $e->id, 'student_profile_id' => $student->id, 'course_id' => $course->id, 'semester' => 'Fall 2024', 'academic_year' => '2024-2025', 'grade_point' => $point, 'is_published' => true]);
        }

        // (4 × 4.0 + 1 × 2.0) / 5 = 3.6, not the plain average 3.0
        $this->assertSame(3.6, GradeReport::for($student)['cgpa']);
    }
}
