<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Building blocks for schools and coaching centres (INSTITUTION_TYPE=school|coaching).
 * A student group is a school section ("Class 6 – A") or a coaching batch
 * ("HSC 2026 Physics – Evening"); both hold members with roll numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // Play, Class 6, Class 11
            $table->string('code')->unique();               // PLAY, C6, C11
            $table->string('stage');                        // pre_primary, primary, secondary, higher_secondary
            $table->unsignedSmallInteger('sort_order');
            $table->string('grading')->default('gpa5');     // gpa5, remarks
            $table->boolean('has_streams')->default(false); // Science / Arts / Commerce from class 9
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('class_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('stream')->nullable();           // science, arts, commerce; null = everyone
            $table->unsignedSmallInteger('full_marks')->default(100);
            $table->unsignedSmallInteger('pass_marks')->default(33);
            $table->boolean('is_optional')->default(false); // may be taken as the 4th subject
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->string('kind');                         // section, batch
            $table->string('name');
            $table->foreignId('class_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stream')->nullable();
            $table->string('academic_year');
            $table->string('shift')->nullable();            // morning, day, evening
            $table->foreignId('class_teacher_id')->nullable()->constrained('faculty_profiles')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->date('starts_on')->nullable();          // coaching batches run for a period
            $table->date('ends_on')->nullable();
            $table->decimal('monthly_fee', 10, 2)->nullable();
            $table->string('schedule_note')->nullable();    // "Sat, Mon, Wed 5–7 pm"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'academic_year']);
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('roll_number')->nullable();
            $table->foreignId('optional_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('status')->default('active');    // active, left
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['student_group_id', 'student_profile_id']);
            $table->unique(['student_group_id', 'roll_number']);
        });

        Schema::create('group_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_profile_id')->nullable()->constrained('faculty_profiles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_group_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_subjects');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('class_levels');
    }
};
