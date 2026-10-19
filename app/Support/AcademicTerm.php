<?php

namespace App\Support;

use App\Models\Setting;

/**
 * A term is identified by its semester label (e.g. "Spring 2025") plus the
 * academic year (e.g. "2024-2025"), matching how enrollments are stored.
 */
class AcademicTerm
{
    private const SEASON_ORDER = ['spring' => 1, 'summer' => 2, 'fall' => 3, 'autumn' => 3, 'winter' => 4];

    public static function current(): array
    {
        return [
            'semester'      => Setting::get('current_semester', 'Spring 2025'),
            'academic_year' => Setting::get('current_academic_year', '2024-2025'),
        ];
    }

    /** Sort key so terms order chronologically: "Spring 2024" < "Fall 2024" < "Spring 2025". */
    public static function sortKey(string $semester): int
    {
        preg_match('/([A-Za-z]+)\s*(\d{4})/', $semester, $m);
        $season = self::SEASON_ORDER[strtolower($m[1] ?? '')] ?? 0;

        return ((int) ($m[2] ?? 0)) * 10 + $season;
    }
}
