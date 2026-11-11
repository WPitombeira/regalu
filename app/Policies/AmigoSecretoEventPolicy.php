<?php

namespace App\Policies;

use App\Models\AmigoSecretoEvent;
use App\Models\User;

class AmigoSecretoEventPolicy
{
    public function view(User $user, AmigoSecretoEvent $event): bool
    {
        if ($event->is_archived) {
            return false;
        }

        return $this->isOrganizer($user, $event)
            || $event->participants()
                ->where('user_id', $user->id)
                ->where('status', 'ACCEPTED')
                ->exists();
    }

    public function viewHistory(User $user, AmigoSecretoEvent $event): bool
    {
        return AmigoSecretoEvent::historyFor($user)->whereKey($event->id)->exists();
    }

    public function manage(User $user, AmigoSecretoEvent $event): bool
    {
        return ! $event->is_archived && $this->isOrganizer($user, $event);
    }

    public function viewManagement(User $user, AmigoSecretoEvent $event): bool
    {
        return $this->isOrganizer($user, $event);
    }

    public function update(User $user, AmigoSecretoEvent $event): bool
    {
        return $this->manage($user, $event)
            && ! in_array($event->status, ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'], true);
    }

    public function draw(User $user, AmigoSecretoEvent $event): bool
    {
        return $this->manage($user, $event);
    }

    public function join(User $user, AmigoSecretoEvent $event): bool
    {
        return ! $event->is_archived
            && ! in_array($event->status, ['DRAWS_COMPLETE', 'REVEALED', 'COMPLETED'], true);
    }

    public function archive(User $user, AmigoSecretoEvent $event): bool
    {
        return $this->manage($user, $event) && $event->status === 'COMPLETED';
    }

    public function complete(User $user, AmigoSecretoEvent $event): bool
    {
        return AmigoSecretoEvent::completableBy($user)->whereKey($event->id)->exists();
    }

    private function isOrganizer(User $user, AmigoSecretoEvent $event): bool
    {
        return $event->organizer_id === $user->id;
    }
}
