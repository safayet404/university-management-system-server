<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        $roles = [
            'faculty'           => 15,
            'student'           => 20,
            'staff'             => 5,
            'librarian'         => 2,
            'accountant'        => 2,
            'admission-officer' => 3,
            'applicant'         => 3,
        ];

        $counter = 100; // Start from 100 to avoid clash with AdminSeeder (001-008)

        foreach ($roles as $role => $count) {
            for ($i = 0; $i < $count; $i++) {
                $gender    = $faker->randomElement(['male', 'female']);
                $firstName = $gender === 'male' ? $faker->firstNameMale() : $faker->firstNameFemale();
                $name      = $firstName . ' ' . $faker->lastName();
                $isStudent  = $role === 'student';
                $isApplicant = $role === 'applicant';

                $employeeId = null;
                $studentId  = null;

                if (!$isStudent && !$isApplicant) {
                    $prefix = strtoupper(substr($role, 0, 3));
                    $employeeId = $prefix . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
                    // Ensure unique
                    while (User::where('employee_id', $employeeId)->exists()) {
                        $counter++;
                        $employeeId = $prefix . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
                    }
                }

                $email = strtolower(str_replace([' ', "'"], ['.', ''], $name)) . $counter . '@unicore.edu';
                // Ensure unique email
                $emailCheck = $email;
                $attempt = 0;
                while (User::where('email', $emailCheck)->exists()) {
                    $attempt++;
                    $emailCheck = strtolower(str_replace([' ', "'"], ['.', ''], $name)) . $counter . $attempt . '@unicore.edu';
                }
                $email = $emailCheck;

                $user = User::create([
                    'name'          => $name,
                    'email'         => $email,
                    'password'      => Hash::make('password'),
                    'phone'         => '+880170' . str_pad($counter, 7, '0', STR_PAD_LEFT),
                    'gender'        => $gender,
                    'date_of_birth' => $faker->dateTimeBetween('-45 years', '-18 years')->format('Y-m-d'),
                    'address'       => $faker->streetAddress(),
                    'city'          => $faker->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna']),
                    'country'       => 'Bangladesh',
                    'status'        => $faker->randomElement(['active', 'active', 'active', 'inactive']),
                    'employee_id'   => $employeeId,
                    'student_id'    => $studentId,
                    'last_login_at' => $faker->dateTimeBetween('-30 days', 'now'),
                    'last_login_ip' => $faker->ipv4(),
                ]);

                $user->assignRole($role);
                $counter++;
            }
        }

        $this->command->info('✅ UserSeeder: ' . array_sum($roles) . ' users created');
    }
}
