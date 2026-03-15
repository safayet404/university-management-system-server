<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryMember extends Model
{
    protected $fillable = [
        'user_id', 'member_id', 'member_type', 'max_books',
        'membership_start', 'membership_end', 'status', 'total_fines',
    ];

    protected $casts = [
        'membership_start' => 'date',
        'membership_end'   => 'date',
    ];

    public function user()   { return $this->belongsTo(User::class); }
    public function issues() { return $this->hasMany(BookIssue::class); }

    public function getCurrentlyIssuedCountAttribute(): int
    {
        return $this->issues()->where('status', 'issued')->count();
    }
}
