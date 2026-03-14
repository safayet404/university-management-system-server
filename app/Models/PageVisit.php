<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    protected $fillable = [
        'user_id', 'module', 'section', 'action',
        'url', 'method', 'description',
        'old_values', 'new_values',
        'model_type', 'model_id',
        'ip_address', 'user_agent', 'browser', 'device',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeForModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }
}
