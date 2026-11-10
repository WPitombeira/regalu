<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\Wishlist;
use App\Services\AssignmentAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

class EventDetail extends Component
{
    public AmigoSecretoEvent $event;

    #[Rule(['required', 'in:NAMED,ANONYMOUS_UNTIL_REVEAL'])]
    public string $recipient_visibility = 'NAMED';

    #[Rule(['required_if:recipient_visibility,ANONYMOUS_UNTIL_REVEAL', 'nullable', 'date', 'after_or_equal:today'])]
    public ?string $reveal_date = null;

    public ?int $wishlist_id = null;

    public function mount(AmigoSecretoEvent $event): void
    {
        $this->event = $event;
        $this->event = $this->authorizedEvent();
        $this->recipient_visibility = $this->event->recipient_visibility;
        $this->reveal_date = $this->event->reveal_date?->toDateString();
        $this->wishlist_id = $this->event->participants()->where('user_id', Auth::id())
            ->where('status', 'ACCEPTED')->value('wishlist_id');
    }

    public function savePrivacy(): void
    {
        $event = $this->authorizedEvent();
        Gate::authorize('update', $event);
        $this->validate();

        // The conditional write also rejects a draw committed since authorization.
        $updated = AmigoSecretoEvent::query()->whereKey($event->id)
            ->where('is_archived', false)
            ->whereNotIn('status', ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'])
            ->update([
                'recipient_visibility' => $this->recipient_visibility,
                'reveal_date' => $this->reveal_date ?: null,
            ]);
        abort_unless($updated === 1, 403);
        $this->dispatch('notification', ['message' => __('assignments.saved'), 'type' => 'success']);
    }

    public function saveWishlist(): void
    {
        $event = $this->authorizedEvent();
        $participant = $event->participants()->where('user_id', Auth::id())
            ->where('status', 'ACCEPTED')->firstOrFail();
        $this->validate([
            'wishlist_id' => ['nullable', 'integer', ValidationRule::exists('wishlists', 'id')
                ->where('user_id', Auth::id())->where('is_archived', false)],
        ]);
        $participant->update(['wishlist_id' => $this->wishlist_id]);
        $this->dispatch('notification', ['message' => __('assignments.saved'), 'type' => 'success']);
    }

    /** @return array<int, array{key: string, label: string, completed: bool, current: bool}> */
    private function getStatusSteps(): array
    {
        $statuses = ['PLANNING', 'INVITES_SENT', 'DRAW_PENDING', 'DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'];
        $currentIndex = array_search($this->event->status, $statuses);

        return collect($statuses)->map(function (string $status, int $index) use ($currentIndex) {
            return [
                'key' => $status,
                'label' => __('messages.amigo_secreto.status_'.strtolower($status)),
                'completed' => $index <= $currentIndex,
                'current' => $index === $currentIndex,
            ];
        })->all();
    }

    public function render(): View
    {
        $this->event = $this->authorizedEvent();
        $myAssignment = app(AssignmentAccess::class)->summaryFor($this->event, Auth::user());

        $isOrganizer = $this->event->organizer_id === Auth::id();

        $isParticipant = $this->event->participants()
            ->where('user_id', Auth::id())
            ->where('status', 'ACCEPTED')
            ->exists();

        return view('livewire.amigo-secreto.event-detail', [
            'myAssignment' => $myAssignment,
            'isOrganizer' => $isOrganizer,
            'isParticipant' => $isParticipant,
            'ownWishlists' => Wishlist::query()->where('user_id', Auth::id())
                ->where('is_archived', false)->orderBy('name')->get(['id', 'name']),
            'statusSteps' => $this->getStatusSteps(),
        ]);
    }

    private function authorizedEvent(): AmigoSecretoEvent
    {
        $event = AmigoSecretoEvent::query()->findOrFail($this->event->getKey());
        Gate::authorize('view', $event);
        $event->setRelation('participants', $event->participants()
            ->get(['id', 'event_id', 'status']));

        return $event;
    }
}
