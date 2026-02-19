<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model {
    /** @var array<string, mixed> */
    protected $attributes = [
        'is_read' => false,
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'related_entity_type',
        'related_entity_id',
        'is_read',
        'action_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function relatedEntity(): MorphTo {
        return $this->morphTo('related_entity');
    }

    /**
     * Scope to only include unread notifications.
     *
     * @param Builder<Notification> $query
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder {
        return $query->where('is_read', false);
    }
}
