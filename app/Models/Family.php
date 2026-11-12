<?php

namespace App\Models;

use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'invite_code',
        'creator_id',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Family $family) {
            if (empty($family->invite_code)) {
                $family->invite_code = Str::upper(Str::random(12));
            }
        });
    }

    /** @param Builder<Family> $query */
    public function scopeEditableBy(Builder $query, int $userId): void
    {
        // Keep mutable administration grants inside the final database write.
        $query->where('families.is_archived', false)
            ->where(function (Builder $family) use ($userId): void {
                $family->where('families.creator_id', $userId)
                    ->orWhereExists(DB::table('family_members as administrators')->selectRaw('1')
                        ->whereColumn('administrators.family_id', 'families.id')
                        ->where('administrators.user_id', $userId)
                        ->where('administrators.role', 'ADMIN'));
            });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return HasMany<FamilyMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'family_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /** @return HasMany<Wishlist, $this> */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
}
