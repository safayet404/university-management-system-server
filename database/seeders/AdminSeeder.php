<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'        => 'Super Admin',
                'email'       => 'superadmin@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000000',
                'status'      => 'active',
                'employee_id' => 'SA-001',
                'role'        => 'super-admin',
            ],
            [
                'name'        => 'Admin User',
                'email'       => 'admin@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000001',
                'status'      => 'active',
                'employee_id' => 'AD-001',
                'role'        => 'admin',
            ],
            [
                'name'        => 'Dr. John Faculty',
                'email'       => 'faculty@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000002',
                'status'      => 'active',
                'employee_id' => 'FAC-001',
                'gender'      => 'male',
                'role'        => 'faculty',
            ],
            [
                'name'        => 'Sarah Student',
                'email'       => 'student@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000003',
                'status'      => 'active',
                'student_id'  => 'STU-2024-001',
                'gender'      => 'female',
                'role'        => 'student',
            ],
            [
                'name'        => 'Mark Staff',
                'email'       => 'staff@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000004',
                'status'      => 'active',
                'employee_id' => 'STF-001',
                'role'        => 'staff',
            ],
            [
                'name'        => 'Lisa Librarian',
                'email'       => 'librarian@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000005',
                'status'      => 'active',
                'employee_id' => 'LIB-001',
                'role'        => 'librarian',
            ],
            [
                'name'        => 'Tom Accountant',
                'email'       => 'accountant@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000006',
                'status'      => 'active',
                'employee_id' => 'ACC-001',
                'role'        => 'accountant',
            ],
            [
                'name'        => 'Amy Admission',
                'email'       => 'admission@unicore.edu',
                'password'    => Hash::make('password'),
                'phone'       => '+8801700000007',
                'status'      => 'active',
                'employee_id' => 'ADM-001',
                'role'        => 'admission-officer',
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            $user->syncRoles([$role]);
        }

        $this->command->info('✅ Test accounts created (all passwords: password)');
        $this->command->table(
            ['Role', 'Email'],
            collect($users)->map(fn($u) => [$u['role'] ?? '', $u['email']])->toArray()
        );
    }
}
