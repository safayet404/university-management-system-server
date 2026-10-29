<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSubject extends Model
{
    protected $fillable = ['student_group_id', 'subject_id', 'faculty_profile_id'];

    public function group()   { return $this->belongsTo(StudentGroup::class, 'student_group_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(FacultyProfile::class, 'faculty_profile_id'); }
}
