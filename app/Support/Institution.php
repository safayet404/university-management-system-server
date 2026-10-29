<?php

namespace App\Support;

/**
 * Each deployment serves one kind of institution (INSTITUTION_TYPE):
 * university (departments, semesters, credits, CGPA), school (classes,
 * sections, roll numbers, GPA-5) or coaching (batches, monthly fees, model tests).
 */
class Institution
{
    public const TYPES = ['university', 'school', 'coaching'];

    public static function type(): string
    {
        $type = config('app.institution_type');

        return in_array($type, self::TYPES, true) ? $type : 'university';
    }

    public static function isUniversity(): bool { return self::type() === 'university'; }
    public static function isSchool(): bool     { return self::type() === 'school'; }
    public static function isCoaching(): bool   { return self::type() === 'coaching'; }
}
