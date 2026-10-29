<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A school term exam (per class) or a coaching model test (per batch). */
class TermExam extends Model
{
    public const KINDS = ['half_yearly', 'annual', 'pre_test', 'test', 'class_test', 'model_test', 'weekly_test', 'monthly_test'];

    protected $fillable = [
        'name', 'kind', 'academic_year', 'class_level_id', 'student_group_id', 'full_marks',
        'starts_on', 'ends_on', 'status', 'published_at', 'created_by',
    ];

    protected $casts = [
        'starts_on'    => 'date',
        'ends_on'      => 'date',
        'published_at' => 'datetime',
    ];

    public function classLevel() { return $this->belongsTo(ClassLevel::class); }
    public function batch()      { return $this->belongsTo(StudentGroup::class, 'student_group_id'); }
    public function marks()      { return $this->hasMany(ExamMark::class); }
    public function remarks()    { return $this->hasMany(ExamRemark::class); }

    public function isPublished(): bool { return $this->status === 'published'; }

    /** Sections of the class in that academic year, or the one batch. */
    public function groups()
    {
        if ($this->student_group_id) {
            return StudentGroup::with('classLevel')->whereKey($this->student_group_id)->get();
        }

        return StudentGroup::with('classLevel')
            ->where('kind', 'section')
            ->where('class_level_id', $this->class_level_id)
            ->where('academic_year', $this->academic_year)
            ->orderBy('name')
            ->get();
    }

    public function covers(StudentGroup $group): bool
    {
        return $this->student_group_id
            ? (int) $group->id === (int) $this->student_group_id
            : $group->kind === 'section' && (int) $group->class_level_id === (int) $this->class_level_id && $group->academic_year === $this->academic_year;
    }

    /** Full and pass marks for a subject in this exam. */
    public function marksFor(Subject $subject): array
    {
        if (! $this->full_marks) {
            return [(int) $subject->full_marks, (int) $subject->pass_marks];
        }
        $ratio = $subject->full_marks ? $subject->pass_marks / $subject->full_marks : 0.33;

        return [(int) $this->full_marks, (int) ceil($this->full_marks * $ratio)];
    }
}
