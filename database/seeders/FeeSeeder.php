<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeeSeeder extends Seeder
{
    public function run(): void
    {
        $faker    = \Faker\Factory::create();
        $admin    = User::role('super-admin')->first();
        $semester = 'Fall 2024';
        $acadYear = '2024-2025';

        // ── Fee Structures ──────────────────────────────────
        $structures = [
            ['name' => 'Tuition Fee',        'fee_type' => 'tuition',   'amount' => 15000, 'is_mandatory' => true],
            ['name' => 'Lab Fee',             'fee_type' => 'lab',       'amount' => 3000,  'is_mandatory' => true],
            ['name' => 'Library Fee',         'fee_type' => 'library',   'amount' => 1000,  'is_mandatory' => true],
            ['name' => 'Exam Fee',            'fee_type' => 'exam',      'amount' => 2000,  'is_mandatory' => true],
            ['name' => 'Admission Fee',       'fee_type' => 'admission', 'amount' => 5000,  'is_mandatory' => true],
            ['name' => 'Transport Fee',       'fee_type' => 'transport', 'amount' => 2500,  'is_mandatory' => false],
            ['name' => 'Miscellaneous Fee',   'fee_type' => 'misc',      'amount' => 500,   'is_mandatory' => false],
        ];

        $createdStructures = [];
        foreach ($structures as $s) {
            $fs = FeeStructure::firstOrCreate(['name' => $s['name'], 'fee_type' => $s['fee_type']], array_merge($s, ['status' => 'active', 'semester' => $semester, 'academic_year' => $acadYear]));
            $createdStructures[$s['fee_type']] = $fs;
        }

        // ── Generate Invoices for students ──────────────────
        $students  = StudentProfile::where('academic_status', 'regular')->take(20)->get();
        $invoiceCount = 0;
        $paymentCount = 0;

        $mandatoryTypes = ['tuition', 'library', 'exam'];

        foreach ($students as $student) {
            foreach ($mandatoryTypes as $type) {
                $fs = $createdStructures[$type];
                if (FeeInvoice::where(['student_profile_id' => $student->id, 'fee_structure_id' => $fs->id, 'semester' => $semester, 'academic_year' => $acadYear])->exists()) continue;

                $discount = $faker->randomElement([0, 0, 0, 500, 1000]);
                $invoice  = FeeInvoice::create([
                    'invoice_number'     => 'INV-' . strtoupper(Str::random(8)),
                    'student_profile_id' => $student->id,
                    'fee_structure_id'   => $fs->id,
                    'fee_type'           => $type,
                    'description'        => $fs->name . ' - ' . $semester,
                    'amount'             => $fs->amount,
                    'discount'           => $discount,
                    'semester'           => $semester,
                    'academic_year'      => $acadYear,
                    'due_date'           => now()->subDays(rand(0, 60))->format('Y-m-d'),
                    'status'             => 'unpaid',
                ]);

                // Randomly pay some invoices
                $payStatus = $faker->randomElement(['paid', 'paid', 'paid', 'partial', 'unpaid']);
                $netAmount = $fs->amount - $discount;

                if ($payStatus !== 'unpaid') {
                    $payAmount = $payStatus === 'partial' ? round($netAmount * $faker->randomFloat(2, 0.3, 0.8), 2) : $netAmount;
                    FeePayment::create([
                        'fee_invoice_id'     => $invoice->id,
                        'student_profile_id' => $student->id,
                        'collected_by'       => $admin->id,
                        'transaction_id'     => 'TXN-' . strtoupper(Str::random(10)),
                        'amount'             => $payAmount,
                        'payment_method'     => $faker->randomElement(['cash', 'bank_transfer', 'bkash', 'nagad']),
                        'payment_date'       => now()->subDays(rand(0, 30))->format('Y-m-d'),
                    ]);
                    $invoice->paid_amount = $payAmount;
                    $invoice->save();
                    $invoice->updateStatus();
                    $paymentCount++;
                }
                $invoiceCount++;
            }
        }

        $this->command->info("✅ FeeSeeder: {$invoiceCount} invoices, {$paymentCount} payments created");
    }
}
