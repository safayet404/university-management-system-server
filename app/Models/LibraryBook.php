<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LibraryBook extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'isbn', 'title', 'author', 'publisher', 'edition',
        'publish_year', 'category', 'language', 'department_id',
        'total_copies', 'available_copies', 'price',
        'shelf_location', 'status', 'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'status', 'available_copies'])->logOnlyDirty();
    }

    public function department() { return $this->belongsTo(Department::class); }
    public function issues()     { return $this->hasMany(BookIssue::class); }
}
