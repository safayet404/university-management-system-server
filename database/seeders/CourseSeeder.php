<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\Program;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        $coursesByDept = [
            'CSE' => [
                ['name' => 'Introduction to Programming',         'code' => 'CSE101', 'credits' => 3, 'type' => 'theory', 'sem' => '1st'],
                ['name' => 'Programming Lab',                     'code' => 'CSE102', 'credits' => 1, 'type' => 'lab',    'sem' => '1st'],
                ['name' => 'Data Structures',                     'code' => 'CSE201', 'credits' => 3, 'type' => 'theory', 'sem' => '3rd'],
                ['name' => 'Data Structures Lab',                 'code' => 'CSE202', 'credits' => 1, 'type' => 'lab',    'sem' => '3rd'],
                ['name' => 'Algorithms',                          'code' => 'CSE301', 'credits' => 3, 'type' => 'theory', 'sem' => '5th'],
                ['name' => 'Database Systems',                    'code' => 'CSE302', 'credits' => 3, 'type' => 'theory', 'sem' => '5th'],
                ['name' => 'Operating Systems',                   'code' => 'CSE303', 'credits' => 3, 'type' => 'theory', 'sem' => '5th'],
                ['name' => 'Computer Networks',                   'code' => 'CSE401', 'credits' => 3, 'type' => 'theory', 'sem' => '7th'],
                ['name' => 'Machine Learning',                    'code' => 'CSE402', 'credits' => 3, 'type' => 'theory', 'sem' => '7th'],
                ['name' => 'Software Engineering',                'code' => 'CSE403', 'credits' => 3, 'type' => 'theory', 'sem' => '7th'],
            ],
            'EEE' => [
                ['name' => 'Basic Electrical Engineering',        'code' => 'EEE101', 'credits' => 3, 'type' => 'theory', 'sem' => '1st'],
                ['name' => 'Circuit Analysis',                    'code' => 'EEE201', 'credits' => 3, 'type' => 'theory', 'sem' => '3rd'],
                ['name' => 'Electronics',                         'code' => 'EEE301', 'credits' => 3, 'type' => 'theory', 'sem' => '5th'],
                ['name' => 'Power Systems',                       'code' => 'EEE401', 'credits' => 3, 'type' => 'theory', 'sem' => '7th'],
            ],
            'BBA' => [
                ['name' => 'Principles of Management',            'code' => 'BBA101', 'credits' => 3, 'type' => 'theory', 'sem' => '1st'],
                ['name' => 'Financial Accounting',                'code' => 'BBA201', 'credits' => 3, 'type' => 'theory', 'sem' => '3rd'],
                ['name' => 'Marketing Management',                'code' => 'BBA301', 'credits' => 3, 'type' => 'theory', 'sem' => '5th'],
                ['name' => 'Strategic Management',                'code' => 'BBA401', 'credits' => 3, 'type' => 'theory', 'sem' => '7th'],
            ],
            'CE' => [
                ['name' => 'Engineering Mechanics',               'code' => 'CE101',  'credits' => 3, 'type' => 'theory', 'sem' => '1st'],
                ['name' => 'Structural Analysis',                 'code' => 'CE301',  'credits' => 3, 'type' => 'theory', 'sem' => '5th'],
            ],
            'ELL' => [
                ['name' => 'Introduction to Literature',          'code' => 'ELL101', 'credits' => 3, 'type' => 'theory', 'sem' => '1st'],
                ['name' => 'Applied Linguistics',                 'code' => 'ELL301', 'credits' => 3, 'type' => 'theory', 'sem' => '5th'],
            ],
        ];

        $count = 0;
        foreach ($coursesByDept as $deptCode => $courses) {
            $dept = Department::where('code', $deptCode)->first();
            if (!$dept) continue;

            $program  = Program::where('department_id', $dept->id)->first();
            $faculties = FacultyProfile::where('department_id', $dept->id)->pluck('id')->toArray();

            foreach ($courses as $c) {
                if (Course::where('code', $c['code'])->exists()) continue;

                Course::create([
                    'department_id'      => $dept->id,
                    'program_id'         => $program?->id,
                    'faculty_profile_id' => !empty($faculties) ? $faker->randomElement($faculties) : null,
                    'name'               => $c['name'],
                    'code'               => $c['code'],
                    'credit_hours'       => $c['credits'],
                    'contact_hours'      => $c['credits'],
                    'course_type'        => $c['type'],
                    'semester_level'     => $c['sem'],
                    'max_students'       => $faker->randomElement([40, 50, 60]),
                    'status'             => 'active',
                ]);
                $count++;
            }
        }

        $this->command->info("✅ CourseSeeder: {$count} courses created");
    }
}
