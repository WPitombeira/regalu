<?php

namespace App\Livewire;

use App\Models\AmigoSecretoEvent;
use App\Models\Family;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\AssignmentAccess;
use App\Services\NotificationInbox;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function markAsRead(int $notificationId): void
    {
        $destination = app(NotificationInbox::class)->open($this->currentUser(), $notificationId);
        if ($destination !== null) {
            $this->redirect($destination);
        }
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function render(): View
    {
        $user = $this->currentUser();
        $inbox = app(NotificationInbox::class);

        return view('livewire.dashboard', [
            'notifications' => $inbox->recentFor($user),
            'familyInvitationCount' => $inbox->familyInvitationsFor($user)->count(),
            'eventInvitationCount' => $inbox->eventInvitationsFor($user)->count(),
            'assignments' => app(AssignmentAccess::class)->activeEventsFor($user)
                ->orderByDesc('id')->limit(4)->get(['id', 'name']),
            'events' => AmigoSecretoEvent::visibleTo($user)->where('is_archived', false)->where('status', '!=', 'COMPLETED')
                ->where(fn (Builder $query) => $query->whereNull('event_date')->orWhereDate('event_date', '>=', today()))
                ->orderByRaw('CASE WHEN event_date IS NULL THEN 1 ELSE 0 END')->orderBy('event_date')->orderBy('id')
                ->limit(4)->get(['id', 'name', 'event_date', 'status']),
            'wishlists' => Wishlist::where('user_id', $user->id)->where('is_archived', false)
                ->orderByDesc('id')->limit(4)->get(['id', 'name']),
            'families' => Family::where('is_archived', false)
                ->where(fn (Builder $query) => $query->where('creator_id', $user->id)
                    ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id)))
                ->orderByDesc('id')->limit(4)->get(['id', 'name']),
        ]);
    }
}
