<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lets the institution email a student's guardian from inside the system
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('guardian_email')->nullable()->after('guardian_phone');
        });
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('guardian_email')->nullable()->after('guardian_phone');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', fn (Blueprint $table) => $table->dropColumn('guardian_email'));
        Schema::table('admissions', fn (Blueprint $table) => $table->dropColumn('guardian_email'));
    }
};
