<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();

            // Student ID pattern: YYS-SSSS-DDD
            // YY = year, S = semester, SSSS = serial, DDD = dept code
            $table->string('student_id')->unique();     // e.g. 212-0136-006
            $table->string('batch')->nullable();        // e.g. 2021
            $table->string('semester')->nullable();     // current semester e.g. 5th
            $table->string('section')->nullable();      // A, B, C
            $table->string('shift')->nullable();        // morning, evening
            $table->string('admission_type')->nullable(); // regular, transfer, lateral
            $table->date('admission_date')->nullable();
            $table->date('expected_graduation')->nullable();
            $table->date('actual_graduation')->nullable();

            // Academic status
            $table->string('academic_status')->default('regular'); // regular, on-leave, suspended, graduated, dropped
            $table->decimal('cgpa', 4, 2)->nullable();
            $table->integer('completed_credits')->default(0);
            $table->integer('total_credits_required')->default(120);

            // Personal
            $table->string('blood_group')->nullable();
            $table->string('nationality')->nullable()->default('Bangladeshi');
            $table->string('religion')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('nid_number')->nullable();
            $table->string('birth_certificate_no')->nullable();
            $table->string('passport_no')->nullable();

            // Guardian
            $table->string('father_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_phone')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->text('guardian_address')->nullable();

            // Address
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            // Previous education
            $table->string('ssc_school')->nullable();
            $table->string('ssc_board')->nullable();
            $table->string('ssc_year')->nullable();
            $table->decimal('ssc_gpa', 4, 2)->nullable();
            $table->string('hsc_college')->nullable();
            $table->string('hsc_board')->nullable();
            $table->string('hsc_year')->nullable();
            $table->decimal('hsc_gpa', 4, 2)->nullable();

            // Financial
            $table->string('scholarship_type')->nullable();
            $table->boolean('fee_waiver')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
