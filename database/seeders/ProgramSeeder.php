<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            // CSE
            ['dept' => 'CSE', 'name' => 'B.Sc. in Computer Science & Engineering', 'code' => 'BSCSE',  'degree_type' => 'bachelor', 'duration_years' => 4, 'total_credits' => 148, 'dept_code' => 6],
            ['dept' => 'CSE', 'name' => 'M.Sc. in Computer Science & Engineering', 'code' => 'MSCSE',  'degree_type' => 'master',   'duration_years' => 2, 'total_credits' => 60,  'dept_code' => 6],
            // EEE
            ['dept' => 'EEE', 'name' => 'B.Sc. in Electrical & Electronic Engineering', 'code' => 'BSEEE', 'degree_type' => 'bachelor', 'duration_years' => 4, 'total_credits' => 148, 'dept_code' => 7],
            ['dept' => 'EEE', 'name' => 'M.Sc. in Electrical & Electronic Engineering', 'code' => 'MSEEE', 'degree_type' => 'master',   'duration_years' => 2, 'total_credits' => 60,  'dept_code' => 7],
            // BBA
            ['dept' => 'BBA', 'name' => 'Bachelor of Business Administration',      'code' => 'BBA',    'degree_type' => 'bachelor', 'duration_years' => 4, 'total_credits' => 130, 'dept_code' => 8],
            ['dept' => 'BBA', 'name' => 'Master of Business Administration',        'code' => 'MBA',    'degree_type' => 'master',   'duration_years' => 2, 'total_credits' => 60,  'dept_code' => 8],
            // CE
            ['dept' => 'CE',  'name' => 'B.Sc. in Civil Engineering',               'code' => 'BSCE',   'degree_type' => 'bachelor', 'duration_years' => 4, 'total_credits' => 148, 'dept_code' => 9],
            // ELL
            ['dept' => 'ELL', 'name' => 'B.A. in English Language & Literature',    'code' => 'BAELL',  'degree_type' => 'bachelor', 'duration_years' => 4, 'total_credits' => 120, 'dept_code' => 10],
        ];

        foreach ($programs as $p) {
            $dept = Department::where('code', $p['dept'])->first();
            if (!$dept) continue;
            Program::firstOrCreate(['code' => $p['code']], [
                'department_id'  => $dept->id,
                'name'           => $p['name'],
                'code'           => $p['code'],
                'degree_type'    => $p['degree_type'],
                'duration_years' => $p['duration_years'],
                'total_credits'  => $p['total_credits'],
                'dept_code'      => $p['dept_code'],
                'status'         => 'active',
            ]);
        }

        $this->command->info('✅ ProgramSeeder: ' . count($programs) . ' programs created');
    }
}
