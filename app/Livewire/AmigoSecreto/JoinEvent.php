<?php

namespace App\Livewire\AmigoSecreto;

use App\Models\AmigoSecretoEvent;
use App\Models\AmigoSecretoParticipant;
use App\Services\NotificationInbox;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class JoinEvent extends Component
{
    #[Rule(['required', 'string', 'size:12'])]
    public string $eventCode = '';

    public function mount(?string $code = null): void
    {
        if ($code) {
            $this->eventCode = $code;
        }
    }

    public function join(): Redirector|RedirectResponse|null
    {
        abort_unless(Auth::check(), 403);
        $this->validate();

        $event = AmigoSecretoEvent::where('event_code', strtoupper($this->eventCode))->first();

        if (! $event) {
            $this->addError('eventCode', 'Event not found. Please check the code and try again.');

            return null;
        }

        $alreadyParticipant = $event->participants()
            ->where('user_id', Auth::id())
            ->exists();

        if ($alreadyParticipant) {
            return redirect()->route('amigo-secreto.show', $event);
        }

        Gate::authorize('join', $event);

        $eligibleEvent = AmigoSecretoEvent::whereKey($event->id)
            ->where('event_code', strtoupper($this->eventCode))->where('is_archived', false)
            ->whereNotIn('status', ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'])
            ->whereExists(DB::table('users')->selectRaw('1')->where('id', Auth::id()))
            ->whereNotExists(DB::table('amigo_secreto_participants as existing')->selectRaw('1')
                ->whereColumn('existing.event_id', 'amigo_secreto_events.id')->where('existing.user_id', Auth::id()));

        // Reuse only invitations offered to this account by the dashboard.
        $invitations = app(NotificationInbox::class)->eventInvitationsFor(Auth::user())
            ->where('event_id', $event->id)->whereExists((clone $eligibleEvent)->select('id'));
        $pendingInvite = (clone $invitations)->orderBy('id')->first();

        if ($pendingInvite) {
            // DISTINCT prevents MySQL from merging this derived table back into
            // the UPDATE target. All eligibility checks still run in this write.
            $eligibleInvitation = $invitations->whereKey($pendingInvite->id)->select('id')->distinct();
            $written = AmigoSecretoParticipant::whereKey($pendingInvite->id)
                ->whereIn('id', DB::query()->fromSub($eligibleInvitation, 'eligible_invitation')->select('id'))
                ->update([
                    'user_id' => Auth::id(),
                    'status' => 'ACCEPTED',
                    'accepted_at' => now(),
                ]);
        } else {
            $timestamp = now();
            $eligibleEvent->selectRaw('id, ?, ?, ?, ?, ?', [Auth::id(), 'ACCEPTED', $timestamp, $timestamp, $timestamp]);
            $written = DB::table('amigo_secreto_participants')->insertUsing(
                ['event_id', 'user_id', 'status', 'accepted_at', 'created_at', 'updated_at'],
                $eligibleEvent
            );
        }

        abort_unless($written === 1, 403);

        $this->dispatch('notification', ['message' => 'You have joined the event!', 'type' => 'success']);

        return redirect()->route('amigo-secreto.show', $event);
    }

    public function render(): View
    {
        return view('livewire.amigo-secreto.join-event');
    }
}
