<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                     // e.g. Tuition Fee, Lab Fee
            $table->string('fee_type');                 // tuition, lab, library, exam, admission, transport, hostel, misc
            $table->decimal('amount', 10, 2);
            $table->string('semester')->nullable();     // null = all semesters
            $table->string('academic_year')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained('fee_structures')->nullOnDelete();
            $table->string('fee_type');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('fine', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('semester');
            $table->string('academic_year');
            $table->date('due_date');
            $table->string('status')->default('unpaid'); // unpaid, partial, paid, waived, overdue
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('collected_by')->constrained('users')->cascadeOnDelete();
            $table->string('transaction_id')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('cash'); // cash, bank_transfer, online, cheque, bkash, nagad
            $table->date('payment_date');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('fee_structures');
    }
};
