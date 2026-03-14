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
            'faculty'          => 15,
            'student'          => 20,
            'staff'            => 5,
            'librarian'        => 2,
            'accountant'       => 2,
            'admission-officer' => 3,
            'applicant'        => 3,
        ];

        $counter = 1;

        foreach ($roles as $role => $count) {
            for ($i = 0; $i < $count; $i++) {
                $gender    = $faker->randomElement(['male', 'female']);
                $firstName = $gender === 'male' ? $faker->firstNameMale() : $faker->firstNameFemale();
                $lastName  = $faker->lastName();
                $name      = $firstName . ' ' . $lastName;

                $isStudent  = $role === 'student';
                $isApplicant = $role === 'applicant';
                $isFaculty  = $role === 'faculty';

                $user = User::create([
                    'name'          => $name,
                    'email'         => strtolower(str_replace(' ', '.', $name)) . $counter . '@unicore.edu',
                    'password'      => Hash::make('password'),
                    'phone'         => '+880170' . str_pad($counter, 7, '0', STR_PAD_LEFT),
                    'gender'        => $gender,
                    'date_of_birth' => $faker->dateTimeBetween('-45 years', '-18 years')->format('Y-m-d'),
                    'address'       => $faker->streetAddress(),
                    'city'          => $faker->randomElement(['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna']),
                    'country'       => 'Bangladesh',
                    'status'        => $faker->randomElement(['active', 'active', 'active', 'inactive']),
                    'employee_id'   => !$isStudent && !$isApplicant ? strtoupper(substr($role, 0, 3)) . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT) : null,
                    'student_id'    => $isStudent ? 'STU-2024-' . str_pad($counter, 3, '0', STR_PAD_LEFT) : null,
                    'last_login_at' => $faker->dateTimeBetween('-30 days', 'now'),
                    'last_login_ip' => $faker->ipv4(),
                ]);

                $user->assignRole($role);
                $counter++;
            }
        }

        $this->command->info('✅ UserSeeder: ' . array_sum($roles) . ' users created across all roles');
    }
}
