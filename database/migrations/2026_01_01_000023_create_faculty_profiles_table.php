<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            // Professional
            $table->string('employee_id')->unique();
            $table->string('designation');          // Professor, Associate Professor, Assistant Professor, Lecturer
            $table->string('employment_type')->default('full-time'); // full-time, part-time, visiting, adjunct
            $table->string('specialization')->nullable();
            $table->text('research_interests')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('employment_status')->default('active'); // active, on-leave, resigned, retired

            // Academic qualifications
            $table->string('highest_degree')->nullable(); // PhD, M.Sc, M.Phil, MBA
            $table->string('phd_institution')->nullable();
            $table->string('phd_year')->nullable();
            $table->string('masters_institution')->nullable();
            $table->string('masters_year')->nullable();
            $table->string('bachelors_institution')->nullable();
            $table->string('bachelors_year')->nullable();

            // Contact & Office
            $table->string('office_room')->nullable();
            $table->string('office_phone')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('website')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('google_scholar')->nullable();
            $table->string('orcid')->nullable();

            // Stats
            $table->integer('publications_count')->default(0);
            $table->integer('citations_count')->default(0);
            $table->decimal('h_index', 5, 2)->nullable();

            // Personal
            $table->string('blood_group')->nullable();
            $table->string('nationality')->nullable()->default('Bangladeshi');
            $table->string('religion')->nullable();
            $table->string('nid_number')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_profiles');
    }
};
