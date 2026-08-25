<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmRecurringTransactionRequest;
use App\Http\Requests\RecurringBillRequest;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Services\OwnershipResolver;
use App\Services\RecurringBillService;
use App\Services\ViewContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecurringBillController extends Controller
{
    public function __construct(
        private readonly RecurringBillService $service
    ) {}

    public function index(ViewContext $view): Response
    {
        $this->authorize('viewAny', RecurringBill::class);

        $user = auth()->user();

        RecurringBill::query()
            ->forViewContext($view)
            ->where('active', true)
            ->get()
            ->each(fn (RecurringBill $bill) => $this->service->materializeAhead($bill, 3));

        $monthSummary = $this->service->summarizeMonth(now(), $view);
        $paidByBill = $monthSummary['paid_by_bill'];

        $bills = RecurringBill::query()
            ->forViewContext($view)
            ->with(['category:id,name,color', 'user:id,name', 'paymentCard:id,name,color', 'bankAccount:id,name,color'])
            ->where('active', true)
            ->orderByRaw('day_of_month is null')
            ->orderBy('day_of_month')
            ->orderBy('description')
            ->get()
            ->map(function (RecurringBill $bill) use ($user, $paidByBill) {
                $monthPaid = (float) ($paidByBill[$bill->id] ?? 0);
                $estimate = (float) $bill->estimated_amount;
                $monthPercent = $estimate > 0
                    ? (int) round(($monthPaid / $estimate) * 100)
                    : 0;

                return [
                    'id' => $bill->id,
                    'description' => $bill->description,
                    'kind' => $bill->kind ?? RecurringBill::KIND_FIXED,
                    'estimated_amount' => $estimate,
                    'day_of_month' => $bill->day_of_month,
                    'payment_method' => $bill->payment_method,
                    'payment_card_id' => $bill->payment_card_id,
                    'payment_card' => $bill->paymentCard,
                    'bank_account_id' => $bill->bank_account_id,
                    'bank_account' => $bill->bankAccount,
                    'category_id' => $bill->category_id,
                    'category' => $bill->category,
                    'start_date' => $bill->start_date->toDateString(),
                    'end_date' => $bill->end_date?->toDateString(),
                    'active' => $bill->active,
                    'user_id' => $bill->user_id,
                    'is_shared' => (bool) $bill->is_shared,
                    'company_id' => $bill->company_id,
                    'user' => $bill->user,
                    'can_edit' => $user->isOwner() || $bill->user_id === $user->id,
                    'month_paid' => $monthPaid,
                    'month_percent' => $monthPercent,
                ];
            });

        $horizonStart = now()->startOfMonth()->toDateString();
        $horizonEnd = now()->addMonthsNoOverflow(2)->endOfMonth()->toDateString();

        $mapScheduleItem = fn (Transaction $tx) => [
            'id' => $tx->id,
            'description' => $tx->description,
            'amount' => (float) $tx->amount,
            'date' => $tx->date->toDateString(),
            'status' => $tx->status,
            'category' => $tx->category,
            'recurring_bill_id' => $tx->recurring_bill_id,
            'can_edit' => $user->isOwner() || $tx->user_id === $user->id,
        ];

        // Só contas fixas (com vencimento) entram em próximos / a pagar.
        $upcoming = Transaction::query()
            ->forViewContext($view)
            ->with(['category:id,name,color', 'recurringBill:id,description,kind'])
            ->whereNotNull('recurring_bill_id')
            ->where('status', Transaction::STATUS_PLANNED)
            ->whereBetween('date', [$horizonStart, $horizonEnd])
            ->whereHas(
                'recurringBill',
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('kind', RecurringBill::KIND_FIXED)
                        ->orWhereNull('kind');
                })
            )
            ->orderBy('date')
            ->get()
            ->map($mapScheduleItem);

        $paid = Transaction::query()
            ->forViewContext($view)
            ->with(['category:id,name,color', 'recurringBill:id,description,kind'])
            ->whereNotNull('recurring_bill_id')
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$horizonStart, $horizonEnd])
            ->orderByDesc('date')
            ->get()
            ->map($mapScheduleItem);

        $monthKey = fn (string $date) => substr($date, 0, 7);

        $sumByMonth = function ($items) use ($monthKey) {
            $totals = [];
            foreach ($items as $item) {
                $key = $monthKey($item['date']);
                $totals[$key] = ($totals[$key] ?? 0) + (float) $item['amount'];
            }

            return $totals;
        };

        $countByMonth = function ($items) use ($monthKey) {
            $counts = [];
            foreach ($items as $item) {
                $key = $monthKey($item['date']);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            return $counts;
        };

        $upcomingAmounts = $sumByMonth($upcoming);
        $paidAmounts = $sumByMonth($paid);
        $upcomingCounts = $countByMonth($upcoming);
        $paidCounts = $countByMonth($paid);

        $currentMonth = now()->format('Y-m');
        $nextMonth = now()->copy()->addMonthNoOverflow()->format('Y-m');

        $periodSummary = [
            'current' => [
                'month' => $currentMonth,
                'pending_count' => $monthSummary['pending_count'],
                'pending_amount' => $monthSummary['pending_amount'],
                'paid_count' => $monthSummary['paid_count'],
                'paid_amount' => $monthSummary['paid_amount'],
                'total_amount' => $monthSummary['total_amount'],
                'paid_percent' => $monthSummary['paid_percent'],
            ],
            'next' => [
                'month' => $nextMonth,
                'pending_count' => $upcomingCounts[$nextMonth] ?? 0,
                'pending_amount' => round($upcomingAmounts[$nextMonth] ?? 0, 2),
                'paid_count' => $paidCounts[$nextMonth] ?? 0,
                'paid_amount' => round($paidAmounts[$nextMonth] ?? 0, 2),
            ],
        ];

        return Inertia::render('RecurringBills/Index', [
            'bills' => $bills,
            'upcoming' => $upcoming,
            'paid' => $paid,
            'periodSummary' => $periodSummary,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'paymentCards' => PaymentCard::query()
                ->forViewContext($view)
                ->orderBy('name')
                ->get(['id', 'name', 'brand', 'type', 'last_four', 'color']),
            'bankAccounts' => BankAccount::query()
                ->forViewContext($view)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
        ]);
    }

    public function store(RecurringBillRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringBill::class);

        $data = $request->validated();
        $ownerId = OwnershipResolver::resolveOwnerId($request->user(), $data['user_id'] ?? null);
        $isShared = (bool) ($data['is_shared'] ?? false);
        $companyId = OwnershipResolver::resolveCompanyId($ownerId, $data['company_id'] ?? null, $isShared);
        unset($data['user_id'], $data['is_shared'], $data['company_id']);

        $this->service->create($request->user(), [
            ...$data,
            'user_id' => $ownerId,
            'is_shared' => $isShared,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Conta fixa cadastrada.');
    }

    public function update(RecurringBillRequest $request, RecurringBill $recurringBill): RedirectResponse
    {
        $this->authorize('update', $recurringBill);

        $data = $request->validated();
        $propagate = $data['propagate'] ?? 'none';
        $propagateFrom = $data['propagate_from'] ?? null;
        unset($data['propagate'], $data['propagate_from'], $data['user_id'], $data['is_shared'], $data['company_id']);

        if (($data['kind'] ?? null) === RecurringBill::KIND_VARIABLE) {
            $data['day_of_month'] = null;
        }

        $wasVariable = $recurringBill->isVariable();
        $recurringBill->update($data);
        $bill = $recurringBill->fresh();

        if ($bill->isVariable()) {
            $this->service->clearPlannedForBill($bill);
        } elseif ($bill->active) {
            $this->service->materializeAhead($bill, 3);
        }

        if (! $wasVariable && ! $bill->isVariable() && in_array($propagate, ['open', 'from_date'], true)) {
            $updated = $this->service->propagateToPlanned(
                $bill,
                $propagate === 'from_date' ? 'from_date' : 'open',
                $propagateFrom
            );

            return back()->with('success', "Conta fixa atualizada. {$updated} lançamento(s) pendente(s) ajustado(s).");
        }

        return back()->with('success', 'Conta fixa atualizada.');
    }

    public function destroy(RecurringBill $recurringBill): RedirectResponse
    {
        $this->authorize('delete', $recurringBill);

        Transaction::query()
            ->where('recurring_bill_id', $recurringBill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->delete();

        $hasHistory = Transaction::query()
            ->where('recurring_bill_id', $recurringBill->id)
            ->whereIn('status', [Transaction::STATUS_CONFIRMED, Transaction::STATUS_SKIPPED])
            ->exists();

        if ($hasHistory) {
            $recurringBill->update(['active' => false]);

            return back()->with('success', 'Conta fixa desativada (já havia pagamentos no histórico).');
        }

        $recurringBill->delete();

        return back()->with('success', 'Conta fixa excluída.');
    }

    public function confirm(
        ConfirmRecurringTransactionRequest $request,
        Transaction $transaction
    ): RedirectResponse {
        $this->authorize('update', $transaction);

        if (! $transaction->recurring_bill_id || $transaction->status !== Transaction::STATUS_PLANNED) {
            return back()->with('error', 'Lançamento inválido para confirmação.');
        }

        $this->service->confirm(
            $transaction,
            (float) $request->validated('amount'),
            $request->validated('date')
        );

        return back()->with('success', 'Conta fixa confirmada.');
    }

    public function skip(Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        if (! $transaction->recurring_bill_id || $transaction->status !== Transaction::STATUS_PLANNED) {
            return back()->with('error', 'Lançamento inválido para pular.');
        }

        $this->service->skip($transaction);

        return back()->with('success', 'Lançamento pulado neste mês.');
    }
}
