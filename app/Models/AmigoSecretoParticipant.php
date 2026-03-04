<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmigoSecretoParticipant extends Model {
    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'invite_email',
        'invited_at',
        'accepted_at',
    ];

    protected function casts(): array {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo {
        return $this->belongsTo(AmigoSecretoEvent::class, 'event_id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
