<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        $depts    = Department::all();
        $programs = Program::all();

        if ($depts->isEmpty() || $programs->isEmpty()) {
            $this->command->warn('⚠️ Run DepartmentSeeder and ProgramSeeder first');
            return;
        }

        $batches   = ['2020', '2021', '2022', '2023', '2024'];
        $semesters = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
        $statuses  = ['regular', 'regular', 'regular', 'on-leave', 'graduated'];
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

        $count = 0;
        for ($i = 1; $i <= 30; $i++) {
            $dept    = $depts->random();
            $program = $programs->where('department_id', $dept->id)->first() ?? $programs->random();
            $batch   = $faker->randomElement($batches);
            $semNum  = $faker->numberBetween(1, 2);
            $serial  = str_pad($i, 4, '0', STR_PAD_LEFT);
            $deptCode = str_pad($program->dept_code ?? $dept->id, 3, '0', STR_PAD_LEFT);
            $studentId = substr($batch, 2) . $semNum . '-' . $serial . '-' . $deptCode;

            if (User::where('student_id', $studentId)->exists()) continue;

            $gender    = $faker->randomElement(['male', 'female']);
            $firstName = $gender === 'male' ? $faker->firstNameMale() : $faker->firstNameFemale();
            $name      = $firstName . ' ' . $faker->lastName();
            $email     = strtolower(str_replace(' ', '.', $name)) . $i . '@unicore.edu';

            if (User::where('email', $email)->exists()) continue;

            $user = User::create([
                'name'          => $name,
                'email'         => $email,
                'password'      => Hash::make('password'),
                'phone'         => '+880170' . str_pad($i + 100, 7, '0', STR_PAD_LEFT),
                'gender'        => $gender,
                'date_of_birth' => $faker->dateTimeBetween('-26 years', '-18 years')->format('Y-m-d'),
                'city'          => $faker->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna']),
                'country'       => 'Bangladesh',
                'status'        => 'active',
                'student_id'    => $studentId,
            ]);
            $user->assignRole('student');

            StudentProfile::create([
                'user_id'          => $user->id,
                'student_id'       => $studentId,
                'department_id'    => $dept->id,
                'program_id'       => $program->id,
                'batch'            => $batch,
                'semester'         => $faker->randomElement($semesters),
                'section'          => $faker->randomElement(['A', 'B', 'C']),
                'shift'            => $faker->randomElement(['morning', 'evening']),
                'admission_date'   => $batch . '-01-01',
                'academic_status'  => $faker->randomElement($statuses),
                'cgpa'             => $faker->randomFloat(2, 2.0, 4.0),
                'blood_group'      => $faker->randomElement($bloodGroups),
                'nationality'      => 'Bangladeshi',
                'father_name'      => $faker->name('male'),
                'father_phone'     => '+880170' . rand(1000000, 9999999),
                'mother_name'      => $faker->name('female'),
                'present_address'  => $faker->streetAddress() . ', ' . $faker->city(),
                'permanent_address'=> $faker->streetAddress() . ', ' . $faker->city(),
                'ssc_school'       => $faker->company() . ' High School',
                'ssc_board'        => $faker->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi']),
                'ssc_year'         => strval(intval($batch) - 2),
                'ssc_gpa'          => $faker->randomFloat(2, 3.5, 5.0),
                'hsc_college'      => $faker->company() . ' College',
                'hsc_board'        => $faker->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi']),
                'hsc_year'         => strval(intval($batch) - 1),
                'hsc_gpa'          => $faker->randomFloat(2, 3.5, 5.0),
            ]);
            $count++;
        }

        $this->command->info("✅ StudentSeeder: {$count} students created");
    }
}
