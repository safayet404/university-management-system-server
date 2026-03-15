<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Course extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'department_id', 'program_id', 'faculty_profile_id',
        'name', 'code', 'credit_hours', 'contact_hours',
        'course_type', 'semester_level', 'description',
        'objectives', 'syllabus', 'status', 'max_students',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'status', 'faculty_profile_id'])
            ->logOnlyDirty();
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function faculty()
    {
        return $this->belongsTo(FacultyProfile::class, 'faculty_profile_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getEnrolledCountAttribute(): int
    {
        return $this->enrollments()->where('status', 'approved')->count();
    }
}
