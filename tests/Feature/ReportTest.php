<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\FeeInvoice;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        Setting::create(['group' => 'academic', 'key' => 'current_semester', 'value' => 'Spring 2026', 'type' => 'string', 'label' => 'Current Semester']);
        Setting::create(['group' => 'academic', 'key' => 'current_academic_year', 'value' => '2025-2026', 'type' => 'string', 'label' => 'Current Academic Year']);
        Sanctum::actingAs(User::factory()->create()->assignRole('admin'));
    }

    private function student(): StudentProfile
    {
        $dept = Department::firstOrCreate(['code' => 'CSE'], ['name' => 'Computer Science']);

        return StudentProfile::create(['user_id' => User::factory()->create()->id, 'department_id' => $dept->id, 'student_id' => 'S-' . uniqid(), 'academic_status' => 'regular']);
    }

    public function test_every_report_loads(): void
    {
        $this->student();

        foreach (['overview', 'students', 'fees', 'attendance', 'exams', 'admissions'] as $report) {
            $this->getJson("/api/reports/{$report}")->assertOk();
        }
    }

    public function test_student_report_counts_students_by_department(): void
    {
        $this->student();
        $this->student();

        $this->getJson('/api/reports/students')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('by_department.0.count', 2);
    }

    public function test_fee_report_defaults_to_the_current_term_and_counts_discounts_and_fines(): void
    {
        $student = $this->student();
        $invoice = fn (array $a) => FeeInvoice::create($a + [
            'invoice_number' => 'INV-' . uniqid(), 'student_profile_id' => $student->id, 'fee_type' => 'tuition',
            'description' => 'Tuition', 'semester' => 'Spring 2026', 'academic_year' => '2025-2026', 'due_date' => now()->addMonth(),
        ]);

        // 10,000 with a 2,000 scholarship, fully paid; 5,000 plus a 500 fine, unpaid
        $invoice(['amount' => 10000, 'discount' => 2000, 'paid_amount' => 8000, 'status' => 'paid']);
        $invoice(['amount' => 5000, 'fine' => 500]);
        // Another term must not be counted
        FeeInvoice::create(['invoice_number' => 'INV-OLD', 'student_profile_id' => $student->id, 'fee_type' => 'tuition', 'description' => 'Old',
            'amount' => 99999, 'semester' => 'Fall 2024', 'academic_year' => '2024-2025', 'due_date' => now()]);

        $this->getJson('/api/reports/fees')
            ->assertOk()
            ->assertJsonPath('term.semester', 'Spring 2026')
            ->assertJsonPath('summary.total_invoiced', 13500)
            ->assertJsonPath('summary.total_collected', 8000)
            ->assertJsonPath('summary.total_pending', 5500);
    }

    public function test_pdf_reports_download(): void
    {
        $this->student();

        $this->get('/api/reports/pdf/students')->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
