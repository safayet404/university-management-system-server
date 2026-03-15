<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FacultySeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();
        $departments = Department::all();

        if ($departments->isEmpty()) {
            $this->command->warn('⚠️ Run DepartmentSeeder first');
            return;
        }

        $designations    = ['Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer', 'Senior Lecturer'];
        $employmentTypes = ['full-time', 'full-time', 'full-time', 'part-time', 'visiting'];
        $degrees         = ['PhD', 'M.Sc', 'M.Phil', 'MBA', 'M.Eng'];
        $bloodGroups     = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

        $specializations = [
            'CSE'  => ['Machine Learning', 'Computer Networks', 'Software Engineering', 'Database Systems', 'Cybersecurity', 'IoT'],
            'EEE'  => ['Power Systems', 'Electronics', 'Signal Processing', 'Telecommunications', 'Robotics'],
            'BBA'  => ['Finance', 'Marketing', 'HRM', 'Operations Management', 'Strategic Management'],
            'CE'   => ['Structural Engineering', 'Environmental Engineering', 'Transportation', 'Geotechnical'],
            'ELL'  => ['Literature', 'Linguistics', 'Applied Linguistics', 'Creative Writing'],
            'MATH' => ['Pure Mathematics', 'Applied Mathematics', 'Statistics', 'Numerical Analysis'],
            'PHY'  => ['Quantum Physics', 'Condensed Matter', 'Astrophysics', 'Optics'],
        ];

        $count = 0;
        $empCounter = 200; // Start from 200 to avoid conflicts

        foreach ($departments as $dept) {
            $deptSpecs = $specializations[$dept->code] ?? ['General'];
            $numFaculty = rand(3, 5);

            for ($i = 0; $i < $numFaculty; $i++) {
                $employeeId = 'FAC-' . str_pad($empCounter, 3, '0', STR_PAD_LEFT);
                while (User::where('employee_id', $employeeId)->exists() || FacultyProfile::where('employee_id', $employeeId)->exists()) {
                    $empCounter++;
                    $employeeId = 'FAC-' . str_pad($empCounter, 3, '0', STR_PAD_LEFT);
                }

                $gender    = $faker->randomElement(['male', 'female']);
                $prefix    = $gender === 'male' ? 'Dr.' : 'Dr.';
                $firstName = $gender === 'male' ? $faker->firstNameMale() : $faker->firstNameFemale();
                $name      = $prefix . ' ' . $firstName . ' ' . $faker->lastName();
                $email     = strtolower(str_replace([' ', '.', "'"], ['.', '', ''], $firstName)) . $empCounter . '@unicore.edu';

                while (User::where('email', $email)->exists()) {
                    $empCounter++;
                    $email = strtolower(str_replace([' ', '.', "'"], ['.', '', ''], $firstName)) . $empCounter . '@unicore.edu';
                }

                $designation   = $faker->randomElement($designations);
                $employType    = $faker->randomElement($employmentTypes);
                $specialization = $faker->randomElement($deptSpecs);
                $joiningYear   = rand(2005, 2023);

                $user = User::create([
                    'name'          => $name,
                    'email'         => $email,
                    'password'      => Hash::make('password'),
                    'phone'         => '+880170' . str_pad($empCounter, 7, '0', STR_PAD_LEFT),
                    'gender'        => $gender,
                    'date_of_birth' => $faker->dateTimeBetween('-55 years', '-28 years')->format('Y-m-d'),
                    'city'          => $faker->randomElement(['Dhaka', 'Chittagong', 'Sylhet']),
                    'country'       => 'Bangladesh',
                    'status'        => 'active',
                    'employee_id'   => $employeeId,
                ]);
                $user->assignRole('faculty');

                FacultyProfile::create([
                    'user_id'             => $user->id,
                    'employee_id'         => $employeeId,
                    'department_id'       => $dept->id,
                    'designation'         => $designation,
                    'employment_type'     => $employType,
                    'employment_status'   => $faker->randomElement(['active', 'active', 'active', 'on-leave']),
                    'specialization'      => $specialization,
                    'research_interests'  => implode(', ', $faker->randomElements($deptSpecs, min(3, count($deptSpecs)))),
                    'joining_date'        => $joiningYear . '-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-01',
                    'highest_degree'      => $faker->randomElement($degrees),
                    'phd_institution'     => $faker->randomElement(['BUET', 'DU', 'NSU', 'BRAC University', 'MIT', 'Stanford', 'Oxford']),
                    'phd_year'            => (string) rand(1995, 2018),
                    'masters_institution' => $faker->randomElement(['BUET', 'DU', 'NSU', 'BRAC University']),
                    'masters_year'        => (string) rand(1990, 2015),
                    'office_room'         => $dept->building . '-' . rand(100, 999),
                    'office_phone'        => '+880' . rand(1000000000, 9999999999),
                    'publications_count'  => rand(0, 50),
                    'citations_count'     => rand(0, 500),
                    'h_index'             => rand(0, 20),
                    'blood_group'         => $faker->randomElement($bloodGroups),
                    'nationality'         => 'Bangladeshi',
                ]);

                $count++;
                $empCounter++;
            }
        }

        $this->command->info("✅ FacultySeeder: {$count} faculty members created");
    }
}
