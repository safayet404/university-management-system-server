<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FacultyProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Course $myCourse;
    private Course $otherCourse;
    private Enrollment $myEnrollment;
    private Enrollment $otherEnrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->teacher = $this->facultyUser();
        $this->myCourse    = $this->course($this->teacher->facultyProfile);
        $this->otherCourse = $this->course($this->facultyUser()->facultyProfile);

        $this->myEnrollment    = $this->enroll($this->myCourse);
        $this->otherEnrollment = $this->enroll($this->otherCourse);
    }

    private function facultyUser(): User
    {
        $user = User::factory()->create()->assignRole('faculty');
        FacultyProfile::create(['user_id' => $user->id, 'employee_id' => 'F-' . $user->id, 'designation' => 'Lecturer']);

        return $user;
    }

    private function course(FacultyProfile $faculty): Course
    {
        $department = Department::firstOrCreate(['code' => 'CSE'], ['name' => 'Computer Science']);

        return Course::create([
            'department_id'      => $department->id,
            'faculty_profile_id' => $faculty->id,
            'code'               => 'CSE-' . uniqid(),
            'name'               => 'Algorithms',
        ]);
    }

    private function enroll(Course $course): Enrollment
    {
        $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_id' => 'S-' . uniqid()]);

        return Enrollment::create([
            'student_profile_id' => $student->id,
            'course_id'          => $course->id,
            'semester'           => 'Fall',
            'academic_year'      => '2026',
            'status'             => 'approved',
        ]);
    }

    private function gradePayload(Course $course, Enrollment $enrollment): array
    {
        return [
            'course_id'     => $course->id,
            'semester'      => 'Fall',
            'academic_year' => '2026',
            'grades'        => [[
                'enrollment_id'      => $enrollment->id,
                'student_profile_id' => $enrollment->student_profile_id,
                'total_marks'        => 85,
            ]],
        ];
    }

    private function attendancePayload(Course $course, Enrollment $enrollment): array
    {
        return [
            'course_id'     => $course->id,
            'date'          => '2026-10-17',
            'semester'      => 'Fall',
            'academic_year' => '2026',
            'records'       => [['student_profile_id' => $enrollment->student_profile_id, 'status' => 'present']],
        ];
    }

    private function exam(Course $course): Exam
    {
        return Exam::create([
            'course_id'     => $course->id,
            'created_by'    => $this->teacher->id,
            'title'         => 'Midterm',
            'exam_type'     => 'midterm',
            'semester'      => 'Fall',
            'academic_year' => '2026',
            'total_marks'   => 100,
        ]);
    }

    public function test_faculty_grades_only_their_own_course(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/grades/save', $this->gradePayload($this->myCourse, $this->myEnrollment))->assertOk();
        $this->postJson('/api/grades/save', $this->gradePayload($this->otherCourse, $this->otherEnrollment))->assertForbidden();
        $this->postJson('/api/grades/publish', ['course_id' => $this->otherCourse->id, 'semester' => 'Fall', 'academic_year' => '2026'])->assertForbidden();
        $this->getJson("/api/grades/course?course_id={$this->otherCourse->id}&semester=Fall&academic_year=2026")->assertForbidden();
    }

    public function test_faculty_cannot_slip_another_courses_enrollment_into_their_own_course(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/grades/save', $this->gradePayload($this->myCourse, $this->otherEnrollment))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grades.0.enrollment_id');

        $this->assertDatabaseMissing('course_grades', ['enrollment_id' => $this->otherEnrollment->id]);
    }

    public function test_admin_can_grade_any_course(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));

        $this->postJson('/api/grades/save', $this->gradePayload($this->otherCourse, $this->otherEnrollment))->assertOk();
    }

    public function test_attendance_is_limited_to_own_course_and_its_students(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/attendance/mark', $this->attendancePayload($this->myCourse, $this->myEnrollment))->assertOk();
        $this->postJson('/api/attendance/mark', $this->attendancePayload($this->otherCourse, $this->otherEnrollment))->assertForbidden();
        $this->postJson('/api/attendance/mark', $this->attendancePayload($this->myCourse, $this->otherEnrollment))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('records');
    }

    public function test_exam_results_are_limited_to_own_course_and_its_students(): void
    {
        Sanctum::actingAs($this->teacher);
        $result = fn (Enrollment $e) => ['results' => [['student_profile_id' => $e->student_profile_id, 'marks_obtained' => 70]]];

        $myExam    = $this->exam($this->myCourse);
        $otherExam = $this->exam($this->otherCourse);

        $this->postJson("/api/exams/{$myExam->id}/results", $result($this->myEnrollment))->assertOk();
        $this->postJson("/api/exams/{$otherExam->id}/results", $result($this->otherEnrollment))->assertForbidden();
        $this->patchJson("/api/exams/{$otherExam->id}/publish")->assertForbidden();
        $this->postJson("/api/exams/{$myExam->id}/results", $result($this->otherEnrollment))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('results');
    }

    public function test_faculty_schedules_exams_only_for_own_course(): void
    {
        Sanctum::actingAs($this->teacher);
        $exam = fn (Course $c) => ['course_id' => $c->id, 'title' => 'Quiz 1', 'exam_type' => 'quiz', 'semester' => 'Fall', 'academic_year' => '2026'];

        $this->postJson('/api/exams', $exam($this->myCourse))->assertSuccessful();
        $this->postJson('/api/exams', $exam($this->otherCourse))->assertForbidden();
    }

    public function test_teachable_course_list_shows_faculty_only_their_courses(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->getJson('/api/courses?all=1&teachable=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->myCourse->id);

        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
        $this->getJson('/api/courses?all=1&teachable=1')->assertOk()->assertJsonCount(2, 'data');
    }
}
