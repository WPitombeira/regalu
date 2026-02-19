<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmigoSecretoExclusion extends Model {
    protected $fillable = [
        'event_id',
        'user_a_id',
        'user_b_id',
        'reason',
    ];

    public function event(): BelongsTo {
        return $this->belongsTo(AmigoSecretoEvent::class, 'event_id');
    }

    public function userA(): BelongsTo {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo {
        return $this->belongsTo(User::class, 'user_b_id');
    }
}
