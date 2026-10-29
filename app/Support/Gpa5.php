<?php

namespace App\Support;

/**
 * Bangladesh school grading (SSC/HSC style, GPA out of 5).
 *
 * Subject: marks as a percentage of full marks → A+ (80+) 5, A (70+) 4,
 * A- (60+) 3.5, B (50+) 3, C (40+) 2, D (33+) 1, otherwise F 0. Marks below
 * the subject's pass marks, or absence, is F.
 *
 * Result: GPA = (points of compulsory subjects + the 4th subject's points
 * above 2) / number of compulsory subjects, capped at 5. An F in any
 * compulsory subject fails the student (GPA 0); an F in the 4th subject does not.
 */
class Gpa5
{
    private const SCALE = [[80, 'A+', 5.0], [70, 'A', 4.0], [60, 'A-', 3.5], [50, 'B', 3.0], [40, 'C', 2.0], [33, 'D', 1.0]];

    /** @return array{letter: string, point: float, passed: bool} */
    public static function grade(?float $marks, int $fullMarks, int $passMarks, bool $absent = false): array
    {
        if ($absent || $marks === null || $fullMarks <= 0 || $marks < $passMarks) {
            return ['letter' => 'F', 'point' => 0.0, 'passed' => false];
        }

        $percent = $marks / $fullMarks * 100;
        foreach (self::SCALE as [$min, $letter, $point]) {
            if ($percent >= $min) {
                return ['letter' => $letter, 'point' => $point, 'passed' => true];
            }
        }

        // Passed the subject's own pass marks but under 33%: lowest passing grade
        return ['letter' => 'D', 'point' => 1.0, 'passed' => true];
    }

    public static function letterFor(float $gpa): string
    {
        return match (true) {
            $gpa >= 5.0 => 'A+',
            $gpa >= 4.0 => 'A',
            $gpa >= 3.5 => 'A-',
            $gpa >= 3.0 => 'B',
            $gpa >= 2.0 => 'C',
            $gpa >= 1.0 => 'D',
            default     => 'F',
        };
    }

    /**
     * @param array<int, array{name: string, full_marks: int, pass_marks: int, marks: ?float, is_absent: bool, is_fourth: bool}> $subjects
     * @return array{status: string, gpa: ?float, letter: ?string, total: float, full_total: int, subjects: array, missing: int}
     */
    public static function result(array $subjects): array
    {
        $rows = [];
        $missing = 0;
        foreach ($subjects as $s) {
            $entered = $s['is_absent'] || $s['marks'] !== null;
            if (! $entered) {
                $missing++;
            }
            $grade  = $entered ? self::grade($s['marks'], $s['full_marks'], $s['pass_marks'], $s['is_absent']) : null;
            $rows[] = $s + ['grade' => $grade];
        }

        $compulsory = array_values(array_filter($rows, fn ($r) => ! $r['is_fourth']));
        $fourth     = array_values(array_filter($rows, fn ($r) => $r['is_fourth']))[0] ?? null;
        $total      = array_sum(array_map(fn ($r) => (float) ($r['marks'] ?? 0), $rows));
        $fullTotal  = array_sum(array_column($rows, 'full_marks'));

        $base = ['total' => round($total, 2), 'full_total' => (int) $fullTotal, 'subjects' => $rows, 'missing' => $missing];

        // Results are only final once every compulsory subject has marks
        $compulsoryMissing = count(array_filter($compulsory, fn ($r) => $r['grade'] === null));
        if (! $compulsory || $compulsoryMissing > 0) {
            return $base + ['status' => 'incomplete', 'gpa' => null, 'letter' => null];
        }

        if (array_filter($compulsory, fn ($r) => ! $r['grade']['passed'])) {
            return $base + ['status' => 'failed', 'gpa' => 0.0, 'letter' => 'F'];
        }

        $points = array_sum(array_map(fn ($r) => $r['grade']['point'], $compulsory));
        $bonus  = $fourth && $fourth['grade'] ? max(0, $fourth['grade']['point'] - 2) : 0;
        $gpa    = min(5.0, round(($points + $bonus) / count($compulsory), 2));

        return $base + ['status' => 'passed', 'gpa' => $gpa, 'letter' => self::letterFor($gpa)];
    }

    /**
     * Competition ranking ("1, 2, 2, 4"): passed before failed before incomplete,
     * then GPA, then total marks. With $byTotal (coaching model tests) only the
     * total counts.
     *
     * @param array<int|string, array{status: string, gpa: ?float, total: float}> $results keyed by student
     * @return array<int|string, ?int> position per key (null when incomplete)
     */
    public static function merit(array $results, bool $byTotal = false): array
    {
        $order = ['passed' => 0, 'failed' => 1, 'incomplete' => 2];
        $keys  = array_keys($results);
        usort($keys, function ($a, $b) use ($results, $order, $byTotal) {
            $x = $results[$a];
            $y = $results[$b];
            if (! $byTotal && $order[$x['status']] !== $order[$y['status']]) {
                return $order[$x['status']] <=> $order[$y['status']];
            }
            if (! $byTotal && ($x['gpa'] ?? -1) !== ($y['gpa'] ?? -1)) {
                return ($y['gpa'] ?? -1) <=> ($x['gpa'] ?? -1);
            }

            return $y['total'] <=> $x['total'];
        });

        $positions = [];
        $previous  = null;
        foreach ($keys as $i => $key) {
            $r = $results[$key];
            if ($r['status'] === 'incomplete') {
                $positions[$key] = null;
                continue;
            }
            $signature = $byTotal ? [$r['total']] : [$r['status'], $r['gpa'], $r['total']];
            $positions[$key] = $previous !== null && $previous['signature'] === $signature ? $previous['position'] : $i + 1;
            $previous = ['signature' => $signature, 'position' => $positions[$key]];
        }

        return $positions;
    }
}
