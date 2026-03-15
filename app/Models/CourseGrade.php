<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseGrade extends Model
{
    protected $fillable = [
        'enrollment_id', 'course_id', 'student_profile_id',
        'semester', 'academic_year', 'total_marks',
        'grade_point', 'grade_letter', 'is_published',
        'published_at', 'published_by', 'remarks',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'total_marks'  => 'decimal:2',
        'grade_point'  => 'decimal:2',
    ];

    public function enrollment() { return $this->belongsTo(Enrollment::class); }
    public function course()     { return $this->belongsTo(Course::class); }
    public function student()    { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function publishedBy(){ return $this->belongsTo(User::class, 'published_by'); }
}
