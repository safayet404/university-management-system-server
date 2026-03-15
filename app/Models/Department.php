<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Department extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'code', 'short_name', 'description',
        'head_name', 'email', 'phone',
        'building', 'room', 'website', 'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'code', 'status'])->logOnlyDirty();
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
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
