<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistShare extends Model {
    protected $fillable = [
        'wishlist_id',
        'shared_with_user_id',
        'access_level',
    ];

    /** @return BelongsTo<Wishlist, $this> */
    public function wishlist(): BelongsTo {
        return $this->belongsTo(Wishlist::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sharedWithUser(): BelongsTo {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }
}
