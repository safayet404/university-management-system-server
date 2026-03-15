<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TimetableSlot extends Model
{
    use LogsActivity;

    protected $fillable = [
        'course_id', 'faculty_profile_id', 'department_id',
        'semester', 'academic_year', 'section',
        'day_of_week', 'start_time', 'end_time',
        'room', 'building', 'slot_type', 'color', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['day_of_week', 'start_time', 'room'])->logOnlyDirty();
    }

    public function course()     { return $this->belongsTo(Course::class); }
    public function faculty()    { return $this->belongsTo(FacultyProfile::class, 'faculty_profile_id'); }
    public function department() { return $this->belongsTo(Department::class); }

    public function getDurationAttribute(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end   = \Carbon\Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }
}
