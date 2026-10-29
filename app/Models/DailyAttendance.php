<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAttendance extends Model
{
    protected $fillable = ['student_group_id', 'date', 'taken_by', 'note'];

    protected $casts = ['date' => 'date'];

    public function group()   { return $this->belongsTo(StudentGroup::class, 'student_group_id'); }
    public function takenBy() { return $this->belongsTo(User::class, 'taken_by'); }
    public function records() { return $this->hasMany(DailyAttendanceRecord::class); }
}
