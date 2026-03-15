<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FacultyProfile extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'department_id',
        'employee_id',
        'designation',
        'employment_type',
        'specialization',
        'research_interests',
        'joining_date',
        'employment_status',
        'highest_degree',
        'phd_institution',
        'phd_year',
        'masters_institution',
        'masters_year',
        'bachelors_institution',
        'bachelors_year',
        'office_room',
        'office_phone',
        'personal_email',
        'website',
        'linkedin',
        'google_scholar',
        'orcid',
        'publications_count',
        'citations_count',
        'h_index',
        'blood_group',
        'nationality',
        'religion',
        'nid_number',
        'present_address',
        'permanent_address',
    ];

    protected $casts = [
        'joining_date'       => 'date',
        'publications_count' => 'integer',
        'citations_count'    => 'integer',
        'h_index'            => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['designation', 'department_id', 'employment_status', 'specialization'])
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

    public function scopeByDepartment($query, $deptId)
    {
        return $query->where('department_id', $deptId);
    }

    public function scopeByDesignation($query, $designation)
    {
        return $query->where('designation', $designation);
    }

    public function scopeActive($query)
    {
        return $query->where('employment_status', 'active');
    }
}
