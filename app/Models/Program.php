<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Program extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'department_id', 'name', 'code', 'degree_type',
        'duration_years', 'total_credits', 'dept_code',
        'description', 'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'code', 'status'])->logOnlyDirty();
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function studentProfiles()
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
