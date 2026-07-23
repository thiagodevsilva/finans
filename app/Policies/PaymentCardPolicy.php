<?php

namespace App\Policies;

use App\Models\PaymentCard;
use App\Models\User;

class PaymentCardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentCard $paymentCard): bool
    {
        return $user->account_id === $paymentCard->account_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PaymentCard $paymentCard): bool
    {
        if ($user->account_id !== $paymentCard->account_id) {
            return false;
        }

        return $user->isOwner() || $paymentCard->user_id === $user->id;
    }

    public function delete(User $user, PaymentCard $paymentCard): bool
    {
        return $this->update($user, $paymentCard);
    }
}
