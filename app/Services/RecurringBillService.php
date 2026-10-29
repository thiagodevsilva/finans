<?php

namespace App\Services;

use App\Models\CreditCardInvoice;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringBillService
{
    public function __construct(
        private readonly CreditCardInvoiceService $invoiceService,
        private readonly BalanceService $balances
    ) {}

    public function create(User $user, array $data): RecurringBill
    {
        return DB::transaction(function () use ($user, $data) {
            $kind = $data['kind'] ?? RecurringBill::KIND_FIXED;

            $bill = RecurringBill::create([
                'account_id' => $user->account_id,
                'user_id' => $data['user_id'] ?? $user->id,
                'is_shared' => (bool) ($data['is_shared'] ?? false),
                'company_id' => $data['company_id'] ?? null,
                'created_by' => $data['created_by'] ?? $user->id,
                'category_id' => $data['category_id'],
                'description' => $data['description'],
                'kind' => $kind,
                'estimated_amount' => $data['estimated_amount'],
                'day_of_month' => $kind === RecurringBill::KIND_VARIABLE
                    ? null
                    : $data['day_of_month'],
                'frequency' => RecurringBill::FREQUENCY_MONTHLY,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_card_id' => $data['payment_card_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'active' => true,
            ]);

            $this->materializeAhead($bill, 3);

            return $bill;
        });
    }

    public function materializeAhead(RecurringBill $bill, int $monthsAhead = 3): void
    {
        if (! $bill->active || $bill->isVariable()) {
            return;
        }

        if (! $bill->day_of_month) {
            return;
        }

        $start = Carbon::parse($bill->start_date)->startOfMonth();
        $cursor = now()->startOfMonth()->greaterThan($start) ? now()->startOfMonth() : $start->copy();
        $endLimit = now()->startOfMonth()->addMonthsNoOverflow($monthsAhead);

        if ($bill->end_date) {
            $billEnd = Carbon::parse($bill->end_date)->startOfMonth();
            if ($billEnd->lt($endLimit)) {
                $endLimit = $billEnd;
            }
        }

        while ($cursor->lte($endLimit)) {
            $dueDate = $this->dueDateForMonth($bill, $cursor);

            if ($dueDate->lt(Carbon::parse($bill->start_date)->startOfDay())) {
                $cursor->addMonthNoOverflow();

                continue;
            }

            if ($bill->end_date && $dueDate->gt(Carbon::parse($bill->end_date)->endOfDay())) {
                break;
            }

            $exists = Transaction::query()
                ->where('recurring_bill_id', $bill->id)
                ->whereYear('date', $dueDate->year)
                ->whereMonth('date', $dueDate->month)
                ->exists();

            if (! $exists) {
                $this->createPlannedTransaction($bill, $dueDate);
            }

            $cursor->addMonthNoOverflow();
        }
    }

    /**
     * Remove planned abertos ao tornar a conta variável.
     */
    public function clearPlannedForBill(RecurringBill $bill): int
    {
        return Transaction::query()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->delete();
    }

    /**
     * Progresso do mês: só contas fixas com vencimento (confirmed + planned).
     * Estimativa variável e lançamento pulado não entram no total nem na porcentagem.
     *
     * @return array{
     *     paid_amount: float,
     *     pending_amount: float,
     *     total_amount: float,
     *     paid_count: int,
     *     pending_count: int,
     *     total_count: int,
     *     paid_percent: int,
     *     skipped_count: int,
     *     skipped_amount: float,
     *     paid_by_bill: array<string, float>
     * }
     */
    public function summarizeMonth(?Carbon $at = null, ?ViewContext $view = null): array
    {
        if ($view === null && auth()->check()) {
            $view = app(ViewContext::class);
        }

        $start = ($at ?? now())->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];

        $txQuery = fn () => Transaction::query()
            ->when($view, fn ($q) => $q->forViewContext($view));

        $confirmed = $txQuery()
            ->whereNotNull('recurring_bill_id')
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', $range)
            ->get(['id', 'amount', 'recurring_bill_id']);

        $planned = $txQuery()
            ->whereNotNull('recurring_bill_id')
            ->where('status', Transaction::STATUS_PLANNED)
            ->whereBetween('date', $range)
            ->get(['id', 'amount', 'recurring_bill_id']);

        $paidByBill = $confirmed
            ->groupBy('recurring_bill_id')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->all();

        $skipped = $txQuery()
            ->whereNotNull('recurring_bill_id')
            ->where('status', Transaction::STATUS_SKIPPED)
            ->whereBetween('date', $range)
            ->get(['id', 'amount', 'recurring_bill_id']);

        $variableBillIds = RecurringBill::query()
            ->when($view, fn ($q) => $q->forViewContext($view))
            ->where('kind', RecurringBill::KIND_VARIABLE)
            ->pluck('id')
            ->all();

        $isVariable = fn ($tx) => in_array($tx->recurring_bill_id, $variableBillIds, true);

        $fixedConfirmed = $confirmed->reject($isVariable);
        $fixedPlanned = $planned->reject($isVariable);
        $fixedSkipped = $skipped->reject($isVariable);

        $paidAmount = round((float) $fixedConfirmed->sum('amount'), 2);
        $paidCount = $fixedConfirmed->count();
        $pendingAmount = round((float) $fixedPlanned->sum('amount'), 2);
        $pendingCount = $fixedPlanned->count();
        $skippedCount = $fixedSkipped->pluck('recurring_bill_id')->unique()->count();
        $skippedAmount = round((float) $fixedSkipped->sum('amount'), 2);
        $totalAmount = round($paidAmount + $pendingAmount, 2);
        $totalCount = $paidCount + $pendingCount;
        $paidPercent = $totalAmount > 0
            ? (int) round(($paidAmount / $totalAmount) * 100)
            : ($skippedCount > 0 ? 100 : 0);

        return [
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'total_amount' => $totalAmount,
            'paid_count' => $paidCount,
            'pending_count' => $pendingCount,
            'total_count' => $totalCount,
            'paid_percent' => $paidPercent,
            'skipped_count' => $skippedCount,
            'skipped_amount' => $skippedAmount,
            'paid_by_bill' => $paidByBill,
        ];
    }

    public function confirm(Transaction $transaction, float $amount, ?string $date = null, array $payment = []): Transaction
    {
        if (! $transaction->recurring_bill_id) {
            throw new \InvalidArgumentException('Transação não é de conta fixa.');
        }

        $updates = [
            'status' => Transaction::STATUS_CONFIRMED,
            'amount' => $amount,
        ];

        if ($date) {
            $updates['date'] = $date;
        }

        if (array_key_exists('payment_method', $payment)) {
            $updates['payment_method'] = $payment['payment_method'];
            $updates['payment_card_id'] = $payment['payment_card_id'] ?? null;
        }

        $method = $updates['payment_method'] ?? $transaction->payment_method;
        $cardId = $updates['payment_card_id'] ?? $transaction->payment_card_id;

        if ($method === Transaction::PAYMENT_CARD && $cardId) {
            $card = PaymentCard::query()->find($cardId);
            if ($card) {
                $invoice = $this->invoiceService->resolveForPurchase(
                    $card,
                    $updates['date'] ?? $transaction->date
                );
                $updates['credit_card_invoice_id'] = $invoice?->id;
            }
        } else {
            $updates['credit_card_invoice_id'] = null;
        }

        $transaction->update($updates);

        return $transaction->fresh();
    }

    public function skip(Transaction $transaction): void
    {
        $invoiceId = $transaction->credit_card_invoice_id;

        $transaction->update([
            'status' => Transaction::STATUS_SKIPPED,
            'credit_card_invoice_id' => null,
        ]);

        $this->refreshInvoice($invoiceId);
    }

    /**
     * Pula o mês inteiro da conta. Fixa: marca o planned. Variável: grava um marcador
     * para a estimativa sair da porcentagem, mesmo sem gasto lançado.
     */
    public function skipCurrentMonth(RecurringBill $bill, ?Carbon $month = null): void
    {
        $month ??= now();

        if ($this->findMonthTransaction($bill, $month, Transaction::STATUS_SKIPPED)) {
            return;
        }

        if (! $bill->isVariable()) {
            $planned = $this->findMonthTransaction($bill, $month, Transaction::STATUS_PLANNED);
            if ($planned) {
                $this->skip($planned);

                return;
            }

            if ($this->findMonthTransaction($bill, $month, Transaction::STATUS_CONFIRMED)) {
                return;
            }
        }

        $date = $bill->isVariable() || ! $bill->day_of_month
            ? $month->copy()->startOfMonth()
            : $this->dueDateForMonth($bill, $month->copy()->startOfMonth());

        $this->createSkippedMarker($bill, $date);
    }

    public function unskip(Transaction $transaction): ?Transaction
    {
        if (! $transaction->recurring_bill_id || $transaction->status !== Transaction::STATUS_SKIPPED) {
            throw new \InvalidArgumentException('Lançamento inválido para desfazer o pulo.');
        }

        $bill = RecurringBill::query()->findOrFail($transaction->recurring_bill_id);

        if ($bill->isVariable()) {
            $transaction->delete();

            return null;
        }

        return $this->restorePlanned($transaction, $bill);
    }

    /**
     * Inativa sem apagar o cadastro.
     * current: tira o mês atual e os seguintes. future: mantém o mês atual.
     */
    public function deactivate(RecurringBill $bill, string $scope): void
    {
        $startThisMonth = now()->copy()->startOfMonth();
        $startNextMonth = now()->copy()->startOfMonth()->addMonthNoOverflow();

        $bill->update([
            'active' => false,
            'suspended_from' => ($scope === 'current' ? $startThisMonth : $startNextMonth)->toDateString(),
        ]);

        $planned = Transaction::query()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED);

        if ($scope === 'current') {
            $planned->whereDate('date', '>=', $startThisMonth->toDateString())->delete();

            if ($bill->isVariable()) {
                $this->skipCurrentMonth($bill->fresh());
            }

            return;
        }

        $planned->whereDate('date', '>=', $startNextMonth->toDateString())->delete();
    }

    /**
     * Reativa. current: gera o mês atual. future: só os próximos (o mês atual fica pulado).
     */
    public function activate(RecurringBill $bill, string $scope): void
    {
        $bill->update([
            'active' => true,
            'suspended_from' => null,
        ]);

        $bill = $bill->fresh();

        if ($scope === 'future') {
            $this->omitCurrentMonthIfMissing($bill);
        } else {
            $this->restoreCurrentMonth($bill);
        }

        $this->materializeAhead($bill->fresh(), 3);
    }

    /**
     * Desfaz pagamento confirmado de conta fixa/variável.
     * Fixed: volta a planned com dados do cadastro. Variable: remove o lançamento.
     */
    public function unconfirm(Transaction $transaction): ?Transaction
    {
        if (! $transaction->recurring_bill_id) {
            throw new \InvalidArgumentException('Transação não é de conta fixa.');
        }

        if ($transaction->status !== Transaction::STATUS_CONFIRMED) {
            throw new \InvalidArgumentException('Lançamento inválido para desfazer pagamento.');
        }

        $bill = RecurringBill::query()->findOrFail($transaction->recurring_bill_id);

        $this->balances->recordRetroactiveCashDeletion($transaction);

        if ($bill->isVariable()) {
            $transaction->delete();

            return null;
        }

        return $this->restorePlanned($transaction, $bill);
    }

    /**
     * Confirma planned do mês ou cria expense confirmada vinculada à conta fixa.
     * Conta variável: sempre cria um novo lançamento confirmado (vários no mês).
     */
    public function settleForBill(User $user, RecurringBill $bill, array $data): Transaction
    {
        $date = Carbon::parse($data['date']);

        if (! $bill->isVariable()) {
            $planned = Transaction::query()
                ->where('recurring_bill_id', $bill->id)
                ->where('status', Transaction::STATUS_PLANNED)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->first();

            if ($planned) {
                $confirmed = $this->confirm(
                    $planned,
                    (float) $data['amount'],
                    $data['date'],
                    [
                        'payment_method' => $data['payment_method'] ?? null,
                        'payment_card_id' => $data['payment_card_id'] ?? null,
                    ]
                );

                $updates = [
                    'description' => $data['description'] ?? $confirmed->description,
                    'category_id' => $data['category_id'] ?? $confirmed->category_id,
                    'credit_card_invoice_id' => $data['credit_card_invoice_id'] ?? $confirmed->credit_card_invoice_id,
                ];

                foreach (['user_id', 'is_shared', 'company_id', 'created_by'] as $key) {
                    if (array_key_exists($key, $data)) {
                        $updates[$key] = $data[$key];
                    }
                }

                $confirmed->update($updates);

                return $confirmed->fresh();
            }
        }

        $invoiceId = $data['credit_card_invoice_id'] ?? null;
        $method = $data['payment_method'] ?? null;
        $cardId = $data['payment_card_id'] ?? null;

        if (! $invoiceId && $method === Transaction::PAYMENT_CARD && $cardId) {
            $card = PaymentCard::query()->find($cardId);
            if ($card) {
                $invoiceId = $this->invoiceService->resolveForPurchase($card, $date)?->id;
            }
        }

        return Transaction::create([
            'account_id' => $user->account_id,
            'user_id' => $data['user_id'] ?? $user->id,
            'is_shared' => (bool) ($data['is_shared'] ?? $bill->is_shared),
            'company_id' => array_key_exists('company_id', $data) ? $data['company_id'] : $bill->company_id,
            'created_by' => $data['created_by'] ?? $user->id,
            'category_id' => $data['category_id'],
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'date' => $data['date'],
            'payment_method' => $method,
            'payment_card_id' => $cardId,
            'bank_account_id' => null,
            'credit_card_invoice_id' => $invoiceId,
            'recurring_bill_id' => $bill->id,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Lança um gasto já ocorrido numa conta variável. Não altera a porcentagem das fixas.
     */
    public function recordVariablePayment(User $user, RecurringBill $bill, array $data): Transaction
    {
        if (! $bill->isVariable()) {
            throw new \InvalidArgumentException('Só contas variáveis recebem lançamento avulso.');
        }

        $this->deleteSkippedInMonth($bill, Carbon::parse($data['date']));

        return $this->settleForBill($user, $bill, [
            'amount' => $data['amount'],
            'date' => $data['date'],
            'description' => $data['description'] ?: $bill->description,
            'category_id' => $bill->category_id,
            'payment_method' => $bill->payment_method,
            'payment_card_id' => $bill->payment_card_id,
            'user_id' => $bill->user_id,
            'is_shared' => (bool) $bill->is_shared,
            'company_id' => $bill->company_id,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Vincula uma saída existente à conta fixa, dando baixa no planned do mês se houver.
     * Conta variável: só vincula o lançamento (sem consumir planned).
     */
    public function linkExpenseToBill(
        User $user,
        Transaction $expense,
        RecurringBill $bill,
        array $data
    ): Transaction {
        $date = Carbon::parse($data['date'] ?? $expense->date);
        $dateString = $date->toDateString();

        if (! $bill->isVariable()) {
            $planned = Transaction::query()
                ->where('recurring_bill_id', $bill->id)
                ->where('status', Transaction::STATUS_PLANNED)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->first();

            if ($planned && $planned->id !== $expense->id) {
                $confirmed = $this->confirm(
                    $planned,
                    (float) ($data['amount'] ?? $expense->amount),
                    $dateString,
                    [
                        'payment_method' => $data['payment_method'] ?? $expense->payment_method,
                        'payment_card_id' => $data['payment_card_id'] ?? $expense->payment_card_id,
                    ]
                );

                $confirmed->update([
                    'description' => $data['description'] ?? $expense->description,
                    'category_id' => $data['category_id'] ?? $expense->category_id,
                    'credit_card_invoice_id' => $data['credit_card_invoice_id'] ?? $expense->credit_card_invoice_id,
                    'user_id' => $user->id,
                ]);

                $expense->delete();

                return $confirmed->fresh();
            }
        }

        $expense->update([
            'amount' => $data['amount'] ?? $expense->amount,
            'description' => $data['description'] ?? $expense->description,
            'category_id' => $data['category_id'] ?? $expense->category_id,
            'date' => $dateString,
            'payment_method' => $data['payment_method'] ?? $expense->payment_method,
            'payment_card_id' => $data['payment_card_id'] ?? $expense->payment_card_id,
            'bank_account_id' => $data['bank_account_id'] ?? $expense->bank_account_id,
            'credit_card_invoice_id' => $data['credit_card_invoice_id'] ?? $expense->credit_card_invoice_id,
            'type' => Transaction::TYPE_EXPENSE,
            'recurring_bill_id' => $bill->id,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        return $expense->fresh();
    }

    /**
     * Atualiza lançamentos planned conforme escopo (abertos ou a partir de data).
     */
    public function propagateToPlanned(RecurringBill $bill, string $scope, ?string $fromDate = null): int
    {
        if ($bill->isVariable()) {
            return 0;
        }

        $query = Transaction::query()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED);

        if ($scope === 'from_date') {
            $query->whereDate('date', '>=', $fromDate ?: now()->toDateString());
        }

        $updates = [
            'description' => $bill->description,
            'amount' => $bill->estimated_amount,
            'category_id' => $bill->category_id,
            'payment_method' => $bill->payment_method,
            'payment_card_id' => $bill->payment_card_id,
        ];

        $count = 0;

        $query->get()->each(function (Transaction $tx) use ($bill, $updates, &$count) {
            $payload = $updates;

            if ($bill->day_of_month) {
                $date = Carbon::parse($tx->date);
                $payload['date'] = $date->copy()
                    ->day(min((int) $bill->day_of_month, $date->daysInMonth))
                    ->toDateString();
            }

            if (
                ($payload['payment_method'] ?? null) === Transaction::PAYMENT_CARD
                && ! empty($payload['payment_card_id'])
            ) {
                $card = PaymentCard::query()->find($payload['payment_card_id']);
                if ($card) {
                    $payload['credit_card_invoice_id'] = $this->invoiceService
                        ->resolveForPurchase($card, $payload['date'] ?? $tx->date)
                        ?->id;
                }
            } else {
                $payload['credit_card_invoice_id'] = null;
            }

            $tx->update($payload);
            $count++;
        });

        return $count;
    }

    private function omitCurrentMonthIfMissing(RecurringBill $bill): void
    {
        $month = now();

        if ($this->findMonthTransaction($bill, $month, Transaction::STATUS_CONFIRMED)) {
            return;
        }

        if (! $bill->isVariable() && $this->findMonthTransaction($bill, $month, Transaction::STATUS_PLANNED)) {
            return;
        }

        $this->skipCurrentMonth($bill);
    }

    private function restoreCurrentMonth(RecurringBill $bill): void
    {
        $month = now();

        if ($bill->isVariable()) {
            $this->deleteSkippedInMonth($bill, $month);

            return;
        }

        if ($this->findMonthTransaction($bill, $month, Transaction::STATUS_CONFIRMED)) {
            $this->deleteSkippedInMonth($bill, $month);

            return;
        }

        $skipped = $this->findMonthTransaction($bill, $month, Transaction::STATUS_SKIPPED);
        if ($skipped) {
            $this->unskip($skipped);
        }
    }

    private function restorePlanned(Transaction $transaction, RecurringBill $bill): Transaction
    {
        $dueDate = $this->dueDateForMonth($bill, $transaction->date->copy()->startOfMonth());

        $invoiceId = null;
        if ($bill->payment_method === Transaction::PAYMENT_CARD && $bill->payment_card_id) {
            $card = PaymentCard::query()->find($bill->payment_card_id);
            if ($card) {
                $invoiceId = $this->invoiceService->resolveForPurchase($card, $dueDate)?->id;
            }
        }

        $transaction->update([
            'status' => Transaction::STATUS_PLANNED,
            'amount' => $bill->estimated_amount,
            'description' => $bill->description,
            'category_id' => $bill->category_id,
            'date' => $dueDate->toDateString(),
            'payment_method' => $bill->payment_method,
            'payment_card_id' => $bill->payment_card_id,
            'bank_account_id' => null,
            'credit_card_invoice_id' => $invoiceId,
            'user_id' => $bill->user_id,
            'is_shared' => (bool) $bill->is_shared,
            'company_id' => $bill->company_id,
        ]);

        $this->refreshInvoice($invoiceId);

        return $transaction->fresh();
    }

    private function findMonthTransaction(RecurringBill $bill, Carbon $month, string $status): ?Transaction
    {
        return Transaction::query()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', $status)
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->first();
    }

    private function deleteSkippedInMonth(RecurringBill $bill, Carbon $month): void
    {
        Transaction::query()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_SKIPPED)
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->delete();
    }

    private function createSkippedMarker(RecurringBill $bill, Carbon $date): Transaction
    {
        return Transaction::create([
            'account_id' => $bill->account_id,
            'user_id' => $bill->user_id,
            'is_shared' => (bool) $bill->is_shared,
            'company_id' => $bill->company_id,
            'created_by' => $bill->created_by,
            'category_id' => $bill->category_id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => $bill->estimated_amount,
            'description' => $bill->description,
            'date' => $date->toDateString(),
            'payment_method' => $bill->payment_method,
            'payment_card_id' => $bill->payment_card_id,
            'bank_account_id' => null,
            'credit_card_invoice_id' => null,
            'recurring_bill_id' => $bill->id,
            'status' => Transaction::STATUS_SKIPPED,
        ]);
    }

    private function refreshInvoice(?string $invoiceId): void
    {
        if (! $invoiceId) {
            return;
        }

        $invoice = CreditCardInvoice::query()->find($invoiceId);
        if ($invoice) {
            $this->invoiceService->refreshStatus($invoice);
        }
    }

    private function createPlannedTransaction(RecurringBill $bill, Carbon $dueDate): Transaction
    {
        $invoiceId = null;

        if ($bill->payment_method === Transaction::PAYMENT_CARD && $bill->payment_card_id) {
            $card = PaymentCard::query()->find($bill->payment_card_id);
            if ($card) {
                $invoiceId = $this->invoiceService->resolveForPurchase($card, $dueDate)?->id;
            }
        }

        return Transaction::create([
            'account_id' => $bill->account_id,
            'user_id' => $bill->user_id,
            'is_shared' => (bool) $bill->is_shared,
            'company_id' => $bill->company_id,
            'created_by' => $bill->created_by,
            'category_id' => $bill->category_id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => $bill->estimated_amount,
            'description' => $bill->description,
            'date' => $dueDate->toDateString(),
            'payment_method' => $bill->payment_method,
            'payment_card_id' => $bill->payment_card_id,
            'bank_account_id' => null,
            'credit_card_invoice_id' => $invoiceId,
            'recurring_bill_id' => $bill->id,
            'status' => Transaction::STATUS_PLANNED,
        ]);
    }

    private function dueDateForMonth(RecurringBill $bill, Carbon $month): Carbon
    {
        $daysInMonth = $month->daysInMonth;

        return $month->copy()->day(min((int) $bill->day_of_month, $daysInMonth))->startOfDay();
    }
}
