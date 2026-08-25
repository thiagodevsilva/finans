<?php

namespace App\Http\Controllers;

use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\RecurringBillService;
use App\Services\ReportChartService;
use App\Services\ViewContext;
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
        ViewContext $view,
    ): Response {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];

        $isCurrentMonth = $start->isSameMonth(now());
        $balanceAt = $isCurrentMonth ? now() : $end->copy();

        $focusMember = $view->isFamily()
            ? $request->user()
            : ($view->focusMember() ?? $request->user());

        if ($view->isFamily()) {
            $cashBalance = $balances->familyEffectiveBalanceAt($balanceAt);
            $latestAnchor = $balances->latestAnchor($balanceAt, $focusMember);
            $needsInitial = $balances->needsInitialAnchor($focusMember);
            $needsCheckin = $balances->needsMonthlyCheckin(null, $focusMember);
            $previousMonthEnd = now()->copy()->startOfMonth()->subDay()->endOfDay();
            $previousMonthBalance = $needsInitial
                ? null
                : $balances->familyEffectiveBalanceAt($previousMonthEnd);
            $staleRecalc = $isCurrentMonth
                ? $balances->staleRecalcMeta(null, $balances->effectiveBalanceAt(null, $focusMember), $focusMember)
                : ['needs_stale_recalc' => false, 'suggested_balance' => null, 'stale_recalc_mode' => null];
        } else {
            $cashBalance = $balances->effectiveBalanceAt($balanceAt, $focusMember);
            $latestAnchor = $balances->latestAnchor($balanceAt, $focusMember);
            $needsInitial = $balances->needsInitialAnchor($focusMember);
            $needsCheckin = $balances->needsMonthlyCheckin(null, $focusMember);
            $previousMonthEnd = now()->copy()->startOfMonth()->subDay()->endOfDay();
            $previousMonthBalance = $needsInitial
                ? null
                : $balances->effectiveBalanceAt($previousMonthEnd, $focusMember);
            $staleRecalc = $isCurrentMonth
                ? $balances->staleRecalcMeta(null, $cashBalance, $focusMember)
                : ['needs_stale_recalc' => false, 'suggested_balance' => null, 'stale_recalc_mode' => null];
        }

        $confirmedInMonth = fn () => Transaction::query()
            ->forViewContext($view)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
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

        $cardPayments = $charts->cardPaymentsTotal($start->toDateString(), $end->toDateString(), $view);

        $recent = Transaction::query()
            ->forViewContext($view)
            ->with([
                'category:id,name,color',
                'user:id,name',
                'company:id,name,cnpj',
                'paymentCard:id,name,brand,type,last_four,color',
                'bankAccount:id,name,color',
            ])
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recurringSummary = $recurringBills->summarizeMonth($start, $view);

        $memberBalances = [];
        if ($view->isFamily()) {
            $memberBalances = User::query()
                ->where('account_id', $request->user()->account_id)
                ->orderBy('name')
                ->get()
                ->map(fn (User $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'balance' => $balances->effectiveBalanceAt($balanceAt, $m),
                ])
                ->values()
                ->all();
        }

        $pinnedChartId = $request->user()->pinned_dashboard_chart;
        $pinnedChart = $charts->isValidChartId($pinnedChartId)
            ? $charts->build($pinnedChartId, $month, $year, $view)
            : null;

        return Inertia::render('Dashboard', [
            'summary' => [
                'balance' => $cashBalance,
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
                'has_anchor' => ! $needsInitial,
                'needs_initial' => $needsInitial,
                'needs_monthly_checkin' => $needsCheckin,
                'as_of_date' => $latestAnchor?->as_of_date?->toDateString(),
                'previous_month_balance' => $previousMonthBalance,
                'needs_stale_recalc' => $staleRecalc['needs_stale_recalc'],
                'suggested_balance' => $staleRecalc['suggested_balance'],
                'stale_recalc_mode' => $staleRecalc['stale_recalc_mode'] ?? null,
                'focus_member_id' => $focusMember->id,
                'is_family_sum' => $view->isFamily(),
            ],
            'memberBalances' => $memberBalances,
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
