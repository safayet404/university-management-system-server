<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'sent_by', 'type', 'category',
        'title', 'message', 'action_url', 'action_label',
        'meta', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
        'meta'     => 'array',
    ];

    public function user()   { return $this->belongsTo(User::class); }
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helper to create a notification
    public static function notify(int $userId, string $type, string $category, string $title, string $message, array $options = []): self
    {
        return self::create([
            'user_id'      => $userId,
            'sent_by'      => $options['sent_by'] ?? null,
            'type'         => $type,
            'category'     => $category,
            'title'        => $title,
            'message'      => $message,
            'action_url'   => $options['action_url']   ?? null,
            'action_label' => $options['action_label'] ?? null,
            'meta'         => $options['meta']         ?? null,
        ]);
    }

    // Broadcast to multiple users
    public static function notifyMany(array $userIds, string $type, string $category, string $title, string $message, array $options = []): void
    {
        foreach ($userIds as $userId) {
            self::notify($userId, $type, $category, $title, $message, $options);
        }
    }

    // Broadcast to all users with a role
    public static function notifyRole(string $role, string $type, string $category, string $title, string $message, array $options = []): void
    {
        $userIds = User::role($role)->pluck('id')->toArray();
        self::notifyMany($userIds, $type, $category, $title, $message, $options);
    }
}
