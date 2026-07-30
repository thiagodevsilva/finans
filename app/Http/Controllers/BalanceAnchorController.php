<?php

namespace App\Http\Controllers;

use App\Http\Requests\BalanceAnchorRequest;
use App\Models\BalanceAnchor;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BalanceAnchorController extends Controller
{
    public function store(BalanceAnchorRequest $request, BalanceService $balances): RedirectResponse
    {
        $this->authorize('create', BalanceAnchor::class);

        $data = $request->validated();
        $checkinMonth = null;

        if (in_array($data['source'], [
            BalanceAnchor::SOURCE_INITIAL,
            BalanceAnchor::SOURCE_MONTHLY_UPDATE,
            BalanceAnchor::SOURCE_MANUAL,
        ], true)) {
            $checkinMonth = now()->format('Y-m');
        }

        $balances->upsertAnchor(
            $request->user(),
            (float) $data['amount'],
            $data['as_of_date'],
            $data['source'],
            $checkinMonth,
        );

        return back()->with('success', 'Saldo atualizado com sucesso.');
    }

    public function keep(Request $request, BalanceService $balances): RedirectResponse
    {
        $this->authorize('create', BalanceAnchor::class);

        if ($balances->needsInitialAnchor()) {
            return back()->with('error', 'Informe o saldo inicial antes de manter o valor do mês.');
        }

        $balances->keepPreviousMonth($request->user());

        return back()->with('success', 'Saldo do mês anterior mantido.');
    }
}
