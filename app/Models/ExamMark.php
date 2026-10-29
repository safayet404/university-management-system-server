<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamMark extends Model
{
    protected $fillable = ['term_exam_id', 'student_profile_id', 'subject_id', 'marks', 'is_absent', 'entered_by'];

    protected $casts = ['marks' => 'float', 'is_absent' => 'boolean'];

    public function exam()    { return $this->belongsTo(TermExam::class, 'term_exam_id'); }
    public function student() { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
}
