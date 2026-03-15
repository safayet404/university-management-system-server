<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Computer Science & Engineering',       'code' => 'CSE',  'short_name' => 'CSE',  'head_name' => 'Dr. Rahman Ali',    'email' => 'cse@unicore.edu',  'building' => 'Block A', 'room' => '101'],
            ['name' => 'Electrical & Electronic Engineering',  'code' => 'EEE',  'short_name' => 'EEE',  'head_name' => 'Dr. Karim Hossain', 'email' => 'eee@unicore.edu',  'building' => 'Block B', 'room' => '201'],
            ['name' => 'Business Administration',              'code' => 'BBA',  'short_name' => 'BBA',  'head_name' => 'Dr. Fatema Begum',  'email' => 'bba@unicore.edu',  'building' => 'Block C', 'room' => '301'],
            ['name' => 'Civil Engineering',                    'code' => 'CE',   'short_name' => 'CE',   'head_name' => 'Dr. Alam Khan',     'email' => 'ce@unicore.edu',   'building' => 'Block D', 'room' => '401'],
            ['name' => 'English Language & Literature',        'code' => 'ELL',  'short_name' => 'ELL',  'head_name' => 'Dr. Nadia Islam',   'email' => 'ell@unicore.edu',  'building' => 'Block E', 'room' => '501'],
            ['name' => 'Mathematics',                          'code' => 'MATH', 'short_name' => 'Math', 'head_name' => 'Dr. Rafiq Ahmed',   'email' => 'math@unicore.edu', 'building' => 'Block F', 'room' => '601'],
            ['name' => 'Physics',                              'code' => 'PHY',  'short_name' => 'Phy',  'head_name' => 'Dr. Salam Uddin',   'email' => 'phy@unicore.edu',  'building' => 'Block F', 'room' => '602'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], array_merge($dept, ['status' => 'active']));
        }

        $this->command->info('✅ DepartmentSeeder: ' . count($departments) . ' departments created');
    }
}
