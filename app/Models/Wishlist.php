<?php

namespace App\Models;

use Database\Factories\WishlistFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

class Wishlist extends Model
{
    /** @use HasFactory<WishlistFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'family_id',
        'name',
        'description',
        'type',
        'privacy',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** @return HasMany<WishlistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /** @return HasMany<WishlistShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(WishlistShare::class);
    }

    /**
     * Determine if the given user can see this wishlist.
     */
    public function isVisibleTo(User $user): bool
    {
        // Owner can always see their own wishlist
        if ($this->user_id === $user->id) {
            return true;
        }

        // Family privacy: user must share a family with the owner
        if ($this->privacy === 'FAMILY') {
            return FamilyMember::where('user_id', $user->id)
                ->whereIn('family_id', FamilyMember::select('family_id')->where('user_id', $this->user_id))
                ->exists();
        }

        // Specific privacy: user must be in the shares list
        if ($this->privacy === 'SPECIFIC') {
            return $this->shares()->where('shared_with_user_id', $user->id)->exists();
        }

        // Private wishlists are only visible to the owner (handled above)
        return false;
    }

    /**
     * Return items query scoped for the given user.
     * Buyer identity is omitted from the query, not merely hidden in Blade.
     */
    /** @return Builder<WishlistItem> */
    public function visibleItemsFor(User $user): Builder
    {
        Gate::forUser($user)->authorize('view', $this);

        // Never disclose gift-giver identities in a listing payload.
        return $this->items()
            ->select([
                'id',
                'wishlist_id',
                'name',
                'description',
                'url',
                'image_url',
                'price_min',
                'price_max',
                'category',
                'priority',
                'status',
                'bought_at',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->getQuery();
    }
}
