<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;

class FamilyPolicy
{
    public function view(User $user, Family $family): bool
    {
        return $family->creator_id === $user->id
            || FamilyMember::query()
                ->where('family_id', $family->id)
                ->where('user_id', $user->id)
                ->exists();
    }

    public function manage(User $user, Family $family): bool
    {
        return $family->creator_id === $user->id
            || FamilyMember::query()
                ->where('family_id', $family->id)
                ->where('user_id', $user->id)
                ->where('role', 'ADMIN')
                ->exists();
    }

    public function update(User $user, Family $family): bool
    {
        return ! $family->is_archived && $this->manage($user, $family);
    }

    public function archive(User $user, Family $family): bool
    {
        return $this->update($user, $family);
    }

    public function join(User $user, Family $family): bool
    {
        return ! $family->is_archived;
    }
}
