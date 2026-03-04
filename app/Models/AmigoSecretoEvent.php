<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AmigoSecretoEvent extends Model {
    /** @use HasFactory<\Database\Factories\AmigoSecretoEventFactory> */
    use HasFactory;

    protected $fillable = [
        'organizer_id',
        'family_id',
        'name',
        'description',
        'event_code',
        'event_type',
        'budget_min',
        'budget_max',
        'event_date',
        'reveal_date',
        'status',
        'is_archived',
    ];

    protected function casts(): array {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'event_date' => 'date',
            'reveal_date' => 'date',
            'is_archived' => 'boolean',
        ];
    }

    protected static function boot(): void {
        parent::boot();

        static::creating(function (AmigoSecretoEvent $event) {
            if (empty($event->event_code)) {
                $event->event_code = Str::upper(Str::random(12));
            }
        });
    }

    public function organizer(): BelongsTo {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function family(): BelongsTo {
        return $this->belongsTo(Family::class);
    }

    public function participants(): HasMany {
        return $this->hasMany(AmigoSecretoParticipant::class, 'event_id');
    }

    public function draws(): HasMany {
        return $this->hasMany(AmigoSecretoDraw::class, 'event_id');
    }

    public function exclusions(): HasMany {
        return $this->hasMany(AmigoSecretoExclusion::class, 'event_id');
    }
}
