<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();

            // Applicant personal info
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable()->default('Bangladeshi');
            $table->string('religion')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('nid_number')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            // Academic background
            $table->string('ssc_board')->nullable();
            $table->string('ssc_year')->nullable();
            $table->decimal('ssc_gpa', 4, 2)->nullable();
            $table->string('hsc_board')->nullable();
            $table->string('hsc_year')->nullable();
            $table->decimal('hsc_gpa', 4, 2)->nullable();
            $table->string('hsc_group')->nullable(); // Science, Arts, Commerce

            // Application details
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('semester');          // e.g. Spring 2025
            $table->string('academic_year');
            $table->string('quota')->nullable();  // general, freedom_fighter, tribal, etc.

            // Guardian info
            $table->string('father_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->decimal('family_income', 12, 2)->nullable();

            // Status
            $table->string('status')->default('applied'); // applied, under_review, shortlisted, accepted, rejected, enrolled, cancelled
            $table->text('remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('merit_score', 6, 2)->nullable();

            // Processed by
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            // Converted to student
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
