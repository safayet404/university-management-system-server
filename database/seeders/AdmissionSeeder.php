<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdmissionSeeder extends Seeder
{
    public function run(): void
    {
        $faker       = \Faker\Factory::create();
        $departments = Department::all();
        $semester    = 'Spring 2025';
        $acadYear    = '2024-2025';
        $count       = 0;

        $statuses  = ['applied', 'applied', 'under_review', 'under_review', 'shortlisted', 'accepted', 'accepted', 'rejected'];
        $groups    = ['Science', 'Science', 'Science', 'Commerce', 'Arts'];
        $boards    = ['Dhaka', 'Chittagong', 'Rajshahi', 'Sylhet', 'Comilla'];
        $quotas    = ['general', 'general', 'general', 'freedom_fighter', 'tribal'];

        foreach ($departments as $dept) {
            $program = Program::where('department_id', $dept->id)->first();
            $numApps = rand(4, 8);

            for ($i = 0; $i < $numApps; $i++) {
                $gender    = $faker->randomElement(['male', 'female']);
                $firstName = $gender === 'male' ? $faker->firstNameMale() : $faker->firstNameFemale();
                $lastName  = $faker->lastName();
                $email     = strtolower(str_replace(["'", ' '], ['', '.'], $firstName)) . '.' . strtolower($lastName) . $count . '@gmail.com';

                if (Admission::where('email', $email)->exists()) {
                    $email = 'app' . $count . rand(100, 999) . '@gmail.com';
                }

                $sscGpa = round($faker->randomFloat(2, 3.0, 5.0), 2);
                $hscGpa = round($faker->randomFloat(2, 3.0, 5.0), 2);
                $merit  = round(($sscGpa * 40) + ($hscGpa * 60), 2);

                Admission::create([
                    'application_number' => 'APP-2025-' . strtoupper(Str::random(6)),
                    'first_name'         => $firstName,
                    'last_name'          => $lastName,
                    'email'              => $email,
                    'phone'              => '+8801' . rand(10, 99) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                    'gender'             => $gender,
                    'date_of_birth'      => $faker->dateTimeBetween('-22 years', '-17 years')->format('Y-m-d'),
                    'nationality'        => 'Bangladeshi',
                    'religion'           => $faker->randomElement(['Islam', 'Hinduism', 'Christianity', 'Buddhism']),
                    'blood_group'        => $faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+']),
                    'present_address'    => $faker->address(),
                    'ssc_board'          => $faker->randomElement($boards),
                    'ssc_year'           => (string) rand(2020, 2022),
                    'ssc_gpa'            => $sscGpa,
                    'hsc_board'          => $faker->randomElement($boards),
                    'hsc_year'           => (string) rand(2022, 2024),
                    'hsc_gpa'            => $hscGpa,
                    'hsc_group'          => $faker->randomElement($groups),
                    'department_id'      => $dept->id,
                    'program_id'         => $program?->id,
                    'semester'           => $semester,
                    'academic_year'      => $acadYear,
                    'quota'              => $faker->randomElement($quotas),
                    'father_name'        => $faker->name('male'),
                    'mother_name'        => $faker->name('female'),
                    'guardian_phone'     => '+8801' . rand(10, 99) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                    'family_income'      => $faker->randomElement([30000, 50000, 75000, 100000, 150000]),
                    'merit_score'        => $merit,
                    'status'             => $faker->randomElement($statuses),
                ]);
                $count++;
            }
        }

        $this->command->info("✅ AdmissionSeeder: {$count} applications created");
    }
}
