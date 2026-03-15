<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();  // recipient
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete(); // sender (null = system)
            $table->string('type');           // info, success, warning, error, announcement
            $table->string('category');       // admission, enrollment, fee, exam, grade, attendance, library, general, system
            $table->string('title');
            $table->text('message');
            $table->string('action_url')->nullable();   // link to relevant page
            $table->string('action_label')->nullable(); // e.g. "View Invoice"
            $table->json('meta')->nullable();            // extra data
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
