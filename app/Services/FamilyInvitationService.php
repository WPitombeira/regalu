<?php

namespace App\Services;

use App\Jobs\SendFamilyInvitation;
use App\Models\Family;
use App\Models\FamilyInvitation;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyInvitationService
{
    public function invite(Family $family, User $sender, string $email): void
    {
        Gate::forUser($sender)->authorize('update', $family->fresh());
        $this->reserveSend($sender->id);

        DB::transaction(function () use ($family, $sender, $email): void {
            $existing = FamilyInvitation::where('family_id', $family->id)->where('email', $email)->first();
            $eligible = $this->manageableFamily($family->id, $sender->id)
                ->whereNotExists(DB::table('family_members')->join('users', 'users.id', '=', 'family_members.user_id')
                    ->selectRaw('1')->whereColumn('family_members.family_id', 'families.id')
                    ->whereRaw('LOWER(TRIM(users.email)) = ?', [$email]))
                ->whereNotExists(DB::table('users')->selectRaw('1')->whereColumn('users.id', 'families.creator_id')
                    ->whereRaw('LOWER(TRIM(users.email)) = ?', [$email]));
            $generation = (string) Str::uuid();
            $values = [
                'inviter_id' => $sender->id, 'generation' => $generation,
                'status' => 'PENDING', 'expires_at' => now()->addDays(7),
                'responded_at' => null, 'updated_at' => now(),
            ];
            if ($existing) {
                $changed = FamilyInvitation::whereKey($existing->id)->where('generation', $existing->generation)
                    ->where(fn (Builder $query) => $query->where('status', '!=', 'PENDING')->orWhere('expires_at', '<=', now()))
                    ->whereExists($eligible->selectRaw('1'))->update($values);
            } else {
                $values = ['family_id' => $family->id, 'email' => $email, ...$values, 'created_at' => now()];
                // The grant and absence of an existing membership are part of the INSERT,
                // not a permission read that can become stale before the write.
                $changed = DB::table('family_invitations')->insertOrIgnoreUsing(array_keys($values),
                    $eligible->selectRaw(implode(', ', array_fill(0, count($values), '?')), array_values($values)));
            }
            if ($changed !== 1) {
                Gate::forUser($sender)->authorize('update', $family->fresh());
                throw ValidationException::withMessages(['inviteEmail' => __('family_invitations.already_invited')]);
            }
            $invitation = FamilyInvitation::where('family_id', $family->id)->where('email', $email)->sole();
            NotificationService::familyInvited($invitation);
            SendFamilyInvitation::dispatch($invitation->id, $generation)->afterCommit();
        });
    }

    public function cancel(Family $family, User $sender, int $invitationId): bool
    {
        Gate::forUser($sender)->authorize('update', $family->fresh());
        $invitation = FamilyInvitation::where('family_id', $family->id)->findOrFail($invitationId);
        $changed = FamilyInvitation::whereKey($invitation->id)->where('generation', $invitation->generation)->where('status', 'PENDING')
            ->whereExists($this->manageableFamily($family->id, $sender->id)->selectRaw('1'))
            ->update(['status' => 'CANCELLED', 'responded_at' => now()]);
        Gate::forUser($sender)->authorize('update', $family->fresh());

        return $changed === 1;
    }

    private function manageableFamily(int $familyId, int $senderId): QueryBuilder
    {
        return DB::table('families')->where('families.id', $familyId)->where('is_archived', false)
            ->where(function (QueryBuilder $query) use ($senderId): void {
                $query->where('creator_id', $senderId)
                    ->orWhereExists(DB::table('family_members')->selectRaw('1')
                        ->whereColumn('family_members.family_id', 'families.id')
                        ->where('family_members.user_id', $senderId)->where('family_members.role', 'ADMIN'));
            });
    }

    private function reserveSend(int $senderId): void
    {
        $storeName = config('cache.limiter');
        $cache = Cache::store(is_string($storeName) ? $storeName : null);
        $store = $cache->getStore();
        $key = 'family-invitations:sender:'.$senderId;
        $reserved = $store instanceof LockProvider
            && $store->lock($key.':reservation', 10)->get(fn () => (new RateLimiter($cache))->hit($key, 3600) <= 20) === true;
        if (! $reserved) {
            throw ValidationException::withMessages(['inviteEmail' => __('family_invitations.rate_limited')]);
        }
    }
}
