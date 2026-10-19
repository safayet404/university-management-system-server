<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentEmail extends Model
{
    protected $fillable = [
        'sender_id', 'recipient_email', 'recipient_name', 'recipient_type',
        'recipient_user_id', 'subject', 'body', 'status', 'error',
    ];

    public function sender()    { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient() { return $this->belongsTo(User::class, 'recipient_user_id'); }
}
