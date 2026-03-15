<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_profile_id')->nullable()->constrained('faculty_profiles')->nullOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('semester');
            $table->string('academic_year');
            $table->string('section')->nullable();
            $table->string('day_of_week');        // Monday, Tuesday...
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable();
            $table->string('building')->nullable();
            $table->string('slot_type')->default('lecture'); // lecture, lab, tutorial
            $table->string('color')->nullable();  // for UI display
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent double-booking of room at same time
            $table->unique(['day_of_week', 'start_time', 'room', 'semester', 'academic_year'], 'unique_room_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
