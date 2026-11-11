<?php

namespace App\Jobs;

use App\Mail\FamilyInviteMail;
use App\Models\FamilyInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(10, 60, 180)]
class SendFamilyInvitation implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $invitationId, public readonly string $generation) {}

    public function handle(): void
    {
        $invitation = FamilyInvitation::with('family')->valid()->whereKey($this->invitationId)
            ->where('generation', $this->generation)->where('status', 'PENDING')->first();
        if (! $invitation) {
            return;
        }

        Mail::to($invitation->email)->send(new FamilyInviteMail(
            $invitation->family->name,
            URL::temporarySignedRoute('family-invitations.show', $invitation->expires_at, [
                'invitation' => $invitation->id, 'generation' => $invitation->generation,
            ]),
        ));
    }
}
