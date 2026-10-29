<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\FeeInvoice;
use App\Models\GroupMember;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\SchoolStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;
    private StudentGroup $section;
    private array $members = [];   // GroupMember by roll

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.institution_type' => 'school']);
        $this->seed(RolesPermissionsSeeder::class);
        $this->seed(SchoolStructureSeeder::class);

        $this->accountant = User::factory()->create()->assignRole('accountant');
        $this->section = StudentGroup::create([
            'kind' => 'section', 'name' => 'A', 'class_level_id' => ClassLevel::where('code', 'C6')->value('id'),
            'academic_year' => '2026', 'monthly_fee' => 1500,
        ]);
        foreach (range(1, 3) as $roll) {
            $student = StudentProfile::create(['user_id' => User::factory()->create()->assignRole('student')->id, 'student_id' => "S-{$roll}"]);
            $this->members[$roll] = GroupMember::create([
                'student_group_id' => $this->section->id, 'student_profile_id' => $student->id, 'roll_number' => $roll, 'status' => 'active',
            ]);
        }

        Sanctum::actingAs($this->accountant);
    }

    private function generate(string $month, ?StudentGroup $group = null)
    {
        $group ??= $this->section;

        return $this->postJson("/api/student-groups/{$group->id}/fees/generate", ['month' => $month, 'due_date' => "{$month}-10"]);
    }

    private function month(int $offset = 0): string
    {
        return today()->startOfMonth()->addMonths($offset)->format('Y-m');
    }

    public function test_generating_bills_every_active_student_once_per_month(): void
    {
        $this->generate($this->month())->assertOk()->assertJsonPath('created', 3);
        $this->generate($this->month())->assertOk()->assertJsonPath('created', 0);

        $this->assertSame(3, FeeInvoice::where('billing_month', $this->month())->count());
        $invoice = FeeInvoice::first();
        $this->assertSame([1500.0, 'unpaid', 'tuition'], [(float) $invoice->amount, $invoice->status, $invoice->fee_type]);
        $this->assertStringStartsWith('Monthly fee – ', $invoice->description);
    }

    public function test_left_students_and_students_joining_later_are_not_billed(): void
    {
        $this->members[1]->update(['status' => 'left']);
        $this->members[2]->update(['joined_on' => today()->startOfMonth()->addMonth()->toDateString()]);

        $this->generate($this->month())->assertJsonPath('created', 1);
        $this->generate($this->month(1))->assertJsonPath('created', 2);  // roll 3 again, and roll 2 from the month they join
    }

    public function test_discounts_reduce_the_invoice_and_a_full_discount_waives_it(): void
    {
        $url = fn ($roll) => "/api/student-groups/{$this->section->id}/members/{$this->members[$roll]->id}/discount";
        $this->patchJson($url(1), ['monthly_discount' => 500, 'discount_note' => 'Sibling'])->assertOk();
        $this->patchJson($url(2), ['monthly_discount' => 1500, 'discount_note' => 'Free studentship'])->assertOk();
        $this->patchJson($url(3), ['monthly_discount' => 1600])->assertJsonValidationErrors('monthly_discount');

        $this->generate($this->month())->assertOk();
        $invoices = FeeInvoice::all()->keyBy('student_profile_id');

        $one = $invoices[$this->members[1]->student_profile_id];
        $this->assertSame([1000.0, 'unpaid', 'Sibling'], [$one->net_amount, $one->status, $one->remarks]);
        $this->assertSame('waived', $invoices[$this->members[2]->student_profile_id]->status);
    }

    public function test_a_new_discount_updates_this_months_unpaid_invoice_but_not_paid_or_past_ones(): void
    {
        $this->generate($this->month(-1))->assertOk();
        $this->generate($this->month())->assertOk();
        $mine = fn ($roll, $offset) => FeeInvoice::where('student_profile_id', $this->members[$roll]->student_profile_id)->where('billing_month', $this->month($offset))->first();
        $this->postJson("/api/fees/invoices/{$mine(2, 0)->id}/pay", ['amount' => 100, 'payment_method' => 'cash', 'payment_date' => today()->toDateString()])->assertOk();

        $url = fn ($roll) => "/api/student-groups/{$this->section->id}/members/{$this->members[$roll]->id}/discount";
        $this->patchJson($url(1), ['monthly_discount' => 500, 'discount_note' => 'Sibling'])->assertOk()->assertJsonPath('message', 'Discount saved and applied to 1 unpaid invoice.');
        $this->patchJson($url(2), ['monthly_discount' => 500])->assertOk()->assertJsonPath('message', 'Discount saved. It applies to the next invoices.');
        $this->patchJson($url(3), ['monthly_discount' => 1500])->assertOk();

        $this->assertSame([1000.0, 'Sibling'], [$mine(1, 0)->net_amount, $mine(1, 0)->remarks]);
        $this->assertSame(1500.0, $mine(1, -1)->net_amount);   // last month stays as billed
        $this->assertSame(1500.0, $mine(2, 0)->net_amount);    // already part-paid
        $this->assertSame('waived', $mine(3, 0)->status);

        // Removing the discount brings the full fee back
        $this->patchJson($url(3), ['monthly_discount' => 0])->assertOk();
        $this->assertSame(1500.0, $mine(3, 0)->net_amount);
        $this->assertContains($mine(3, 0)->status, ['unpaid', 'overdue']);   // overdue once the 10th has passed
    }

    public function test_a_fee_must_be_set_and_months_cannot_be_billed_far_ahead(): void
    {
        $noFee = StudentGroup::create(['kind' => 'batch', 'name' => 'Free batch', 'academic_year' => '2026']);
        $this->generate($this->month(), $noFee)->assertJsonValidationErrors('month');
        $this->generate($this->month(2))->assertJsonValidationErrors('month');
        $this->generate($this->month(1))->assertOk();
    }

    public function test_the_sheet_shows_this_months_invoice_and_earlier_dues(): void
    {
        $this->generate($this->month(-1))->assertOk();
        $this->generate($this->month())->assertOk();

        // Roll 1 pays last month in full and part of this month
        $last = FeeInvoice::where('student_profile_id', $this->members[1]->student_profile_id)->where('billing_month', $this->month(-1))->first();
        $this->postJson("/api/fees/invoices/{$last->id}/pay", ['amount' => 1500, 'payment_method' => 'bkash', 'payment_date' => today()->toDateString()])->assertOk();
        $now = FeeInvoice::where('student_profile_id', $this->members[1]->student_profile_id)->where('billing_month', $this->month())->first();
        $this->postJson("/api/fees/invoices/{$now->id}/pay", ['amount' => 500, 'payment_method' => 'cash', 'payment_date' => today()->toDateString()])->assertOk();

        $data = $this->getJson("/api/student-groups/{$this->section->id}/fees?month={$this->month()}")->assertOk()->json('data');
        $rows = collect($data['students'])->keyBy('roll_number');

        $this->assertEquals([500, 1000, 'partial'], [$rows[1]['invoice']['paid_amount'], $rows[1]['invoice']['due_amount'], $rows[1]['invoice']['status']]);
        $this->assertEquals(0, $rows[1]['earlier_due']);
        $this->assertEquals(1500, $rows[2]['earlier_due']);
        $this->assertSame([$this->month(-1)], $rows[2]['earlier_months']);
        $this->assertNotNull($rows[1]['invoice']['last_payment_id']);
        $this->assertEquals(['invoiced' => 3, 'billed' => 4500, 'collected' => 500, 'due' => 4000], array_intersect_key($data['totals'], array_flip(['invoiced', 'billed', 'collected', 'due'])));
    }

    public function test_generate_all_bills_every_group_with_a_fee_and_the_overview_sums_it(): void
    {
        $batch = StudentGroup::create(['kind' => 'batch', 'name' => 'Evening', 'academic_year' => '2026', 'monthly_fee' => 800]);
        GroupMember::create(['student_group_id' => $batch->id, 'student_profile_id' => $this->members[1]->student_profile_id, 'roll_number' => 1, 'status' => 'active']);
        // An ended batch is not billed
        $ended = StudentGroup::create(['kind' => 'batch', 'name' => 'Old', 'academic_year' => '2025', 'monthly_fee' => 800, 'ends_on' => today()->subMonths(2)->toDateString()]);
        GroupMember::create(['student_group_id' => $ended->id, 'student_profile_id' => $this->members[2]->student_profile_id, 'roll_number' => 1, 'status' => 'active']);

        $this->postJson('/api/fees/monthly/generate', ['month' => $this->month(), 'due_date' => today()->toDateString()])->assertOk()->assertJsonPath('created', 4);

        $overview = $this->getJson("/api/fees/monthly?month={$this->month()}")->assertOk()->json('data');
        $this->assertEquals(5300, $overview['totals']['billed']);
        $this->assertSame(0, collect($overview['groups'])->firstWhere('id', $ended->id)['invoiced']);
    }

    public function test_receipts_download_for_staff_and_the_paying_student_only(): void
    {
        $this->generate($this->month())->assertOk();
        $invoice = FeeInvoice::where('student_profile_id', $this->members[1]->student_profile_id)->first();
        $this->postJson("/api/fees/invoices/{$invoice->id}/pay", ['amount' => 1500, 'payment_method' => 'nagad', 'payment_date' => today()->toDateString()])->assertOk();
        $paymentId = $invoice->payments()->value('id');

        $pdf = $this->get("/api/fees/payments/{$paymentId}/receipt")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        Sanctum::actingAs($this->members[1]->student->user);
        $this->get("/api/fees/payments/{$paymentId}/receipt")->assertOk();

        Sanctum::actingAs($this->members[2]->student->user);
        $this->getJson("/api/fees/payments/{$paymentId}/receipt")->assertForbidden();
    }

    public function test_teachers_cannot_see_or_generate_fees(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('faculty'));

        $this->getJson("/api/student-groups/{$this->section->id}/fees")->assertForbidden();
        $this->generate($this->month())->assertForbidden();
    }
}
