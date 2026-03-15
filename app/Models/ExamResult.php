<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $fillable = [
        'exam_id', 'student_profile_id', 'marks_obtained',
        'grade_point', 'grade_letter', 'is_absent', 'remarks',
        'entered_by', 'entered_at',
    ];

    protected $casts = [
        'is_absent'    => 'boolean',
        'marks_obtained'=> 'decimal:2',
        'grade_point'  => 'decimal:2',
        'entered_at'   => 'datetime',
    ];

    public function exam()      { return $this->belongsTo(Exam::class); }
    public function student()   { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function enteredBy() { return $this->belongsTo(User::class, 'entered_by'); }

    // Calculate grade from marks
    public static function calculateGrade(float $marks, int $totalMarks): array
    {
        $pct = ($marks / $totalMarks) * 100;
        if ($pct >= 80)      return ['letter' => 'A+', 'point' => 4.00];
        if ($pct >= 75)      return ['letter' => 'A',  'point' => 3.75];
        if ($pct >= 70)      return ['letter' => 'A-', 'point' => 3.50];
        if ($pct >= 65)      return ['letter' => 'B+', 'point' => 3.25];
        if ($pct >= 60)      return ['letter' => 'B',  'point' => 3.00];
        if ($pct >= 55)      return ['letter' => 'B-', 'point' => 2.75];
        if ($pct >= 50)      return ['letter' => 'C+', 'point' => 2.50];
        if ($pct >= 45)      return ['letter' => 'C',  'point' => 2.25];
        if ($pct >= 40)      return ['letter' => 'D',  'point' => 2.00];
        return               ['letter' => 'F',  'point' => 0.00];
    }
}
