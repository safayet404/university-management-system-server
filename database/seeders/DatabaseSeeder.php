<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermissionsSeeder::class,  // 1. roles first
            AdminSeeder::class,             // 2. admin users
            UserSeeder::class,              // 3. other users
            DepartmentSeeder::class,        // 4. departments
            ProgramSeeder::class,           // 5. programs (needs departments)
            StudentSeeder::class,           // 6. students (needs dept/program)
            FacultySeeder::class,           // 7. faculty (needs dept)
            CourseSeeder::class,            // 8. courses (needs dept/faculty) ← MISSING
            EnrollmentSeeder::class,        // 9. enrollments (needs students/courses)
            AttendanceSeeder::class,        // 10. attendance (needs enrollments) ← was too early
            ExamSeeder::class,              // 11. exams (needs courses/enrollments)
            GradeSeeder::class,             // 12. grades (needs exams/enrollments)
            FeeSeeder::class,               // 13. fees (needs students)
            AdmissionSeeder::class,         // 14. admissions (needs dept/programs)
            LibrarySeeder::class,           // 15. library
            TimetableSeeder::class,         // 16. timetable (needs courses/faculty)
            NotificationSeeder::class,      // 17. notifications (needs users)
            SettingsSeeder::class
        ]);
    }
}
