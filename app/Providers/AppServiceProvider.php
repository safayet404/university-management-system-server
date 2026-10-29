<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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

        // Daily attendance of a section or batch: its class teacher, any teacher of
        // a coaching batch, or anyone who manages classes (admins)
        Gate::define('take-group-attendance', function (User $user, StudentGroup $group) {
            if ($user->can('classes.manage')) {
                return true;
            }
            $facultyId = $user->facultyProfile?->id;
            if (! $facultyId) {
                return false;
            }

            return (int) $group->class_teacher_id === (int) $facultyId
                || ($group->kind === 'batch' && $group->groupSubjects()->where('faculty_profile_id', $facultyId)->exists());
        });

        // Reset links open the frontend, which posts the token back to the API
        ResetPassword::toMailUsing(function ($user, string $token) {
            $url     = config('app.frontend_url') . '/reset-password?' . http_build_query(['token' => $token, 'email' => $user->email]);
            $minutes = config('auth.passwords.users.expire');

            return (new MailMessage)
                ->subject('Reset your ' . config('app.name') . ' password')
                ->greeting("Hello {$user->name},")
                ->line('We received a request to reset the password for your ' . config('app.name') . ' account.')
                ->action('Choose a new password', $url)
                ->line("This link expires in {$minutes} minutes and can be used once.")
                ->line('If you did not ask for this, you can ignore this email — your password will not change.');
        });
    }
}
