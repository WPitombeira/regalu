<?php

namespace App\Jobs;

use App\Mail\AmigoSecretoAssignmentMail;
use App\Models\AmigoSecretoDraw;
use App\Services\AssignmentAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(10, 60, 180)]
class SendAssignment implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $drawId) {}

    public function handle(): void
    {
        // Queue payloads contain only a reference. Resolve privacy and access at delivery.
        $draw = AmigoSecretoDraw::with(['event', 'drawer'])->find($this->drawId);
        $event = $draw?->event;
        $buyer = $draw?->drawer;
        if ($event === null || $buyer === null || Gate::forUser($buyer)->denies('view', $event)) {
            return;
        }

        $assignment = app(AssignmentAccess::class)->summaryFor($event, $buyer);
        if ($assignment === null) {
            return;
        }

        Mail::to($buyer->email)->send(new AmigoSecretoAssignmentMail(
            $buyer->name,
            $assignment['recipient_name'],
            route('amigo-secreto.assignment', $event),
        ));
    }
}
