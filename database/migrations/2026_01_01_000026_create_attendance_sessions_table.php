<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_profile_id')->nullable()->constrained('faculty_profiles')->nullOnDelete();
            $table->foreignId('taken_by')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('semester');
            $table->string('academic_year');
            $table->string('section')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('topic')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_finalized')->default(false);
            $table->timestamps();

            $table->unique(['course_id', 'date', 'section', 'semester', 'academic_year'], 'unique_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
