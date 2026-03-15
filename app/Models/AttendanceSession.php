<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AttendanceSession extends Model
{
    use LogsActivity;

    protected $fillable = [
        'course_id', 'faculty_profile_id', 'taken_by',
        'date', 'semester', 'academic_year', 'section',
        'start_time', 'end_time', 'topic', 'notes', 'is_finalized',
    ];

    protected $casts = [
        'date'         => 'date',
        'is_finalized' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['date', 'course_id', 'is_finalized'])->logOnlyDirty();
    }

    public function course()       { return $this->belongsTo(Course::class); }
    public function faculty()      { return $this->belongsTo(FacultyProfile::class, 'faculty_profile_id'); }
    public function takenBy()      { return $this->belongsTo(User::class, 'taken_by'); }
    public function records()      { return $this->hasMany(AttendanceRecord::class); }

    public function getPresentCountAttribute(): int
    {
        return $this->records()->where('status', 'present')->count();
    }

    public function getAbsentCountAttribute(): int
    {
        return $this->records()->where('status', 'absent')->count();
    }
}
