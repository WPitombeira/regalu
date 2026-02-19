<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model {
    /** @use HasFactory<\Database\Factories\WishlistItemFactory> */
    use HasFactory;

    protected $fillable = [
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
        'bought_by_user_id',
        'bought_at',
        'notes',
    ];

    protected function casts(): array {
        return [
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'bought_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Wishlist, $this> */
    public function wishlist(): BelongsTo {
        return $this->belongsTo(Wishlist::class);
    }

    /** @return BelongsTo<User, $this> */
    public function boughtBy(): BelongsTo {
        return $this->belongsTo(User::class, 'bought_by_user_id');
    }

    /**
     * Mark this item as bought by the given user.
     */
    public function markAsBought(User $user): void {
        $this->update([
            'status' => 'BOUGHT',
            'bought_by_user_id' => $user->id,
            'bought_at' => now(),
        ]);
    }
}
