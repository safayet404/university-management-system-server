<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('semester');             // e.g. Spring 2024
            $table->string('academic_year');        // e.g. 2024-2025
            $table->string('status')->default('pending'); // pending, approved, rejected, dropped, completed
            $table->string('section')->nullable();  // A, B, C
            $table->decimal('grade', 4, 2)->nullable();      // final grade 0.00 - 4.00
            $table->string('grade_letter')->nullable();       // A+, A, A-, B+...
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate enrollment in same semester
            $table->unique(['student_profile_id', 'course_id', 'semester', 'academic_year'], 'unique_enrollment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
