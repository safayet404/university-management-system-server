<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Enrollment extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'student_profile_id', 'course_id', 'semester',
        'academic_year', 'status', 'section', 'grade',
        'grade_letter', 'remarks', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'grade'       => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'grade', 'grade_letter'])
            ->logOnlyDirty();
    }

    public function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
