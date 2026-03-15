<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Admission extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'application_number', 'first_name', 'last_name', 'email', 'phone',
        'gender', 'date_of_birth', 'nationality', 'religion', 'blood_group',
        'nid_number', 'present_address', 'permanent_address',
        'ssc_board', 'ssc_year', 'ssc_gpa',
        'hsc_board', 'hsc_year', 'hsc_gpa', 'hsc_group',
        'program_id', 'department_id', 'semester', 'academic_year', 'quota',
        'father_name', 'father_occupation', 'father_phone',
        'mother_name', 'mother_occupation', 'guardian_phone', 'family_income',
        'status', 'remarks', 'rejection_reason', 'merit_score',
        'reviewed_by', 'reviewed_at', 'decided_by', 'decided_at',
        'student_profile_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'reviewed_at'   => 'datetime',
        'decided_at'    => 'datetime',
        'ssc_gpa'       => 'decimal:2',
        'hsc_gpa'       => 'decimal:2',
        'merit_score'   => 'decimal:2',
        'family_income' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'merit_score'])->logOnlyDirty();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function program()        { return $this->belongsTo(Program::class); }
    public function department()     { return $this->belongsTo(Department::class); }
    public function reviewedBy()     { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function decidedBy()      { return $this->belongsTo(User::class, 'decided_by'); }
    public function studentProfile() { return $this->belongsTo(StudentProfile::class); }
}
