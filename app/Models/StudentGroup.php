<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A school section or a coaching batch. */
class StudentGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kind', 'name', 'class_level_id', 'stream', 'academic_year', 'shift', 'class_teacher_id',
        'capacity', 'starts_on', 'ends_on', 'monthly_fee', 'schedule_note', 'is_active',
    ];

    protected $casts = [
        'starts_on'   => 'date',
        'ends_on'     => 'date',
        'monthly_fee' => 'decimal:2',
        'is_active'   => 'boolean',
    ];

    public function classLevel()     { return $this->belongsTo(ClassLevel::class); }
    public function classTeacher()   { return $this->belongsTo(FacultyProfile::class, 'class_teacher_id'); }
    public function members()        { return $this->hasMany(GroupMember::class); }
    public function activeMembers()  { return $this->hasMany(GroupMember::class)->where('status', 'active'); }
    public function groupSubjects()  { return $this->hasMany(GroupSubject::class); }

    /** "Class 6 – A (Morning)" for sections, the batch name for batches. */
    public function getDisplayNameAttribute(): string
    {
        if ($this->kind === 'batch') {
            return $this->name;
        }

        return trim(($this->classLevel?->name ?? 'Class') . ' – ' . $this->name
            . ($this->stream ? ' · ' . ucfirst($this->stream) : '')
            . ($this->shift ? ' (' . ucfirst($this->shift) . ')' : ''));
    }
}
