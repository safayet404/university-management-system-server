<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('faculty_profile_id')->nullable()->constrained('faculty_profiles')->nullOnDelete();

            $table->string('name');
            $table->string('code')->unique();           // e.g. CSE301
            $table->integer('credit_hours')->default(3);
            $table->integer('contact_hours')->default(3);
            $table->string('course_type')->default('theory'); // theory, lab, project, thesis
            $table->string('semester_level')->nullable();      // 1st, 2nd, 3rd...
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->text('syllabus')->nullable();
            $table->string('status')->default('active');       // active, inactive, archived
            $table->integer('max_students')->default(50);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
