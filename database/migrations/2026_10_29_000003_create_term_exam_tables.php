<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School term exams (one per class and academic year, all sections sit it)
 * and coaching model tests (one per batch), with marks per student and
 * subject and the class teacher's remarks for the report card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_exams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind');                         // half_yearly, annual, model_test, ...
            $table->string('academic_year', 20);
            $table->foreignId('class_level_id')->nullable()->constrained()->restrictOnDelete();   // school
            $table->foreignId('student_group_id')->nullable()->constrained()->restrictOnDelete(); // coaching batch
            $table->unsignedSmallInteger('full_marks')->nullable();  // overrides every subject's full marks
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('draft');     // draft, published
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->decimal('marks', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['term_exam_id', 'student_profile_id', 'subject_id']);
        });

        Schema::create('exam_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->text('remarks');
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['term_exam_id', 'student_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_remarks');
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('term_exams');
    }
};
