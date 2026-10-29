<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $fillable = ['student_group_id', 'student_profile_id', 'roll_number', 'optional_subject_id', 'monthly_discount', 'discount_note', 'status', 'joined_on', 'left_on'];

    protected $casts = ['joined_on' => 'date', 'left_on' => 'date', 'monthly_discount' => 'decimal:2'];

    public function group()           { return $this->belongsTo(StudentGroup::class, 'student_group_id'); }
    public function student()         { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function optionalSubject() { return $this->belongsTo(Subject::class, 'optional_subject_id'); }
}
