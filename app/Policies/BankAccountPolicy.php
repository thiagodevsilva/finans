<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->account_id === $bankAccount->account_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        if ($user->account_id !== $bankAccount->account_id) {
            return false;
        }

        return $user->isOwner() || $bankAccount->user_id === $user->id;
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->update($user, $bankAccount);
    }
}
