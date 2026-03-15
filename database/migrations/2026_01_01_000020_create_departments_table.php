<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();           // e.g. CSE, EEE, BBA
            $table->string('short_name')->nullable();
            $table->text('description')->nullable();
            $table->string('head_name')->nullable();    // department head name
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('building')->nullable();
            $table->string('room')->nullable();
            $table->string('website')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
