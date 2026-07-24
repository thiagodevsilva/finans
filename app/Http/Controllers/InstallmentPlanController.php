<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallmentPlanRequest;
use App\Models\InstallmentPlan;
use App\Models\Transaction;
use App\Services\InstallmentPlanService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InstallmentPlanController extends Controller
{
    public function __construct(
        private readonly InstallmentPlanService $service
    ) {}

    public function create(): RedirectResponse
    {
        $this->authorize('create', InstallmentPlan::class);

        return redirect()->route('transactions.create');
    }

    public function store(InstallmentPlanRequest $request): RedirectResponse
    {
        $this->authorize('create', InstallmentPlan::class);

        $plan = $this->service->create($request->user(), $request->validated());

        return redirect()
            ->route('installment-plans.show', $plan)
            ->with('success', 'Compra parcelada cadastrada.');
    }

    public function show(InstallmentPlan $installmentPlan): Response
    {
        $this->authorize('view', $installmentPlan);

        $installmentPlan->load([
            'category:id,name,color',
            'paymentCard:id,name,brand,type,last_four,color',
            'user:id,name',
            'installments' => fn ($q) => $q->orderBy('installment_number'),
        ]);

        $paidCount = $installmentPlan->installments
            ->filter(fn (Transaction $tx) => $tx->date->lte(now()->endOfDay()))
            ->count();

        return Inertia::render('Installments/Show', [
            'plan' => [
                'id' => $installmentPlan->id,
                'description' => $installmentPlan->description,
                'total_amount' => (float) $installmentPlan->total_amount,
                'installments_count' => $installmentPlan->installments_count,
                'purchase_date' => $installmentPlan->purchase_date->toDateString(),
                'first_installment_date' => $installmentPlan->first_installment_date->toDateString(),
                'status' => $installmentPlan->status,
                'category' => $installmentPlan->category,
                'payment_card' => $installmentPlan->paymentCard,
                'user' => $installmentPlan->user,
                'paid_count' => $paidCount,
                'remaining_amount' => (float) $installmentPlan->installments
                    ->filter(fn (Transaction $tx) => $tx->date->gt(now()->endOfDay()))
                    ->sum('amount'),
                'can_edit' => auth()->user()->isOwner() || $installmentPlan->user_id === auth()->id(),
                'installments' => $installmentPlan->installments->map(fn (Transaction $tx) => [
                    'id' => $tx->id,
                    'installment_number' => $tx->installment_number,
                    'amount' => (float) $tx->amount,
                    'date' => $tx->date->toDateString(),
                    'description' => $tx->description,
                ]),
            ],
        ]);
    }

    public function destroy(InstallmentPlan $installmentPlan): RedirectResponse
    {
        $this->authorize('delete', $installmentPlan);

        $this->service->cancelFuture($installmentPlan);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Parcelas futuras canceladas.');
    }
}
