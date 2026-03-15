<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermissionsSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
            DepartmentSeeder::class,
            ProgramSeeder::class,
            StudentSeeder::class,
            FacultySeeder::class,
        ]);
    }
}
