<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->string('status')->default('present'); // present, absent, late, excused
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_profile_id'], 'unique_record');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
