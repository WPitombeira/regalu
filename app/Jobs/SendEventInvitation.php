<?php

namespace App\Jobs;

use App\Mail\AmigoSecretoInviteMail;
use App\Models\AmigoSecretoParticipant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(10, 60, 180)]
class SendEventInvitation implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $participantId) {}

    public function handle(): void
    {
        // Re-read state at delivery time: a queued invitation may have been revoked.
        $participant = AmigoSecretoParticipant::with('event')->find($this->participantId);
        if (! $participant || $participant->status !== 'INVITED' || ! $participant->invite_email) {
            return;
        }

        $event = $participant->event;
        if (! $event || $event->is_archived || in_array($event->status, ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'], true)) {
            return;
        }

        Mail::to($participant->invite_email)->send(new AmigoSecretoInviteMail(
            $event->name,
            route('amigo-secreto.public-join', ['code' => $event->event_code]),
        ));
    }
}
