<?php

namespace App\Http\Controllers;

use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Services\BalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BalanceService $balances): Response
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];

        $isCurrentMonth = $start->isSameMonth(now());
        $balanceAt = $isCurrentMonth ? now() : $end->copy();
        $cashBalance = $balances->effectiveBalanceAt($balanceAt);
        $latestAnchor = $balances->latestAnchor($balanceAt);

        $confirmedInMonth = fn () => Transaction::query()
            ->whereBetween('date', $range)
            ->where('status', Transaction::STATUS_CONFIRMED);

        $income = (float) (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_INCOME)
            ->sum('amount');

        $expense = (float) (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_EXPENSE)
            ->sum('amount');

        $investments = (float) (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_INVESTMENT)
            ->sum('amount');

        $expenseCredit = (float) (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('payment_method', Transaction::PAYMENT_CARD)
            ->whereHas('paymentCard', fn ($q) => $q->where('type', PaymentCard::TYPE_CREDIT))
            ->sum('amount');

        $expenseBenefit = (float) (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('payment_method', Transaction::PAYMENT_CARD)
            ->whereHas('paymentCard', fn ($q) => $q->where('type', PaymentCard::TYPE_BENEFIT))
            ->sum('amount');

        // Débito/PIX/dinheiro etc. — exclui crédito e benefício (não saem do caixa do mês).
        $expenseDebit = round($expense - $expenseCredit - $expenseBenefit, 2);

        $recent = Transaction::query()
            ->with([
                'category:id,name,color',
                'user:id,name',
                'paymentCard:id,name,brand,type,last_four,color',
                'bankAccount:id,name,color',
            ])
            ->whereBetween('date', $range)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recurringBase = Transaction::query()
            ->whereNotNull('recurring_bill_id')
            ->whereBetween('date', $range)
            ->whereIn('status', [Transaction::STATUS_PLANNED, Transaction::STATUS_CONFIRMED]);

        $paidAmount = (float) (clone $recurringBase)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->sum('amount');
        $pendingAmount = (float) (clone $recurringBase)
            ->where('status', Transaction::STATUS_PLANNED)
            ->sum('amount');
        $paidCount = (clone $recurringBase)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->count();
        $pendingCount = (clone $recurringBase)
            ->where('status', Transaction::STATUS_PLANNED)
            ->count();
        $totalCount = $paidCount + $pendingCount;
        $totalAmount = $paidAmount + $pendingAmount;
        $paidPercent = $totalAmount > 0 ? (int) round(($paidAmount / $totalAmount) * 100) : 0;

        $previousMonthEnd = now()->copy()->startOfMonth()->subDay()->endOfDay();
        $previousMonthBalance = $balances->needsInitialAnchor()
            ? null
            : $balances->effectiveBalanceAt($previousMonthEnd);

        $staleRecalc = $isCurrentMonth
            ? $balances->staleRecalcMeta()
            : ['needs_stale_recalc' => false, 'suggested_balance' => null];

        return Inertia::render('Dashboard', [
            'summary' => [
                'balance' => $cashBalance,
                // Só saídas de dinheiro do mês (crédito e benefício não entram).
                'month_balance' => round($income - $expenseDebit - $investments, 2),
                'income' => $income,
                'expense' => $expense,
                'expense_credit' => $expenseCredit,
                'expense_debit' => $expenseDebit,
                'investments' => $investments,
            ],
            'balanceMeta' => [
                'has_anchor' => ! $balances->needsInitialAnchor(),
                'needs_initial' => $balances->needsInitialAnchor(),
                'needs_monthly_checkin' => $balances->needsMonthlyCheckin(),
                'as_of_date' => $latestAnchor?->as_of_date?->toDateString(),
                'previous_month_balance' => $previousMonthBalance,
                'needs_stale_recalc' => $staleRecalc['needs_stale_recalc'],
                'suggested_balance' => $staleRecalc['suggested_balance'],
            ],
            'recurringSummary' => [
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'total_amount' => $totalAmount,
                'paid_count' => $paidCount,
                'pending_count' => $pendingCount,
                'total_count' => $totalCount,
                'paid_percent' => $paidPercent,
            ],
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'recentTransactions' => $recent,
        ]);
    }
}
