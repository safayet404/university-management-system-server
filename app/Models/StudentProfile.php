<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentProfile extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id', 'department_id', 'program_id',
        'student_id', 'batch', 'semester', 'section', 'shift',
        'admission_type', 'admission_date', 'expected_graduation', 'actual_graduation',
        'academic_status', 'cgpa', 'completed_credits', 'total_credits_required',
        'blood_group', 'nationality', 'religion', 'marital_status',
        'nid_number', 'birth_certificate_no', 'passport_no',
        'father_name', 'father_occupation', 'father_phone',
        'mother_name', 'mother_occupation', 'mother_phone',
        'guardian_name', 'guardian_relation', 'guardian_phone', 'guardian_address',
        'present_address', 'permanent_address',
        'ssc_school', 'ssc_board', 'ssc_year', 'ssc_gpa',
        'hsc_college', 'hsc_board', 'hsc_year', 'hsc_gpa',
        'scholarship_type', 'fee_waiver',
    ];

    protected $casts = [
        'admission_date'       => 'date',
        'expected_graduation'  => 'date',
        'actual_graduation'    => 'date',
        'cgpa'                 => 'decimal:2',
        'ssc_gpa'              => 'decimal:2',
        'hsc_gpa'              => 'decimal:2',
        'fee_waiver'           => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['academic_status', 'cgpa', 'semester', 'department_id', 'program_id'])
            ->logOnlyDirty();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    // Parse student ID to get batch/semester info
    public function getParsedStudentIdAttribute(): array
    {
        if (!$this->student_id) return [];
        $parts = explode('-', $this->student_id);
        if (count($parts) < 3) return [];
        $yys   = $parts[0]; // e.g. 212
        $year  = '20' . substr($yys, 0, 2);
        $sem   = substr($yys, 2, 1);
        return [
            'year'     => $year,
            'semester' => $sem === '1' ? '1st' : '2nd',
            'serial'   => $parts[1],
            'dept_code'=> $parts[2],
        ];
    }

    public function scopeByDepartment($query, $deptId)
    {
        return $query->where('department_id', $deptId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('academic_status', $status);
    }
}
