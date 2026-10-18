<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_are_refused_when_disabled(): void
    {
        config(['app.avatar_uploads' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/avatar', ['avatar' => UploadedFile::fake()->create('me.png', 10, 'image/png')])
            ->assertStatus(503)
            ->assertJson(['message' => 'Avatar uploads are disabled on this deployment.']);
    }

    public function test_generated_avatar_is_used_when_uploads_are_disabled(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'avatar' => 'avatars/old.png']);

        $this->assertStringContainsString('storage/avatars/old.png', $user->avatar_url);

        config(['app.avatar_uploads' => false]);
        $this->assertStringStartsWith('https://ui-avatars.com/api/?name=Ada+Lovelace', $user->avatar_url);
    }
}
