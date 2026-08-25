<?php

namespace App\Http\Controllers;

use App\Http\Requests\BalanceAnchorRequest;
use App\Models\BalanceAnchor;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\OwnershipResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BalanceAnchorController extends Controller
{
    public function store(BalanceAnchorRequest $request, BalanceService $balances): RedirectResponse
    {
        $this->authorize('create', BalanceAnchor::class);

        $data = $request->validated();
        $member = $this->resolveTargetMember($request->user(), $data['member_id'] ?? null);
        $checkinMonth = null;

        if (in_array($data['source'], [
            BalanceAnchor::SOURCE_INITIAL,
            BalanceAnchor::SOURCE_MONTHLY_UPDATE,
            BalanceAnchor::SOURCE_MANUAL,
        ], true)) {
            $checkinMonth = now()->format('Y-m');
        }

        $balances->upsertAnchor(
            $member,
            (float) $data['amount'],
            $data['as_of_date'],
            $data['source'],
            $checkinMonth,
            $request->user(),
        );

        return back()->with('success', 'Saldo atualizado com sucesso.');
    }

    public function keep(Request $request, BalanceService $balances): RedirectResponse
    {
        $this->authorize('create', BalanceAnchor::class);

        $member = $this->resolveTargetMember($request->user(), $request->input('member_id'));

        if ($balances->needsInitialAnchor($member)) {
            return back()->with('error', 'Informe o saldo inicial antes de manter o valor do mês.');
        }

        $balances->keepPreviousMonth($member, null, $request->user());

        return back()->with('success', 'Saldo do mês anterior mantido.');
    }

    public function dismissStale(Request $request, BalanceService $balances): RedirectResponse
    {
        $this->authorize('create', BalanceAnchor::class);

        $member = $this->resolveTargetMember($request->user(), $request->input('member_id'));
        $balances->dismissStaleRecalc($member);

        return back();
    }

    private function resolveTargetMember(User $actor, mixed $memberId): User
    {
        $ownerId = OwnershipResolver::resolveOwnerId(
            $actor,
            is_string($memberId) && $memberId !== '' ? $memberId : null,
        );

        if ($ownerId === $actor->id) {
            return $actor;
        }

        return User::query()
            ->where('account_id', $actor->account_id)
            ->whereKey($ownerId)
            ->firstOrFail();
    }
}
