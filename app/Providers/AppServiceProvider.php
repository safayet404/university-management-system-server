<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Grades, attendance and exam results of a course may be managed by the
        // course's own faculty, or by anyone allowed to edit every course (admins)
        Gate::define('teach-course', fn (User $user, Course $course) => $user->can('courses.edit')
            || ($course->faculty_profile_id !== null && (int) $course->faculty_profile_id === (int) $user->facultyProfile?->id));
    }
}
