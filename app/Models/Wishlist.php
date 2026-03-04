<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wishlist extends Model {
    /** @use HasFactory<\Database\Factories\WishlistFactory> */
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

    protected function casts(): array {
        return [
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo {
        return $this->belongsTo(Family::class);
    }

    /** @return HasMany<WishlistItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(WishlistItem::class);
    }

    /** @return HasMany<WishlistShare, $this> */
    public function shares(): HasMany {
        return $this->hasMany(WishlistShare::class);
    }

    /**
     * Determine if the given user can see this wishlist.
     */
    public function isVisibleTo(User $user): bool {
        // Owner can always see their own wishlist
        if ($this->user_id === $user->id) {
            return true;
        }

        // Family privacy: user must share a family with the owner
        if ($this->privacy === 'FAMILY') {
            $ownerFamilyIds = FamilyMember::where('user_id', $this->user_id)->pluck('family_id');
            $userFamilyIds = FamilyMember::where('user_id', $user->id)->pluck('family_id');

            return $ownerFamilyIds->intersect($userFamilyIds)->isNotEmpty();
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
     * Owners see all items; non-owners see items but bought_by info is hidden at the view layer.
     */
    /** @return Builder<WishlistItem> */
    public function visibleItemsFor(User $user): Builder {
        if ($this->user_id === $user->id) {
            return $this->items()->getQuery();
        }

        // Non-owners get all items but without bought_by_user_id exposed
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
