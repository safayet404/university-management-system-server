<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['name', 'code', 'class_level_id', 'stream', 'full_marks', 'pass_marks', 'is_optional', 'sort_order'];

    protected $casts = ['is_optional' => 'boolean'];

    public function classLevel() { return $this->belongsTo(ClassLevel::class); }
}
