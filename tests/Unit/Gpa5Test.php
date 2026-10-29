<?php

namespace Tests\Unit;

use App\Support\Gpa5;
use PHPUnit\Framework\TestCase;

class Gpa5Test extends TestCase
{
    private function subject(?float $marks, bool $fourth = false, bool $absent = false, int $full = 100, int $pass = 33): array
    {
        return ['name' => 'S', 'full_marks' => $full, 'pass_marks' => $pass, 'marks' => $marks, 'is_absent' => $absent, 'is_fourth' => $fourth];
    }

    public function test_subject_grades_follow_the_board_scale(): void
    {
        $cases = [[80, 'A+', 5.0], [79, 'A', 4.0], [70, 'A', 4.0], [65, 'A-', 3.5], [55, 'B', 3.0], [45, 'C', 2.0], [33, 'D', 1.0], [32, 'F', 0.0]];
        foreach ($cases as [$marks, $letter, $point]) {
            $grade = Gpa5::grade($marks, 100, 33);
            $this->assertSame([$letter, $point], [$grade['letter'], $grade['point']], "{$marks} marks");
        }
    }

    public function test_grades_use_the_percentage_of_full_marks_and_the_subjects_pass_marks(): void
    {
        $this->assertSame('A+', Gpa5::grade(40, 50, 17)['letter']);   // 80%
        $this->assertSame('F', Gpa5::grade(16, 50, 17)['letter']);    // below the subject's pass marks
        $this->assertSame('F', Gpa5::grade(90, 100, 33, absent: true)['letter']);
    }

    public function test_gpa_averages_compulsory_subjects(): void
    {
        $result = Gpa5::result([$this->subject(85), $this->subject(72), $this->subject(61)]); // 5 + 4 + 3.5

        $this->assertSame('passed', $result['status']);
        $this->assertSame(4.17, $result['gpa']);
        $this->assertSame('A', $result['letter']);
        $this->assertSame(218.0, $result['total']);
    }

    public function test_the_fourth_subject_adds_only_points_above_two_and_gpa_is_capped_at_five(): void
    {
        // 4 + 4 compulsory, 4th subject A+ (5) adds 3 → (8 + 3) / 2 = 5.5 → capped at 5
        $capped = Gpa5::result([$this->subject(75), $this->subject(75), $this->subject(90, fourth: true)]);
        $this->assertSame(5.0, $capped['gpa']);
        $this->assertSame('A+', $capped['letter']);

        // 4th subject C (2) adds nothing
        $noBonus = Gpa5::result([$this->subject(75), $this->subject(65), $this->subject(45, fourth: true)]);
        $this->assertSame(3.75, $noBonus['gpa']);
    }

    public function test_failing_a_compulsory_subject_fails_but_failing_the_fourth_does_not(): void
    {
        $failed = Gpa5::result([$this->subject(95), $this->subject(20)]);
        $this->assertSame(['failed', 0.0, 'F'], [$failed['status'], $failed['gpa'], $failed['letter']]);

        $passed = Gpa5::result([$this->subject(85), $this->subject(85), $this->subject(10, fourth: true)]);
        $this->assertSame(['passed', 5.0], [$passed['status'], $passed['gpa']]);

        $absent = Gpa5::result([$this->subject(95), $this->subject(null, absent: true)]);
        $this->assertSame('failed', $absent['status']);
    }

    public function test_missing_marks_make_the_result_incomplete_not_failed(): void
    {
        $result = Gpa5::result([$this->subject(85), $this->subject(null)]);

        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['gpa']);
        $this->assertSame(1, $result['missing']);
    }

    public function test_merit_puts_passed_first_then_gpa_then_total_with_shared_positions(): void
    {
        $merit = Gpa5::merit([
            'rafi'  => ['status' => 'passed', 'gpa' => 5.0, 'total' => 480],
            'nila'  => ['status' => 'passed', 'gpa' => 5.0, 'total' => 495],
            'tanim' => ['status' => 'passed', 'gpa' => 5.0, 'total' => 480],
            'mitu'  => ['status' => 'failed', 'gpa' => 0.0, 'total' => 500],
            'sumi'  => ['status' => 'incomplete', 'gpa' => null, 'total' => 300],
            'arif'  => ['status' => 'passed', 'gpa' => 4.5, 'total' => 499],
        ]);

        $this->assertSame(['nila' => 1, 'rafi' => 2, 'tanim' => 2, 'arif' => 4, 'mitu' => 5, 'sumi' => null], $merit);
    }

    public function test_model_tests_rank_by_total_marks_only(): void
    {
        $merit = Gpa5::merit([
            'a' => ['status' => 'failed', 'gpa' => 0.0, 'total' => 90],
            'b' => ['status' => 'passed', 'gpa' => 5.0, 'total' => 80],
        ], byTotal: true);

        $this->assertSame(['a' => 1, 'b' => 2], $merit);
    }
}
