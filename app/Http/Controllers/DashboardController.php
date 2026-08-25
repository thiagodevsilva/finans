<?php

namespace App\Http\Controllers;

use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Services\BalanceService;
use App\Services\RecurringBillService;
use App\Services\ReportChartService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        BalanceService $balances,
        ReportChartService $charts,
        RecurringBillService $recurringBills,
    ): Response
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
            ->spendGroup(Transaction::SPEND_GROUP_CREDIT)
            ->sum('amount');

        $expenseDebit = (float) (clone $confirmedInMonth())
            ->spendGroup(Transaction::SPEND_GROUP_DEBIT)
            ->sum('amount');

        // Saídas de caixa do mês (inclui contas fixas à vista) — base do saldo do mês.
        $cashExpense = (float) (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where(function ($q) {
                $q->whereNull('payment_method')
                    ->orWhereIn('payment_method', [
                        Transaction::PAYMENT_CASH,
                        Transaction::PAYMENT_PIX,
                        Transaction::PAYMENT_TRANSFER,
                        Transaction::PAYMENT_DEBIT,
                        Transaction::PAYMENT_AUTO_DEBIT,
                    ])
                    ->orWhere(function ($debitCard) {
                        $debitCard->where('payment_method', Transaction::PAYMENT_CARD)
                            ->whereHas(
                                'paymentCard',
                                fn ($card) => $card->where('type', PaymentCard::TYPE_DEBIT)
                            );
                    });
            })
            ->sum('amount');

        $cardPayments = $charts->cardPaymentsTotal($start->toDateString(), $end->toDateString());

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

        $recurringSummary = $recurringBills->summarizeMonth($start);

        $previousMonthEnd = now()->copy()->startOfMonth()->subDay()->endOfDay();
        $previousMonthBalance = $balances->needsInitialAnchor()
            ? null
            : $balances->effectiveBalanceAt($previousMonthEnd);

        $staleRecalc = $isCurrentMonth
            ? $balances->staleRecalcMeta(null, $cashBalance)
            : ['needs_stale_recalc' => false, 'suggested_balance' => null, 'stale_recalc_mode' => null];

        $pinnedChartId = $request->user()->pinned_dashboard_chart;
        $pinnedChart = $charts->isValidChartId($pinnedChartId)
            ? $charts->build($pinnedChartId, $month, $year)
            : null;

        return Inertia::render('Dashboard', [
            'summary' => [
                'balance' => $cashBalance,
                // Entradas − saídas de caixa (inclui contas fixas à vista) − investimentos.
                'month_balance' => round($income - $cashExpense - $investments, 2),
                'income' => $income,
                'expense' => $expense,
                'expense_credit' => $expenseCredit,
                'expense_debit' => $expenseDebit,
                'expense_spend' => round($expenseCredit + $expenseDebit, 2),
                'card_payments' => $cardPayments,
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
                'stale_recalc_mode' => $staleRecalc['stale_recalc_mode'] ?? null,
            ],
            'recurringSummary' => [
                'paid_amount' => $recurringSummary['paid_amount'],
                'pending_amount' => $recurringSummary['pending_amount'],
                'total_amount' => $recurringSummary['total_amount'],
                'paid_count' => $recurringSummary['paid_count'],
                'pending_count' => $recurringSummary['pending_count'],
                'total_count' => $recurringSummary['total_count'],
                'paid_percent' => $recurringSummary['paid_percent'],
            ],
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'recentTransactions' => $recent,
            'pinnedChart' => $pinnedChart,
        ]);
    }
}
