<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        config(['app.demo_roles' => ['admin', 'student']]);

        User::factory()->create(['email' => 'admin@unicore.edu', 'status' => 'active'])->assignRole('admin');
        User::factory()->create(['email' => 'boss@unicore.edu', 'status' => 'active'])->assignRole('super-admin');
    }

    private function demoToken(string $role): string
    {
        return $this->postJson('/api/auth/demo-login', ['role' => $role])
            ->assertOk()
            ->assertJsonPath('user.is_demo', true)
            ->json('token');
    }

    public function test_lists_the_configured_demo_roles(): void
    {
        $this->getJson('/api/auth/demo-roles')->assertOk()->assertExactJson(['success' => true, 'data' => ['admin', 'student']]);
    }

    public function test_demo_session_can_read_but_not_write(): void
    {
        $headers = ['Authorization' => 'Bearer ' . $this->demoToken('admin')];

        $this->getJson('/api/users', $headers)->assertOk();
        $this->getJson('/api/auth/me', $headers)->assertOk()->assertJsonPath('user.is_demo', true);

        $this->postJson('/api/users', ['name' => 'x'], $headers)
            ->assertForbidden()
            ->assertJson(['message' => 'This is a read-only demo account.']);
        $this->putJson('/api/auth/change-password', [], $headers)->assertForbidden();
        $this->putJson('/api/auth/profile', ['name' => 'Vandal'], $headers)->assertForbidden();

        $this->assertDatabaseMissing('users', ['name' => 'Vandal']);
    }

    public function test_demo_session_can_log_out(): void
    {
        $this->postJson('/api/auth/logout', [], ['Authorization' => 'Bearer ' . $this->demoToken('admin')])->assertOk();
    }

    public function test_super_admin_and_unlisted_roles_are_never_offered(): void
    {
        config(['app.demo_roles' => ['admin', 'super-admin']]);

        $this->postJson('/api/auth/demo-login', ['role' => 'super-admin'])->assertUnprocessable();
        $this->postJson('/api/auth/demo-login', ['role' => 'accountant'])->assertUnprocessable();
    }

    public function test_demo_is_off_when_no_roles_are_configured(): void
    {
        config(['app.demo_roles' => []]);

        $this->getJson('/api/auth/demo-roles')->assertJsonPath('data', []);
        $this->postJson('/api/auth/demo-login', ['role' => 'admin'])->assertUnprocessable();
    }

    public function test_password_login_keeps_full_access(): void
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@unicore.edu', 'password' => 'password'])
            ->assertOk()->json('token');

        $this->putJson('/api/auth/profile', ['name' => 'Real Admin'], ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])->assertJsonPath('user.is_demo', false);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@unicore.edu', 'password' => 'wrong']);
        }

        $this->postJson('/api/auth/login', ['email' => 'admin@unicore.edu', 'password' => 'wrong'])->assertStatus(429);
    }
}
