<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'status',
        'gender',
        'date_of_birth',
        'address',
        'city',
        'country',
        'employee_id',
        'student_id',
        'last_login_at',
        'last_login_ip',
        'is_online',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'date_of_birth'     => 'date',
        'is_online'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ── Activity log config ───────────────────────────────────
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'status', 'role'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "User {$this->name} was {$eventName}");
    }

    // ── Relationships ─────────────────────────────────────────
    public function pageVisits()
    {
        return $this->hasMany(PageVisit::class);
    }
    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function facultyProfile()
    {
        return $this->hasOne(FacultyProfile::class);
    }

    // public function staffProfile()
    // {
    //     return $this->hasOne(StaffProfile::class);
    // }

    // ── Helpers ───────────────────────────────────────────────
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=4F46E5&color=fff&size=128';
    }

    public function getActiveTokensCountAttribute()
    {
        return $this->tokens()->where('expires_at', '>', now())->orWhereNull('expires_at')->count();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByRole($query, $role)
    {
        return $query->role($role);
    }
}
