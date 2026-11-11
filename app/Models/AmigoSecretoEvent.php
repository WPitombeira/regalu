<?php

namespace App\Models;

use Database\Factories\AmigoSecretoEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AmigoSecretoEvent extends Model
{
    /** @use HasFactory<AmigoSecretoEventFactory> */
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
        'recipient_visibility',
        'status',
        'is_archived',
    ];

    /**
     * @return array{budget_min: 'decimal:2', budget_max: 'decimal:2', event_date: 'date', reveal_date: 'date', is_archived: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'event_date' => 'date',
            'reveal_date' => 'date',
            'is_archived' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AmigoSecretoEvent $event) {
            if (empty($event->event_code)) {
                $event->event_code = Str::upper(Str::random(12));
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /** @return Builder<static> */
    public static function completableBy(User $user): Builder
    {
        return static::query()->where('organizer_id', $user->id)
            ->where('is_archived', false)
            ->where(fn (Builder $query) => $query->whereNull('event_date')->orWhereDate('event_date', '<=', today()))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('recipient_visibility', 'NAMED')->whereIn('status', ['DRAWS_COMPLETE', 'REVEALED']);
                })->orWhere(function (Builder $query): void {
                    $query->where('recipient_visibility', 'ANONYMOUS_UNTIL_REVEAL')
                        ->where('status', 'REVEALED')->whereDate('reveal_date', '<=', today());
                });
            });
    }

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** @return HasMany<AmigoSecretoParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(AmigoSecretoParticipant::class, 'event_id');
    }

    /** @return HasMany<AmigoSecretoDraw, $this> */
    public function draws(): HasMany
    {
        return $this->hasMany(AmigoSecretoDraw::class, 'event_id');
    }

    /** @return HasMany<AmigoSecretoExclusion, $this> */
    public function exclusions(): HasMany
    {
        return $this->hasMany(AmigoSecretoExclusion::class, 'event_id');
    }
}
