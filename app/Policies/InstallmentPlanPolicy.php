<?php

namespace App\Policies;

use App\Models\InstallmentPlan;
use App\Models\User;

class InstallmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InstallmentPlan $plan): bool
    {
        return $user->account_id === $plan->account_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, InstallmentPlan $plan): bool
    {
        if ($user->account_id !== $plan->account_id) {
            return false;
        }

        return $user->isOwner() || $plan->user_id === $user->id;
    }

    public function delete(User $user, InstallmentPlan $plan): bool
    {
        return $this->update($user, $plan);
    }
}
