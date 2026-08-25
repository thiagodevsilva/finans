<?php

namespace App\Services;

use App\Models\BalanceAnchor;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OwnershipResolver
{
    /**
     * Resolve o dono do registro: owner pode escolher outro membro da conta.
     */
    public static function resolveOwnerId(User $actor, ?string $requestedUserId): string
    {
        if (! $requestedUserId || $requestedUserId === $actor->id) {
            return $actor->id;
        }

        if (! $actor->isOwner()) {
            throw ValidationException::withMessages([
                'user_id' => 'Apenas o dono da conta pode lançar em nome de outro membro.',
            ]);
        }

        $exists = User::query()
            ->where('account_id', $actor->account_id)
            ->whereKey($requestedUserId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'user_id' => 'Membro inválido.',
            ]);
        }

        return $requestedUserId;
    }

    public static function resolveCompanyId(string $ownerUserId, ?string $companyId, bool $isShared = false): ?string
    {
        if ($isShared || ! $companyId) {
            return null;
        }

        $exists = Company::query()
            ->whereKey($companyId)
            ->where('user_id', $ownerUserId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'company_id' => 'CNPJ inválido para este membro.',
            ]);
        }

        return $companyId;
    }
}
