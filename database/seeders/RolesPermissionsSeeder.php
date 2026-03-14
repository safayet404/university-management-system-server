<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Define all permissions ────────────────────────────
        $permissions = [
            // Dashboard
            'dashboard.read',

            // Users
            'users.read', 'users.create', 'users.edit', 'users.delete',
            'users.export', 'users.import',

            // Students
            'students.read', 'students.create', 'students.edit', 'students.delete',
            'students.export', 'students.import',

            // Faculty
            'faculty.read', 'faculty.create', 'faculty.edit', 'faculty.delete',
            'faculty.export',

            // Staff
            'staff.read', 'staff.create', 'staff.edit', 'staff.delete',

            // Departments
            'departments.read', 'departments.create', 'departments.edit', 'departments.delete',

            // Programs
            'programs.read', 'programs.create', 'programs.edit', 'programs.delete',

            // Courses
            'courses.read', 'courses.create', 'courses.edit', 'courses.delete',

            // Enrollment
            'enrollments.read', 'enrollments.create', 'enrollments.edit', 'enrollments.delete',
            'enrollments.approve', 'enrollments.reject',

            // Timetable
            'timetable.read', 'timetable.create', 'timetable.edit', 'timetable.delete',

            // Attendance
            'attendance.read', 'attendance.create', 'attendance.edit',
            'attendance.export', 'attendance.own',

            // Exams
            'exams.read', 'exams.create', 'exams.edit', 'exams.delete',
            'exams.schedule',

            // Grades
            'grades.read', 'grades.create', 'grades.edit',
            'grades.publish', 'grades.export', 'grades.own',

            // Fees
            'fees.read', 'fees.create', 'fees.edit', 'fees.delete',
            'fees.export', 'fees.collect',

            // Admissions
            'admissions.read', 'admissions.create', 'admissions.edit',
            'admissions.approve', 'admissions.reject', 'admissions.export',

            // Library
            'library.read', 'library.create', 'library.edit', 'library.delete',
            'library.issue', 'library.return',

            // Roles & Permissions
            'roles.read', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.read', 'permissions.create', 'permissions.edit', 'permissions.delete',

            // Reports
            'reports.read', 'reports.generate', 'reports.export',

            // Activity Logs
            'activity-logs.read',

            // Notifications
            'notifications.read', 'notifications.create',

            // Settings
            'settings.read', 'settings.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info('✅ Permissions created: ' . count($permissions));

        // ── Create roles and assign permissions ───────────────

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin — most permissions except super-admin only
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::whereNotIn('name', [
            'roles.delete', 'permissions.delete', 'settings.edit',
        ])->get());

        // Faculty
        $faculty = Role::firstOrCreate(['name' => 'faculty', 'guard_name' => 'web']);
        $faculty->syncPermissions([
            'dashboard.read',
            'courses.read',
            'timetable.read',
            'attendance.read', 'attendance.create', 'attendance.edit', 'attendance.export',
            'grades.read', 'grades.create', 'grades.edit', 'grades.publish',
            'exams.read', 'exams.schedule',
            'students.read',
            'library.read',
            'notifications.read',
        ]);

        // Student
        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student->syncPermissions([
            'dashboard.read',
            'courses.read',
            'timetable.read',
            'attendance.own',
            'grades.own',
            'fees.read',
            'library.read',
            'notifications.read',
            'enrollments.read',
        ]);

        // Staff
        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staff->syncPermissions([
            'dashboard.read',
            'students.read',
            'faculty.read',
            'attendance.read',
            'library.read', 'library.issue', 'library.return',
            'notifications.read',
        ]);

        // Librarian
        $librarian = Role::firstOrCreate(['name' => 'librarian', 'guard_name' => 'web']);
        $librarian->syncPermissions([
            'dashboard.read',
            'library.read', 'library.create', 'library.edit', 'library.delete',
            'library.issue', 'library.return',
            'students.read',
            'notifications.read',
        ]);

        // Accountant
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'dashboard.read',
            'fees.read', 'fees.create', 'fees.edit', 'fees.collect', 'fees.export',
            'students.read',
            'reports.read', 'reports.generate', 'reports.export',
            'notifications.read',
        ]);

        // Admission Officer
        $admissionOfficer = Role::firstOrCreate(['name' => 'admission-officer', 'guard_name' => 'web']);
        $admissionOfficer->syncPermissions([
            'dashboard.read',
            'admissions.read', 'admissions.create', 'admissions.edit',
            'admissions.approve', 'admissions.reject', 'admissions.export',
            'students.read', 'students.create',
            'notifications.read',
        ]);

        // Applicant (external user applying for admission)
        $applicant = Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $applicant->syncPermissions([
            'admissions.create',
            'notifications.read',
        ]);

        $this->command->info('✅ Roles created: super-admin, admin, faculty, student, staff, librarian, accountant, admission-officer, applicant');
    }
}
