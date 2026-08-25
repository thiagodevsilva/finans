<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->account_id;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->account_id === $company->account_id
            && ($user->isOwner() || $company->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return (bool) $user->account_id;
    }

    public function update(User $user, Company $company): bool
    {
        return $this->view($user, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->view($user, $company);
    }
}
