<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticated routes that intentionally need no permission:
     * the user's own account, own notifications, and dropdown reference data.
     */
    private const OPEN_ROUTES = [
        'POST api/auth/logout', 'GET api/auth/me', 'PUT api/auth/profile',
        'PUT api/auth/change-password', 'POST api/auth/avatar',
        'GET api/departments', 'GET api/departments/{id}', 'GET api/programs',
        'GET api/courses', 'GET api/courses/{id}',
        'GET api/notifications/unread-count', 'GET api/notifications/recent',
        'GET api/notifications/stats', 'PATCH api/notifications/mark-all-read',
        'DELETE api/notifications/clear-all', 'GET api/notifications',
        'PATCH api/notifications/{id}/read', 'DELETE api/notifications/{id}',
        'GET api/public-settings',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function student(): User
    {
        $user = $this->userWithRole('student');
        StudentProfile::create(['user_id' => $user->id, 'student_id' => 'S-' . $user->id]);

        return $user;
    }

    public function test_every_authenticated_route_requires_a_permission_unless_explicitly_open(): void
    {
        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('auth:sanctum', $route->gatherMiddleware()))
            ->reject(fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'permission:')))
            ->map(fn ($route) => $route->methods()[0] . ' ' . $route->uri())
            ->reject(fn ($key) => in_array($key, self::OPEN_ROUTES))
            ->values()
            ->all();

        $this->assertSame([], $unguarded, 'Routes without a permission middleware');
    }

    public function test_guests_are_rejected(): void
    {
        $this->postJson('/api/grades/save')->assertUnauthorized();
    }

    public function test_student_cannot_save_or_publish_grades(): void
    {
        Sanctum::actingAs($this->student());

        $this->postJson('/api/grades/save')->assertForbidden();
        $this->postJson('/api/grades/publish')->assertForbidden();
    }

    public function test_faculty_can_reach_grade_saving(): void
    {
        Sanctum::actingAs($this->userWithRole('faculty'));

        // Passing authorization lands on validation
        $this->postJson('/api/grades/save')->assertUnprocessable();
    }

    public function test_only_fee_collectors_can_take_payments(): void
    {
        Sanctum::actingAs($this->student());
        $this->postJson('/api/fees/invoices/1/pay')->assertForbidden();

        Sanctum::actingAs($this->userWithRole('accountant'));
        $this->postJson('/api/fees/invoices/1/pay')->assertNotFound();
    }

    public function test_student_cannot_list_everyones_invoices_or_enrollments(): void
    {
        Sanctum::actingAs($this->student());

        $this->getJson('/api/fees/invoices')->assertForbidden();
        $this->getJson('/api/fees/defaulters')->assertForbidden();
        $this->getJson('/api/enrollments')->assertForbidden();
    }

    public function test_student_can_view_only_their_own_grades_and_invoices(): void
    {
        $me    = $this->student();
        $other = $this->student();
        Sanctum::actingAs($me);

        $this->getJson("/api/grades/student/{$me->studentProfile->id}")->assertOk();
        $this->getJson("/api/fees/student/{$me->studentProfile->id}")->assertOk();

        $this->getJson("/api/grades/student/{$other->studentProfile->id}")->assertForbidden();
        $this->getJson("/api/fees/student/{$other->studentProfile->id}")->assertForbidden();
    }

    public function test_staff_with_read_permission_can_view_any_students_invoices(): void
    {
        $student = $this->student();
        Sanctum::actingAs($this->userWithRole('accountant'));

        $this->getJson("/api/fees/student/{$student->studentProfile->id}")->assertOk();
    }

    public function test_admin_cannot_delete_roles_or_edit_settings(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->deleteJson('/api/roles/1')->assertForbidden();
        $this->putJson('/api/settings/general')->assertForbidden();
    }

    public function test_student_cannot_change_roles_or_users(): void
    {
        Sanctum::actingAs($this->student());

        $this->patchJson('/api/roles/1/permissions')->assertForbidden();
        $this->patchJson('/api/users/1/role')->assertForbidden();
        $this->postJson('/api/users/bulk-action')->assertForbidden();
    }
}
