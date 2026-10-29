<?php

namespace Database\Seeders;

use App\Models\ClassLevel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Bangladeshi class levels (Play – Class 12) and a starting set of NCTB-style
 * subjects. Schools add, rename or remove subjects afterwards. Safe to rerun:
 *   php artisan db:seed --class=SchoolStructureSeeder --force
 */
class SchoolStructureSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['Play', 'PLAY', 'pre_primary'], ['Nursery', 'NURSERY', 'pre_primary'], ['KG', 'KG', 'pre_primary'],
            ['Class 1', 'C1', 'primary'], ['Class 2', 'C2', 'primary'], ['Class 3', 'C3', 'primary'],
            ['Class 4', 'C4', 'primary'], ['Class 5', 'C5', 'primary'],
            ['Class 6', 'C6', 'secondary'], ['Class 7', 'C7', 'secondary'], ['Class 8', 'C8', 'secondary'],
            ['Class 9', 'C9', 'secondary'], ['Class 10', 'C10', 'secondary'],
            ['Class 11', 'C11', 'higher_secondary'], ['Class 12', 'C12', 'higher_secondary'],
        ];

        foreach ($levels as $i => [$name, $code, $stage]) {
            $level = ClassLevel::updateOrCreate(['code' => $code], [
                'name'        => $name,
                'stage'       => $stage,
                'sort_order'  => $i + 1,
                // Pre-primary children get written remarks, not grades
                'grading'     => $stage === 'pre_primary' ? 'remarks' : 'gpa5',
                'has_streams' => in_array($code, ['C9', 'C10', 'C11', 'C12'], true),
            ]);

            foreach ($this->subjectsFor($code) as $n => [$subject, $stream, $optional]) {
                Subject::updateOrCreate(
                    ['code' => $code . '-' . self::CODES[$subject] . ($stream ? '-' . strtoupper($stream[0]) : '')],
                    ['name' => $subject, 'class_level_id' => $level->id, 'stream' => $stream, 'is_optional' => $optional, 'sort_order' => $n + 1, 'full_marks' => 100, 'pass_marks' => 33]
                );
            }
        }

        $this->command?->info('✅ Class levels and subjects ready');
    }

    /** Short, unique subject codes ("Bangla" and "Bangladesh and Global Studies" must not collide) */
    private const CODES = [
        'Bangla' => 'BAN', 'English' => 'ENG', 'Mathematics' => 'MAT', 'Drawing' => 'DRW',
        'Primary Science' => 'PSC', 'Bangladesh and Global Studies' => 'BGS', 'Religion' => 'REL',
        'Science' => 'SCI', 'ICT' => 'ICT', 'Physics' => 'PHY', 'Chemistry' => 'CHE', 'Biology' => 'BIO',
        'Higher Mathematics' => 'HMA', 'History' => 'HIS', 'Geography' => 'GEO', 'Civics' => 'CIV',
        'Accounting' => 'ACC', 'Finance and Banking' => 'FIN', 'Business Entrepreneurship' => 'BEN',
        'Agriculture Studies' => 'AGR', 'Economics' => 'ECO', 'Logic' => 'LOG',
        'Business Organisation' => 'BOR', 'Statistics' => 'STA',
    ];

    /** [name, stream (null = all), may be the 4th subject] */
    private function subjectsFor(string $code): array
    {
        $core = fn (array $extra = []) => array_merge([['Bangla', null, false], ['English', null, false], ['Mathematics', null, false]], $extra);

        return match (true) {
            in_array($code, ['PLAY', 'NURSERY', 'KG']) => $core([['Drawing', null, false]]),
            in_array($code, ['C1', 'C2'])              => $core(),
            in_array($code, ['C3', 'C4', 'C5'])        => $core([['Primary Science', null, false], ['Bangladesh and Global Studies', null, false], ['Religion', null, false]]),
            in_array($code, ['C6', 'C7', 'C8'])        => $core([['Science', null, false], ['Bangladesh and Global Studies', null, false], ['Religion', null, false], ['ICT', null, false]]),
            in_array($code, ['C9', 'C10'])             => $core([
                ['Religion', null, false], ['ICT', null, false], ['Bangladesh and Global Studies', null, false],
                ['Physics', 'science', false], ['Chemistry', 'science', false], ['Biology', 'science', false], ['Higher Mathematics', 'science', true],
                ['History', 'arts', false], ['Geography', 'arts', false], ['Civics', 'arts', false],
                ['Accounting', 'commerce', false], ['Finance and Banking', 'commerce', false], ['Business Entrepreneurship', 'commerce', false],
                ['Agriculture Studies', null, true],
            ]),
            in_array($code, ['C11', 'C12'])            => [
                ['Bangla', null, false], ['English', null, false], ['ICT', null, false],
                ['Physics', 'science', false], ['Chemistry', 'science', false], ['Biology', 'science', true], ['Higher Mathematics', 'science', true],
                ['History', 'arts', false], ['Civics', 'arts', false], ['Economics', 'arts', false], ['Logic', 'arts', true],
                ['Accounting', 'commerce', false], ['Business Organisation', 'commerce', false], ['Finance and Banking', 'commerce', false], ['Statistics', 'commerce', true],
            ],
            default => [],
        };
    }
}
