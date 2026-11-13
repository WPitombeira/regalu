<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class FamilyInvitation extends Model
{
    protected $fillable = [
        'family_id', 'inviter_id', 'email', 'generation', 'status', 'expires_at', 'responded_at',
    ];

    /** @return array{expires_at: 'immutable_datetime', responded_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** @param Builder<FamilyInvitation> $query */
    public function scopeValid(Builder $query): void
    {
        $query->whereIn('status', ['PENDING', 'ACCEPTED', 'DECLINED'])->where('expires_at', '>', now())
            ->whereExists(DB::table('families')->selectRaw('1')
                ->whereColumn('families.id', 'family_invitations.family_id')
                ->where('families.is_archived', false)
                ->where(function (QueryBuilder $family): void {
                    $family->whereColumn('families.creator_id', 'family_invitations.inviter_id')
                        ->orWhereExists(DB::table('family_members')->selectRaw('1')
                            ->whereColumn('family_members.family_id', 'families.id')
                            ->whereColumn('family_members.user_id', 'family_invitations.inviter_id')
                            ->where('family_members.role', 'ADMIN'));
                }));
    }

    /** @param Builder<FamilyInvitation> $query */
    public function scopeForRecipient(Builder $query, int $userId): void
    {
        // A signed email link is required too. Case-colliding accounts fail closed;
        // matching an unverified email address alone is not an invitation grant.
        $query->whereExists(DB::table('users')->selectRaw('1')->where('users.id', $userId)
            ->whereRaw('LOWER(TRIM(users.email)) = family_invitations.email'))
            ->whereNotExists(DB::table('users as other')->selectRaw('1')->where('other.id', '!=', $userId)
                ->whereRaw('LOWER(TRIM(other.email)) = family_invitations.email'));
    }
}
