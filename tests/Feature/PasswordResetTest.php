<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC = 'If an account exists for that email, a reset link is on its way.';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['app.frontend_url' => 'https://unicore.example']);
        $this->user = User::factory()->create(['email' => 'nadia@example.com', 'password' => 'OldPass123', 'must_change_password' => true]);
    }

    private function requestToken(): string
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'nadia@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($this->user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;
            return true;
        });

        return $token;
    }

    private function reset(string $token, string $password = 'NewPass456')
    {
        return $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'nadia@example.com', 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    public function test_known_and_unknown_emails_get_the_same_answer(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'nadia@example.com'])->assertOk()->assertJson(['message' => self::GENERIC]);
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->assertJson(['message' => self::GENERIC]);

        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_reset_email_links_to_the_frontend_with_token_and_email(): void
    {
        $token = $this->requestToken();
        $mail  = (new ResetPassword($token))->toMail($this->user);

        $this->assertSame('Reset your ' . config('app.name') . ' password', $mail->subject);
        $this->assertSame('https://unicore.example/reset-password?token=' . $token . '&email=nadia%40example.com', $mail->actionUrl);
    }

    public function test_a_valid_token_resets_the_password_and_signs_out_everywhere(): void
    {
        $this->user->createToken('phone');
        $token = $this->requestToken();

        $this->reset($token)->assertOk();

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check('NewPass456', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/auth/login', ['email' => 'nadia@example.com', 'password' => 'NewPass456'])->assertOk();
    }

    public function test_tokens_are_single_use_and_must_match(): void
    {
        $token = $this->requestToken();

        $this->reset('not-the-token')->assertUnprocessable();
        $this->reset($token)->assertOk();
        $this->reset($token, 'Another789')->assertUnprocessable();
    }

    public function test_reset_follows_the_password_policy(): void
    {
        $this->reset($this->requestToken(), 'weakpass')->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
