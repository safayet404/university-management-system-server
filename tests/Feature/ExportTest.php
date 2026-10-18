<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_exports_download(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));

        foreach (['users', 'students', 'faculty'] as $module) {
            $this->get("/api/{$module}/export")
                ->assertOk()
                ->assertHeader('content-disposition');
        }
    }
}
