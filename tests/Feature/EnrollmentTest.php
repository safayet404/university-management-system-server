<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
    }

    private function course(int $maxStudents): Course
    {
        $department = Department::firstOrCreate(['code' => 'CSE'], ['name' => 'Computer Science']);

        return Course::create([
            'department_id' => $department->id,
            'code'          => 'CSE-' . uniqid(),
            'name'          => 'Algorithms',
            'credit_hours'  => 3,
            'max_students'  => $maxStudents,
        ]);
    }

    private function pending(Course $course): Enrollment
    {
        $student = StudentProfile::create([
            'user_id'    => User::factory()->create()->id,
            'student_id' => 'S-' . uniqid(),
        ]);

        return Enrollment::create([
            'student_profile_id' => $student->id,
            'course_id'          => $course->id,
            'semester'           => 'Fall',
            'academic_year'      => '2026',
            'status'             => 'pending',
        ]);
    }

    public function test_approval_stops_at_course_capacity(): void
    {
        $course = $this->course(2);
        [$a, $b, $c] = [$this->pending($course), $this->pending($course), $this->pending($course)];

        $this->patchJson("/api/enrollments/{$a->id}/approve")->assertOk();
        $this->patchJson("/api/enrollments/{$b->id}/approve")->assertOk();
        $this->patchJson("/api/enrollments/{$c->id}/approve")
            ->assertUnprocessable()
            ->assertJson(['message' => 'Course has reached maximum capacity.']);

        $this->assertSame('pending', $c->fresh()->status);
    }

    public function test_bulk_approval_fills_only_the_remaining_seats(): void
    {
        $full  = $this->course(2);
        $other = $this->course(10);
        $ids   = collect([
            $this->pending($full), $this->pending($full), $this->pending($full),
            $this->pending($other),
        ])->pluck('id')->all();

        $this->postJson('/api/enrollments/bulk-approve', ['enrollment_ids' => $ids])
            ->assertOk()
            ->assertJson(['approved' => 3, 'skipped' => 1]);

        $this->assertSame(2, $full->enrollments()->where('status', 'approved')->count());
        $this->assertSame(1, $other->enrollments()->where('status', 'approved')->count());
    }

    public function test_an_enrollment_cannot_be_approved_twice(): void
    {
        $enrollment = $this->pending($this->course(5));

        $this->patchJson("/api/enrollments/{$enrollment->id}/approve")->assertOk();
        $this->patchJson("/api/enrollments/{$enrollment->id}/approve")->assertUnprocessable();
    }

    public function test_concurrent_duplicate_enrollment_returns_422_not_500(): void
    {
        $course   = $this->course(5);
        $existing = $this->pending($course);
        $payload  = $existing->only(['student_profile_id', 'course_id', 'semester', 'academic_year']);
        $existing->forceDelete();

        // Simulate another request inserting the same enrollment after our duplicate check passed
        Enrollment::creating(function () use ($payload) {
            Enrollment::withoutEvents(fn () => Enrollment::create($payload + ['status' => 'pending']));
        });

        $this->postJson('/api/enrollments', $payload)
            ->assertUnprocessable()
            ->assertJson(['message' => 'Student already enrolled in this course for this semester.']);
    }
}
