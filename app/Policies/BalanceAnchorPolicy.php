<?php

namespace App\Policies;

use App\Models\BalanceAnchor;
use App\Models\User;

class BalanceAnchorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->account_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->account_id !== null;
    }

    public function update(User $user, BalanceAnchor $anchor): bool
    {
        if ($user->account_id !== $anchor->account_id) {
            return false;
        }

        return $user->isOwner() || $anchor->user_id === $user->id;
    }
}
