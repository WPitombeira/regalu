<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
     * @var list<string>
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
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Family, $this> */
    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class, 'family_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /** @return HasMany<FamilyMember, $this> */
    public function familyMemberships(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /** @return HasMany<Wishlist, $this> */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /** @return HasMany<AmigoSecretoEvent, $this> */
    public function organizedEvents(): HasMany
    {
        return $this->hasMany(AmigoSecretoEvent::class, 'organizer_id');
    }

    /** @return HasMany<AmigoSecretoParticipant, $this> */
    public function eventParticipations(): HasMany
    {
        return $this->hasMany(AmigoSecretoParticipant::class);
    }

    /** @return HasMany<Notification, $this> */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
