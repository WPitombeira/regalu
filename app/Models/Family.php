<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Family extends Model {
    /** @use HasFactory<\Database\Factories\FamilyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'invite_code',
        'creator_id',
        'is_archived',
    ];

    protected function casts(): array {
        return [
            'is_archived' => 'boolean',
        ];
    }

    protected static function boot(): void {
        parent::boot();

        static::creating(function (Family $family) {
            if (empty($family->invite_code)) {
                $family->invite_code = Str::upper(Str::random(12));
            }
        });
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): HasMany {
        return $this->hasMany(FamilyMember::class);
    }

    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class, 'family_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function wishlists(): HasMany {
        return $this->hasMany(Wishlist::class);
    }
}
