<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'phone',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'password' => 'hashed',
        ];
    }

    public function families(): BelongsToMany {
        return $this->belongsToMany(\App\Models\Family::class, 'family_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function familyMemberships(): HasMany {
        return $this->hasMany(\App\Models\FamilyMember::class);
    }

    public function wishlists(): HasMany {
        return $this->hasMany(\App\Models\Wishlist::class);
    }

    public function organizedEvents(): HasMany {
        return $this->hasMany(\App\Models\AmigoSecretoEvent::class, 'organizer_id');
    }

    public function eventParticipations(): HasMany {
        return $this->hasMany(\App\Models\AmigoSecretoParticipant::class);
    }

    public function userNotifications(): HasMany {
        return $this->hasMany(\App\Models\Notification::class);
    }
}
