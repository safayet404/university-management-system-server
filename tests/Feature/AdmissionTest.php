<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Department;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmissionTest extends TestCase
{
    use RefreshDatabase;

    private Department $cse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        $this->cse = Department::create(['name' => 'Computer Science', 'code' => 'CSE']);
        Sanctum::actingAs(User::factory()->create()->assignRole('admission-officer'));
    }

    private function apply(array $extra = []): Admission
    {
        $id = $this->postJson('/api/admissions', array_merge([
            'first_name' => 'Rafi', 'last_name' => 'Hasan', 'email' => 'rafi' . uniqid() . '@example.com',
            'department_id' => $this->cse->id, 'semester' => 'Spring 2026', 'academic_year' => '2025-2026',
            'ssc_gpa' => 5, 'hsc_gpa' => 4.8,
        ], $extra))->assertCreated()->json('data.id');

        return Admission::find($id);
    }

    private function accepted(): Admission
    {
        $admission = $this->apply();
        $this->patchJson("/api/admissions/{$admission->id}/accept")->assertOk();

        return $admission->fresh();
    }

    public function test_applicants_cannot_set_workflow_fields(): void
    {
        $admission = $this->apply(['status' => 'accepted', 'student_profile_id' => 999, 'decided_by' => 1, 'merit_score' => 100]);

        $this->assertSame('applied', $admission->status);
        $this->assertNull($admission->student_profile_id);
        $this->assertNull($admission->decided_by);
        $this->assertNotEquals(100, (float) $admission->merit_score);
    }

    public function test_status_changes_follow_the_workflow(): void
    {
        $admission = $this->apply();

        $this->patchJson("/api/admissions/{$admission->id}/review")->assertOk();
        $this->patchJson("/api/admissions/{$admission->id}/review")->assertUnprocessable();
        $this->patchJson("/api/admissions/{$admission->id}/shortlist")->assertOk();
        $this->patchJson("/api/admissions/{$admission->id}/accept")->assertOk();
        $this->postJson("/api/admissions/{$admission->id}/enroll")->assertOk();

        // Enrolled is final
        $this->patchJson("/api/admissions/{$admission->id}/reject")->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
        $this->deleteJson("/api/admissions/{$admission->id}")->assertUnprocessable();
        $this->assertSame('enrolled', $admission->fresh()->status);
    }

    public function test_enrolling_creates_a_student_with_a_personal_temporary_password(): void
    {
        $admission = $this->accepted();

        $response = $this->postJson("/api/admissions/{$admission->id}/enroll")->assertOk();
        $credentials = $response->json('credentials');

        $user = User::where('email', $admission->email)->first();
        $this->assertTrue($user->hasRole('student'));
        $this->assertTrue($user->must_change_password);
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue(Hash::check($credentials['temporary_password'], $user->password));
        $this->assertSame($user->student_id, $credentials['login']);
        $this->assertSame($user->studentProfile->id, $admission->fresh()->student_profile_id);
    }

    public function test_an_application_cannot_be_enrolled_twice(): void
    {
        $admission = $this->accepted();

        $this->postJson("/api/admissions/{$admission->id}/enroll")->assertOk();
        $this->postJson("/api/admissions/{$admission->id}/enroll")->assertUnprocessable();

        $this->assertSame(1, User::where('email', $admission->email)->count());
    }

    public function test_student_ids_stay_unique_after_a_student_is_deleted(): void
    {
        $first  = $this->accepted();
        $second = $this->accepted();

        $firstId = $this->postJson("/api/admissions/{$first->id}/enroll")->json('credentials.login');
        StudentProfile::where('student_id', $firstId)->first()->forceDelete();

        $secondId = $this->postJson("/api/admissions/{$second->id}/enroll")->assertOk()->json('credentials.login');

        $this->assertNotSame($firstId, $secondId);
        $this->assertMatchesRegularExpression('/^\d{3}-\d{4}-CSE$/', $secondId);
    }

    public function test_an_email_that_already_has_an_account_is_refused_clearly(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $admission = $this->apply(['email' => 'taken@example.com']);
        $this->patchJson("/api/admissions/{$admission->id}/accept")->assertOk();

        $this->postJson("/api/admissions/{$admission->id}/enroll")
            ->assertUnprocessable()
            ->assertJson(['message' => 'An account already exists for taken@example.com.']);
    }
}
