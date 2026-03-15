<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'course_id', 'created_by', 'title', 'exam_type',
        'semester', 'academic_year', 'exam_date', 'start_time',
        'end_time', 'venue', 'total_marks', 'passing_marks',
        'weightage', 'instructions', 'status',
        'results_published', 'results_published_at',
    ];

    protected $casts = [
        'exam_date'            => 'date',
        'results_published'    => 'boolean',
        'results_published_at' => 'datetime',
        'total_marks'          => 'integer',
        'passing_marks'        => 'integer',
        'weightage'            => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'status', 'results_published'])->logOnlyDirty();
    }

    public function course()    { return $this->belongsTo(Course::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function results()   { return $this->hasMany(ExamResult::class); }
}
