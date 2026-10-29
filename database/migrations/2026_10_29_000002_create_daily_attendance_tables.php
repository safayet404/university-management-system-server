<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schools and coaching centres take attendance once a day for a whole section
 * or batch (universities keep per-class attendance_sessions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('taken_by')->constrained('users')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['student_group_id', 'date']);
        });

        Schema::create('daily_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status');                       // present, absent, late, leave
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['daily_attendance_id', 'student_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendance_records');
        Schema::dropIfExists('daily_attendances');
    }
};
