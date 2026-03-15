<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');                     // Bachelor of Science in CSE
            $table->string('code')->unique();           // BSCSE
            $table->string('degree_type');              // bachelor, master, diploma, phd
            $table->integer('duration_years')->default(4);
            $table->integer('total_credits')->default(120);
            $table->integer('dept_code')->nullable();   // numeric dept code e.g. 006
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
