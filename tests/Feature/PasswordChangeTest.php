<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    private function adminCreates(string $email): User
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
        $this->postJson('/api/users', ['name' => 'New Teacher', 'email' => $email, 'password' => 'Temp1234', 'role' => 'faculty'])
            ->assertSuccessful();

        return User::where('email', $email)->first();
    }

    private function loginToken(string $email, string $password): string
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password])->assertOk()->json('token');
    }

    private function as(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_accounts_created_by_an_admin_must_change_their_password_first(): void
    {
        $user = $this->adminCreates('teacher@example.com');
        $this->assertTrue($user->must_change_password);

        $token = $this->loginToken('teacher@example.com', 'Temp1234');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/me', $this->as($token))->assertOk()->assertJsonPath('user.must_change_password', true);
        $this->getJson('/api/courses', $this->as($token))
            ->assertForbidden()
            ->assertJsonPath('code', 'password_change_required');

        $this->putJson('/api/auth/change-password', [
            'current_password' => 'Temp1234', 'password' => 'MyOwnPass9', 'password_confirmation' => 'MyOwnPass9',
        ], $this->as($token))->assertOk()->assertJsonPath('user.must_change_password', false);

        $this->getJson('/api/courses', $this->as($token))->assertOk();
    }

    public function test_weak_or_unchanged_passwords_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Current123']);
        Sanctum::actingAs($user);

        foreach (['short1', 'onlyletters', '12345678', 'Current123'] as $weak) {
            $this->putJson('/api/auth/change-password', ['current_password' => 'Current123', 'password' => $weak, 'password_confirmation' => $weak])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }
    }

    public function test_changing_the_password_signs_out_other_devices(): void
    {
        $user  = User::factory()->create(['email' => 'me@example.com', 'password' => 'Current123']);
        $phone = $this->loginToken('me@example.com', 'Current123');
        $laptop = $this->loginToken('me@example.com', 'Current123');
        $this->app['auth']->forgetGuards();

        $this->putJson('/api/auth/change-password', [
            'current_password' => 'Current123', 'password' => 'Brand3New', 'password_confirmation' => 'Brand3New',
        ], $this->as($laptop))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->assertSame(1, $user->tokens()->count());
        $this->getJson('/api/auth/me', $this->as($laptop))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me', $this->as($phone))->assertUnauthorized();
    }

    public function test_admin_password_reset_requires_a_new_password_again(): void
    {
        $user = User::factory()->create()->assignRole('student');
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));

        $this->postJson("/api/users/{$user->id}/reset-password", ['password' => 'Reset1234', 'password_confirmation' => 'Reset1234'])->assertOk();

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_ordinary_accounts_are_not_interrupted(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('student'));

        $this->getJson('/api/courses')->assertOk();
    }
}
