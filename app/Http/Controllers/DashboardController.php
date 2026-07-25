<?php

namespace App\Http\Controllers;

use App\Models\CreditCardInvoice;
use App\Models\PaymentCard;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];
        $today = now()->toDateString();

        $confirmedInMonth = fn () => Transaction::query()
            ->whereBetween('date', $range)
            ->where('status', Transaction::STATUS_CONFIRMED);

        $income = (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_INCOME)
            ->sum('amount');

        // Competência: todo gasto do mês (inclui compras no crédito; exclui pagamento de fatura).
        $expense = (float) (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_EXPENSE)
            ->sum('amount');

        // Caixa: saiu da conta (PIX/dinheiro/débito) + pagamento de fatura (exceto com outro cartão).
        $cashExpense = (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where(function ($q) {
                $q->whereNull('payment_method')
                    ->orWhere('payment_method', '!=', Transaction::PAYMENT_CARD)
                    ->orWhereHas('paymentCard', fn ($card) => $card->where('type', PaymentCard::TYPE_DEBIT));
            })
            ->sum('amount');

        $invoicePayments = (clone $confirmedInMonth())
            ->where('type', Transaction::TYPE_TRANSFER)
            ->whereNotNull('credit_card_invoice_id')
            ->where(function ($q) {
                $q->whereNull('payment_method')
                    ->orWhere('payment_method', '!=', Transaction::PAYMENT_CARD);
            })
            ->sum('amount');

        $cashFlow = (float) $cashExpense + (float) $invoicePayments;

        $invoiceSummary = $this->invoiceSummary($today);

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

        $paidAmount = (clone $recurringBase)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->sum('amount');
        $pendingAmount = (clone $recurringBase)
            ->where('status', Transaction::STATUS_PLANNED)
            ->sum('amount');
        $paidCount = (clone $recurringBase)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->count();
        $pendingCount = (clone $recurringBase)
            ->where('status', Transaction::STATUS_PLANNED)
            ->count();
        $totalCount = $paidCount + $pendingCount;
        $paidPercent = $totalCount > 0 ? round(($paidCount / $totalCount) * 100) : 0;

        return Inertia::render('Dashboard', [
            'summary' => [
                'income' => (float) $income,
                'expense' => $expense,
                'cash_flow' => $cashFlow,
                'balance' => (float) $income - $expense,
            ],
            'invoiceSummary' => $invoiceSummary,
            'recurringSummary' => [
                'paid_amount' => (float) $paidAmount,
                'pending_amount' => (float) $pendingAmount,
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

    /**
     * @return array{current: float, future: float}
     */
    private function invoiceSummary(string $today): array
    {
        $currentInvoices = CreditCardInvoice::query()
            ->whereIn('status', [
                CreditCardInvoice::STATUS_OPEN,
                CreditCardInvoice::STATUS_CLOSED,
                CreditCardInvoice::STATUS_PARTIAL,
            ])
            ->whereDate('closing_date', '<=', $today)
            ->get();

        $current = $currentInvoices->sum(fn (CreditCardInvoice $invoice) => $invoice->remainingAmount());

        $future = (float) Transaction::query()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereHas('creditCardInvoice', fn ($q) => $q->whereDate('closing_date', '>', $today))
            ->sum('amount');

        return [
            'current' => round((float) $current, 2),
            'future' => round($future, 2),
        ];
    }
}
