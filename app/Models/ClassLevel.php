<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassLevel extends Model
{
    protected $fillable = ['name', 'code', 'stage', 'sort_order', 'grading', 'has_streams'];

    protected $casts = ['has_streams' => 'boolean'];

    public function subjects() { return $this->hasMany(Subject::class)->orderBy('sort_order'); }
    public function groups()   { return $this->hasMany(StudentGroup::class); }
}
