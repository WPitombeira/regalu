<?php

namespace App\Models;

use App\Jobs\SendWishlistPurchaseNotice;
use App\Services\NotificationService;
use Database\Factories\WishlistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;

class WishlistItem extends Model
{
    /** @use HasFactory<WishlistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'wishlist_id',
        'name',
        'description',
        'url',
        'image_url',
        'price_min',
        'price_max',
        'currency',
        'category',
        'priority',
        'status',
        'bought_by_user_id',
        'bought_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'bought_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Wishlist, $this> */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /** @return BelongsTo<User, $this> */
    public function boughtBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bought_by_user_id');
    }

    /**
     * Mark this item as bought by the given user.
     */
    public function markAsBought(User $user): bool
    {
        return $this->getConnection()->transaction(fn (): bool => $this->purchaseWithNotice($user));
    }

    private function purchaseWithNotice(User $user): bool
    {
        $wishlist = $this->wishlist()->first();
        if (! $wishlist) {
            return false;
        }

        // Availability and every mutable grant must hold at the same final write.
        // Match ordinary Wishlist::isVisibleTo rules, not Secret Santa assignment consent.
        $updated = static::whereKey($this->id)
            ->where('wishlist_id', $wishlist->id)
            ->where('status', 'AVAILABLE')
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('users')->where('id', $user->id))
            ->whereExists(function (Builder $query) use ($wishlist, $user): void {
                $query->selectRaw('1')->from('wishlists')
                    ->whereColumn('wishlists.id', 'wishlist_items.wishlist_id')
                    ->where('wishlists.user_id', $wishlist->user_id)
                    ->where('wishlists.user_id', '!=', $user->id)
                    ->where('wishlists.is_archived', false)
                    ->where(function (Builder $grants) use ($user): void {
                        $grants->where(function (Builder $specific) use ($user): void {
                            $specific->where('wishlists.privacy', 'SPECIFIC')
                                ->whereExists(fn (Builder $share) => $share->selectRaw('1')->from('wishlist_shares')
                                    ->whereColumn('wishlist_shares.wishlist_id', 'wishlists.id')
                                    ->where('wishlist_shares.shared_with_user_id', $user->id));
                        })->orWhere(function (Builder $family) use ($user): void {
                            $family->where('wishlists.privacy', 'FAMILY')
                                ->whereExists(fn (Builder $members) => $members->selectRaw('1')->from('family_members as buyer_membership')
                                    ->join('family_members as owner_membership', 'owner_membership.family_id', '=', 'buyer_membership.family_id')
                                    ->join('families', 'families.id', '=', 'buyer_membership.family_id')
                                    ->whereColumn('owner_membership.user_id', 'wishlists.user_id')
                                    ->where('buyer_membership.user_id', $user->id)
                                    ->where('families.is_archived', false));
                        });
                    });
            })
            ->update([
                'status' => 'BOUGHT',
                'bought_by_user_id' => $user->id,
                'bought_at' => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->refresh();
        NotificationService::wishlistPurchased($wishlist, $this->id);
        SendWishlistPurchaseNotice::dispatch($this->id, $wishlist->id, $wishlist->user_id)->afterCommit();

        return true;
    }
}
