<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ViewContext
{
    public const SCOPE_FAMILY = 'family';

    public const SCOPE_MINE = 'mine';

    public const SCOPE_MEMBER = 'member';

    public const COMPANY_ALL = 'all';

    public const COMPANY_NONE = 'none';

    public const SESSION_SCOPE = 'view_scope';

    public const SESSION_MEMBER_ID = 'view_member_id';

    public const SESSION_COMPANY = 'view_company';

    public function __construct(
        public readonly string $scope,
        public readonly ?string $memberId,
        public readonly string $companyFilter,
        public readonly User $viewer,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->account_id) {
            return new self(self::SCOPE_FAMILY, null, self::COMPANY_ALL, $viewer ?? new User);
        }

        $scope = $request->session()->get(self::SESSION_SCOPE, self::SCOPE_FAMILY);
        $memberId = $request->session()->get(self::SESSION_MEMBER_ID);
        $company = $request->session()->get(self::SESSION_COMPANY, self::COMPANY_ALL);

        if (! in_array($scope, [self::SCOPE_FAMILY, self::SCOPE_MINE, self::SCOPE_MEMBER], true)) {
            $scope = self::SCOPE_FAMILY;
        }

        if ($scope === self::SCOPE_MEMBER) {
            if (! $viewer->isOwner() || ! $memberId) {
                $scope = self::SCOPE_FAMILY;
                $memberId = null;
            } else {
                $exists = User::query()
                    ->where('account_id', $viewer->account_id)
                    ->whereKey($memberId)
                    ->exists();

                if (! $exists) {
                    $scope = self::SCOPE_FAMILY;
                    $memberId = null;
                }
            }
        }

        if ($scope === self::SCOPE_MINE) {
            $memberId = $viewer->id;
        }

        if ($scope === self::SCOPE_FAMILY) {
            $memberId = null;
            $company = self::COMPANY_ALL;
        }

        if (! is_string($company) || $company === '') {
            $company = self::COMPANY_ALL;
        }

        return new self($scope, $memberId, $company, $viewer);
    }

    public function isFamily(): bool
    {
        return $this->scope === self::SCOPE_FAMILY;
    }

    public function isPersonal(): bool
    {
        return ! $this->isFamily();
    }

    /**
     * Membro cujo caixa/listagens pessoais se aplicam (null na visão família).
     */
    public function focusMemberId(): ?string
    {
        if ($this->isFamily()) {
            return null;
        }

        return $this->memberId ?? $this->viewer->id;
    }

    public function focusMember(): ?User
    {
        $id = $this->focusMemberId();

        if (! $id) {
            return null;
        }

        if ($id === $this->viewer->id) {
            return $this->viewer;
        }

        return User::query()
            ->where('account_id', $this->viewer->account_id)
            ->whereKey($id)
            ->first();
    }

    /**
     * Filtra registros com dono (user_id) + shared + company conforme o contexto.
     *
     * @param  bool  $hasShared  Model possui coluna is_shared
     * @param  bool  $hasCompany  Model possui coluna company_id
     */
    public function applyToQuery(
        Builder $query,
        bool $hasShared = true,
        bool $hasCompany = true,
        string $userColumn = 'user_id',
    ): Builder {
        $table = $query->getModel()->getTable();

        if ($this->isFamily()) {
            return $query;
        }

        $memberId = $this->focusMemberId();
        $query->where($table.'.'.$userColumn, $memberId);

        if ($hasShared) {
            $query->where($table.'.is_shared', false);
        }

        if ($hasCompany) {
            $this->applyCompanyFilter($query, $table.'.company_id');
        }

        return $query;
    }

    public function applyCompanyFilter(Builder $query, string $column = 'company_id'): Builder
    {
        if ($this->isFamily() || $this->companyFilter === self::COMPANY_ALL) {
            return $query;
        }

        if ($this->companyFilter === self::COMPANY_NONE) {
            return $query->whereNull($column);
        }

        return $query->where($column, $this->companyFilter);
    }

    /**
     * @return array{scope: string, member_id: string|null, company: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'member_id' => $this->memberId,
            'company' => $this->companyFilter,
            'label' => $this->label(),
        ];
    }

    public function label(): string
    {
        return match ($this->scope) {
            self::SCOPE_MINE => 'Eu',
            self::SCOPE_MEMBER => $this->focusMember()?->name ?? 'Membro',
            default => 'Família',
        };
    }

    public static function store(
        Request $request,
        string $scope,
        ?string $memberId = null,
        string $company = self::COMPANY_ALL,
    ): void {
        $viewer = $request->user();

        if (! in_array($scope, [self::SCOPE_FAMILY, self::SCOPE_MINE, self::SCOPE_MEMBER], true)) {
            $scope = self::SCOPE_FAMILY;
        }

        if ($scope === self::SCOPE_MEMBER) {
            if (! $viewer->isOwner() || ! $memberId) {
                $scope = self::SCOPE_FAMILY;
                $memberId = null;
            }
        }

        if ($scope !== self::SCOPE_MEMBER) {
            $memberId = null;
        }

        if ($scope === self::SCOPE_FAMILY) {
            $company = self::COMPANY_ALL;
        }

        $request->session()->put([
            self::SESSION_SCOPE => $scope,
            self::SESSION_MEMBER_ID => $memberId,
            self::SESSION_COMPANY => $company,
        ]);
    }
}
