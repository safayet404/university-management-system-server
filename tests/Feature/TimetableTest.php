<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;
    private Course $a;
    private Course $b;
    private FacultyProfile $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));

        $this->dept    = Department::create(['name' => 'Computer Science', 'code' => 'CSE']);
        $this->a       = Course::create(['department_id' => $this->dept->id, 'code' => 'CSE101', 'name' => 'Programming']);
        $this->b       = Course::create(['department_id' => $this->dept->id, 'code' => 'CSE102', 'name' => 'Lab']);
        $this->teacher = FacultyProfile::create(['user_id' => User::factory()->create()->id, 'employee_id' => 'F-1', 'designation' => 'Lecturer']);
    }

    private function slot(Course $course, string $start, string $end, array $extra = [])
    {
        return $this->postJson('/api/timetable', array_merge([
            'course_id' => $course->id, 'department_id' => $this->dept->id, 'day_of_week' => 'Sunday',
            'start_time' => $start, 'end_time' => $end, 'semester' => 'Spring 2026', 'academic_year' => '2025-2026',
            'room' => 'R-301',
        ], $extra));
    }

    public function test_sunday_classes_are_allowed(): void
    {
        $this->slot($this->a, '09:00', '10:30')->assertCreated();
    }

    public function test_back_to_back_classes_in_the_same_room_do_not_clash(): void
    {
        $this->slot($this->a, '09:00', '10:30')->assertCreated();
        $this->slot($this->b, '10:30', '12:00')->assertCreated();
    }

    public function test_overlapping_classes_in_the_same_room_clash(): void
    {
        $this->slot($this->a, '09:00', '10:30')->assertCreated();
        $this->slot($this->b, '10:00', '11:00')->assertUnprocessable();
    }

    public function test_a_teacher_cannot_teach_a_class_inside_another_one(): void
    {
        $this->slot($this->a, '09:00', '12:00', ['faculty_profile_id' => $this->teacher->id])->assertCreated();

        $this->slot($this->b, '10:00', '11:00', ['faculty_profile_id' => $this->teacher->id, 'room' => 'R-999'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Faculty already has a class on Sunday at this time (CSE101).']);
    }

    public function test_updates_are_checked_for_clashes_and_time_order(): void
    {
        $this->slot($this->a, '09:00', '10:30')->assertCreated();
        $id = $this->slot($this->b, '11:00', '12:00')->assertCreated()->json('data.id');

        $this->putJson("/api/timetable/{$id}", ['start_time' => '10:00'])->assertUnprocessable();
        $this->putJson("/api/timetable/{$id}", ['end_time' => '10:45'])->assertUnprocessable();
        $this->putJson("/api/timetable/{$id}", ['room' => 'R-302', 'start_time' => '10:00'])->assertOk();

        // Only timetable fields can change
        $this->putJson("/api/timetable/{$id}", ['color' => '#000000', 'is_active' => false])->assertOk();
        $this->assertTrue(TimetableSlot::find($id)->is_active);
    }
}
