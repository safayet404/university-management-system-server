<?php

namespace Tests\Feature;

use App\Models\FeeInvoice;
use App\Models\StudentGroup;
use App\Models\TermExam;
use App\Models\User;
use Database\Seeders\SchoolDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_demo_gives_the_demo_accounts_a_full_record(): void
    {
        config(['app.institution_type' => 'school']);
        $this->seed();

        $this->assertSame(3, StudentGroup::where('kind', 'section')->count());
        $this->assertSame(2, TermExam::where('status', 'published')->count());

        Sanctum::actingAs(User::where('email', 'student@unicore.edu')->first());
        $overview = $this->getJson('/api/me/student/overview-school')->assertOk()->json('data');
        $this->assertSame('Class 6 – A (Morning)', $overview['groups'][0]['display_name']);
        $this->assertNotNull($overview['attendance']['month']['percent']);
        $this->assertSame('Half Yearly Exam', $overview['latest_result']['exam']['name']);
        $this->assertNotNull($overview['latest_result']['result']['gpa']);
        $this->get('/api/me/student/exam-results/' . $overview['latest_result']['exam']['id'] . '/report-card')->assertOk();

        // The demo teacher is class teacher of 6 A and teaches Mathematics
        Sanctum::actingAs(User::where('email', 'faculty@unicore.edu')->first());
        $groups = $this->getJson('/api/me/teaching-groups')->assertOk()->json('data');
        $this->assertCount(3, $groups);
        $this->assertTrue(collect($groups)->firstWhere('display_name', 'Class 6 – A (Morning)')['is_class_teacher']);

        // Running it again changes nothing
        $invoices = FeeInvoice::count();
        $this->seed(SchoolDemoSeeder::class);
        $this->assertSame($invoices, FeeInvoice::count());
    }

    public function test_coaching_demo_has_batches_model_tests_and_fees(): void
    {
        config(['app.institution_type' => 'coaching']);
        $this->seed();

        $this->assertSame(3, StudentGroup::where('kind', 'batch')->count());

        Sanctum::actingAs(User::where('email', 'student@unicore.edu')->first());
        $overview = $this->getJson('/api/me/student/overview-school')->assertOk()->json('data');
        $this->assertCount(2, $overview['groups']);
        $this->assertSame('marks', $overview['latest_result']['mode']);
        $this->assertNotNull($overview['latest_result']['result']['section_position']);
        $this->assertGreaterThanOrEqual(0, $overview['fees']['due']);

        Sanctum::actingAs(User::where('email', 'admin@unicore.edu')->first());
        $this->assertGreaterThan(0, $this->getJson('/api/fees/monthly')->assertOk()->json('data.totals.collected'));
    }
}
