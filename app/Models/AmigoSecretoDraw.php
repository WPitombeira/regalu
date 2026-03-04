<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmigoSecretoDraw extends Model {
    protected $fillable = [
        'event_id',
        'drawer_user_id',
        'target_user_id',
        'draw_date',
        'revealed_at',
    ];

    protected function casts(): array {
        return [
            'draw_date' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo {
        return $this->belongsTo(AmigoSecretoEvent::class, 'event_id');
    }

    public function drawer(): BelongsTo {
        return $this->belongsTo(User::class, 'drawer_user_id');
    }

    public function target(): BelongsTo {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
