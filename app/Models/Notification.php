<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'type', 'title', 'body', 'read_at'])]
class Notification extends Model
{
    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Addresses a message to one user.
     *
     * Takes a User id rather than a Passenger/Driver id on purpose: a
     * notification belongs to the person signing in, and both passengers and
     * drivers read them from the same place.
     */
    public static function send(int $userId, string $type, string $title, string $body): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
