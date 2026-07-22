<?php

namespace App\Policies;

use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, User $member): bool
    {
        if (! $user->isOwner() || $user->account_id !== $member->account_id) {
            return false;
        }

        if ($member->id === $user->id) {
            return false;
        }

        if ($member->isOwner()) {
            $owners = User::query()
                ->where('account_id', $user->account_id)
                ->where('role', User::ROLE_OWNER)
                ->count();

            if ($owners <= 1) {
                return false;
            }
        }

        return true;
    }
}
