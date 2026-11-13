<?php

namespace App\Services;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Models\FamilyInvitation;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class NotificationInbox
{
    public function open(User $user, int $notificationId): ?string
    {
        $notice = Notification::where('user_id', $user->id)->find($notificationId);
        if ($notice === null) {
            return null;
        }

        Notification::where('user_id', $user->id)->whereKey($notice->id)->update(['is_read' => true]);

        return $this->destinationFor($notice, $user);
    }

    /** @return LengthAwarePaginator<int, stdClass> */
    public function pageFor(User $user): LengthAwarePaginator
    {
        return $this->queryFor($user)->paginate(15)->through(fn (Notification $notice) => $this->preview($notice, $user));
    }

    /** @return Collection<int, stdClass> */
    public function recentFor(User $user): Collection
    {
        return $this->queryFor($user)->limit(5)->get()->map(fn (Notification $notice) => $this->preview($notice, $user));
    }

    /** @return Builder<Notification> */
    private function queryFor(User $user): Builder
    {
        return Notification::where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id');
    }

    private function preview(Notification $notice, User $user): stdClass
    {
        $destination = $this->destinationFor($notice, $user);
        $known = in_array($notice->kind, ['family_invitation', 'event_invitation', 'assignment_available', 'assignment_revealed', 'wishlist_purchase'], true);
        $available = $notice->kind === null || $destination !== null || $this->invitationAvailable($notice, $user);

        // These are the only attributes delivered to a view; source references and
        // deduplication keys never become Livewire data or browser payloads.
        return (object) [
            'id' => $notice->id,
            'type' => in_array($notice->type, ['family', 'secret_santa', 'wishlist'], true) ? $notice->type : 'system',
            'title' => $known ? __('inbox.'.$notice->kind.'_title') : ($notice->kind === null ? $notice->title : __('inbox.unavailable')),
            'message' => $known ? __('inbox.'.$notice->kind.'_message') : ($notice->kind === null ? $notice->message : __('inbox.unavailable')),
            'is_read' => $notice->is_read,
            'created_at' => $notice->created_at,
            'action_url' => $destination,
            'available' => $available,
        ];
    }

    /** @return Builder<FamilyInvitation> */
    public function familyInvitationsFor(User $user): Builder
    {
        return FamilyInvitation::valid()->forRecipient($user->id)->where('status', 'PENDING')
            ->whereNotExists(DB::table('family_members')->selectRaw('1')
                ->whereColumn('family_members.family_id', 'family_invitations.family_id')->where('user_id', $user->id))
            ->whereNotExists(DB::table('families')->selectRaw('1')
                ->whereColumn('families.id', 'family_invitations.family_id')->where('creator_id', $user->id));
    }

    /** @return Builder<AmigoSecretoParticipant> */
    public function eventInvitationsFor(User $user): Builder
    {
        return AmigoSecretoParticipant::where('status', 'INVITED')->whereNull('user_id')
            ->whereHas('event', fn (Builder $event) => $event->where('is_archived', false)
                ->whereNotIn('status', ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED']))
            ->whereExists(DB::table('users')->selectRaw('1')->where('users.id', $user->id)
                ->whereRaw('LOWER(TRIM(users.email)) = LOWER(TRIM(amigo_secreto_participants.invite_email))'))
            ->whereNotExists(DB::table('users as other')->selectRaw('1')->where('other.id', '!=', $user->id)
                ->whereRaw('LOWER(TRIM(other.email)) = LOWER(TRIM(amigo_secreto_participants.invite_email))'))
            ->whereNotExists(DB::table('amigo_secreto_participants as joined')->selectRaw('1')
                ->whereColumn('joined.event_id', 'amigo_secreto_participants.event_id')
                ->where('joined.user_id', $user->id)->where('joined.status', 'ACCEPTED'));
    }

    private function invitationAvailable(Notification $notice, User $user): bool
    {
        if ($notice->kind === 'family_invitation' && $notice->related_entity_type === (new FamilyInvitation)->getMorphClass()) {
            $invitation = $this->familyInvitationsFor($user)->find($notice->related_entity_id);

            return $invitation !== null && hash_equals($notice->deduplication_key ?? '',
                hash('sha256', 'family_invitation:'.$user->id.':'.$invitation->id.':'.$invitation->generation));
        }
        if ($notice->kind === 'event_invitation' && $notice->related_entity_type === (new AmigoSecretoParticipant)->getMorphClass()) {
            return $this->eventInvitationsFor($user)->whereKey($notice->related_entity_id)->exists();
        }

        return false;
    }

    public function destinationFor(Notification $notice, User $user): ?string
    {
        if ($notice->user_id !== $user->id) {
            return null;
        }

        if ($notice->kind === 'wishlist_purchase' && $notice->related_entity_type === (new Wishlist)->getMorphClass()) {
            $wishlist = Wishlist::whereKey($notice->related_entity_id)->where('user_id', $user->id)
                ->where('is_archived', false)->first(['id']);

            return $wishlist === null ? null : route('wishlists.show', $wishlist);
        }

        if (in_array($notice->kind, ['assignment_available', 'assignment_revealed'], true)
            && $notice->related_entity_type === (new AmigoSecretoEvent)->getMorphClass()) {
            $event = AmigoSecretoEvent::visibleTo($user)->whereKey($notice->related_entity_id)
                ->where('is_archived', false)->whereIn('status', ['DRAWS_COMPLETE', 'REVEALED'])->first();
            if ($event === null) {
                return null;
            }
            $revealDate = $event->reveal_date ?? ($event->recipient_visibility === 'NAMED' ? $event->event_date : null);
            if ($notice->kind === 'assignment_revealed'
                && ($event->status !== 'REVEALED' || $revealDate === null || $revealDate->gt(today()))) {
                return null;
            }
            $drawId = $event->draws()->where('drawer_user_id', $user->id)->value('id');
            $key = hash('sha256', $notice->kind.':'.$user->id.':'.$event->id.':'.$drawId);
            if (! hash_equals($key, $notice->deduplication_key ?? '')
                || app(AssignmentAccess::class)->summaryFor($event, $user) === null) {
                return null;
            }

            return route('amigo-secreto.assignment', $event);
        }

        if ($notice->kind !== null) {
            return null;
        }

        // Legacy stored URLs are data, not authority to redirect or join a group.
        return in_array($notice->action_url, ['/dashboard', '/notifications', '/wishlists', '/families', '/secret-santa'], true)
            ? $notice->action_url : null;
    }
}
