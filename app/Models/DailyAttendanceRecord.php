<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAttendanceRecord extends Model
{
    public const STATUSES = ['present', 'absent', 'late', 'leave'];

    protected $fillable = ['daily_attendance_id', 'student_profile_id', 'status', 'remarks'];

    public function attendance() { return $this->belongsTo(DailyAttendance::class, 'daily_attendance_id'); }
    public function student()    { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
}
