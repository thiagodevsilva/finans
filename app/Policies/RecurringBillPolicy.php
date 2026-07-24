<?php

namespace App\Policies;

use App\Models\RecurringBill;
use App\Models\User;

class RecurringBillPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RecurringBill $bill): bool
    {
        return $user->account_id === $bill->account_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RecurringBill $bill): bool
    {
        if ($user->account_id !== $bill->account_id) {
            return false;
        }

        return $user->isOwner() || $bill->user_id === $user->id;
    }

    public function delete(User $user, RecurringBill $bill): bool
    {
        return $this->update($user, $bill);
    }
}
