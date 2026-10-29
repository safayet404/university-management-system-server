<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schools and coaching centres bill a fixed fee every month per section or
 * batch. Invoices remember which group and month they bill, so a month can
 * never be billed twice; members can get a fixed discount (siblings, aid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->foreignId('student_group_id')->nullable()->after('fee_structure_id')->constrained()->nullOnDelete();
            $table->string('billing_month', 7)->nullable()->after('student_group_id');   // YYYY-MM

            $table->unique(['student_profile_id', 'student_group_id', 'billing_month']);
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->decimal('monthly_discount', 10, 2)->default(0)->after('optional_subject_id');
            $table->string('discount_note')->nullable()->after('monthly_discount');
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn(['monthly_discount', 'discount_note']);
        });

        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->dropUnique(['student_profile_id', 'student_group_id', 'billing_month']);
            $table->dropConstrainedForeignId('student_group_id');
            $table->dropColumn('billing_month');
        });
    }
};
