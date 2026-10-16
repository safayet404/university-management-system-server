<?php

namespace Tests\Feature;

use App\Models\FeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        Sanctum::actingAs(User::factory()->create()->assignRole('accountant'));
    }

    private function invoice(array $attributes = []): FeeInvoice
    {
        $student = StudentProfile::create([
            'user_id'    => User::factory()->create()->id,
            'student_id' => 'S-' . uniqid(),
        ]);

        return FeeInvoice::create(array_merge([
            'invoice_number'     => 'INV-' . uniqid(),
            'student_profile_id' => $student->id,
            'fee_type'           => 'tuition',
            'description'        => 'Tuition fee',
            'amount'             => 1000,
            'semester'           => 'Fall',
            'academic_year'      => '2026',
            'due_date'           => now()->addMonth(),
        ], $attributes));
    }

    private function pay(FeeInvoice $invoice, $amount)
    {
        return $this->postJson("/api/fees/invoices/{$invoice->id}/pay", [
            'amount'         => $amount,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ]);
    }

    public function test_partial_payment_marks_invoice_partial(): void
    {
        $invoice = $this->invoice();

        $this->pay($invoice, 400)->assertOk();

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_paying_the_full_due_marks_invoice_paid(): void
    {
        $invoice = $this->invoice(['discount' => 100, 'fine' => 50]);

        $this->pay($invoice, 600)->assertOk();
        $this->pay($invoice, 350)->assertOk();

        $invoice->refresh();
        $this->assertSame('950.00', $invoice->paid_amount);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_overpayment_is_rejected_and_nothing_is_recorded(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, 700)->assertOk();

        $this->pay($invoice, 300.01)->assertUnprocessable()->assertJsonValidationErrors('amount');

        $invoice->refresh();
        $this->assertSame('700.00', $invoice->paid_amount);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_paid_and_waived_invoices_cannot_take_payments(): void
    {
        $this->pay($this->invoice(['status' => 'paid', 'paid_amount' => 1000]), 1)->assertUnprocessable();
        $this->pay($this->invoice(['status' => 'waived']), 1)->assertUnprocessable();
    }

    public function test_cent_amounts_add_up_exactly(): void
    {
        $invoice = $this->invoice(['amount' => 0.3]);

        // 0.1 + 0.1 + 0.1 is 0.30000000000000004 in floating point
        $this->pay($invoice, 0.1)->assertOk();
        $this->pay($invoice, 0.1)->assertOk();
        $this->pay($invoice, 0.1)->assertOk();

        $invoice->refresh();
        $this->assertSame('0.30', $invoice->paid_amount);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_missing_invoice_returns_not_found(): void
    {
        $this->postJson('/api/fees/invoices/999/pay', [
            'amount' => 10, 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
        ])->assertNotFound();
    }
}
