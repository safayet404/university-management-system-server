<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->admin      = User::factory()->create()->assignRole('admin');
        $this->superAdmin = User::factory()->create()->assignRole('super-admin');
        Sanctum::actingAs($this->admin);
    }

    private function role(string $name): Role
    {
        return Role::findByName($name, 'web');
    }

    public function test_admin_cannot_promote_themselves_to_super_admin(): void
    {
        $this->patchJson("/api/users/{$this->admin->id}/role", ['role' => 'super-admin'])->assertForbidden();
        $this->putJson("/api/users/{$this->admin->id}", ['role' => 'super-admin'])->assertForbidden();

        $this->assertFalse($this->admin->fresh()->hasRole('super-admin'));
    }

    public function test_admin_cannot_create_a_super_admin(): void
    {
        $this->postJson('/api/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'password123', 'role' => 'super-admin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_admin_cannot_take_over_a_super_admin_account(): void
    {
        $id = $this->superAdmin->id;

        $this->postJson("/api/users/{$id}/reset-password", ['password' => 'hijacked123', 'password_confirmation' => 'hijacked123'])->assertForbidden();
        $this->putJson("/api/users/{$id}", ['email' => 'attacker@example.com'])->assertForbidden();
        $this->patchJson("/api/users/{$id}/status", ['status' => 'suspended'])->assertForbidden();
        $this->postJson('/api/users/bulk-action', ['action' => 'suspend', 'user_ids' => [$id]])->assertForbidden();

        $this->assertNotSame('suspended', $this->superAdmin->fresh()->status);
        $this->assertNotSame('attacker@example.com', $this->superAdmin->fresh()->email);
    }

    public function test_admin_cannot_grant_their_role_permissions_they_lack(): void
    {
        $admin   = $this->role('admin');
        $current = $admin->permissions->pluck('name')->mapWithKeys(fn ($p) => [$p => true])->all();

        $this->patchJson("/api/roles/{$admin->id}/permissions", ['permissions' => $current + ['settings.edit' => true]])
            ->assertForbidden();

        $this->assertFalse($admin->fresh()->hasPermissionTo('settings.edit'));
    }

    public function test_admin_cannot_strip_or_copy_the_super_admin_role(): void
    {
        $super = $this->role('super-admin');

        $this->patchJson("/api/roles/{$super->id}/permissions", ['permissions' => ['users.read' => true]])->assertForbidden();
        $this->postJson("/api/roles/{$super->id}/clone", ['name' => 'super-admin-copy'])->assertForbidden();
        $this->putJson("/api/roles/{$super->id}", ['name' => 'renamed'])->assertUnprocessable();

        $this->assertSame(Permission::count(), $super->fresh()->permissions()->count());
        $this->assertDatabaseMissing('roles', ['name' => 'super-admin-copy']);
    }

    public function test_admin_cannot_rename_a_permission_they_lack(): void
    {
        $settingsEdit = Permission::findByName('settings.edit', 'web');

        $this->putJson("/api/permissions/{$settingsEdit->id}", ['name' => 'settings.edit-renamed'])->assertForbidden();
    }

    public function test_admin_can_still_manage_ordinary_users_and_roles(): void
    {
        $user = User::factory()->create()->assignRole('student');

        $this->patchJson("/api/users/{$user->id}/role", ['role' => 'faculty'])->assertOk();
        $this->postJson('/api/roles/' . $this->role('faculty')->id . '/clone', ['name' => 'teaching-assistant'])->assertOk();

        $ta = $this->role('teaching-assistant');
        $this->patchJson("/api/roles/{$ta->id}/permissions", ['permissions' => ['courses.read' => true, 'grades.read' => true]])
            ->assertOk();

        $this->assertSame(['courses.read', 'grades.read'], $ta->fresh()->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_super_admin_can_assign_any_role(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->patchJson("/api/users/{$this->admin->id}/role", ['role' => 'super-admin'])->assertOk();
    }
}
