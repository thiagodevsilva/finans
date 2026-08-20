<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BalanceAnchor;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public const SESSION_STALE_DISMISSED_AT = 'balance_stale_recalc_dismissed_at';

    public function latestAnchor(?Carbon $at = null): ?BalanceAnchor
    {
        $query = BalanceAnchor::query()->orderByDesc('as_of_date')->orderByDesc('created_at');

        if ($at) {
            $query->whereDate('as_of_date', '<=', $at->toDateString());
        }

        return $query->first();
    }

    public function previousAnchor(BalanceAnchor $anchor): ?BalanceAnchor
    {
        return BalanceAnchor::query()
            ->where(function (Builder $q) use ($anchor) {
                $q->whereDate('as_of_date', '<', $anchor->as_of_date->toDateString())
                    ->orWhere(function (Builder $sameDay) use ($anchor) {
                        $sameDay->whereDate('as_of_date', $anchor->as_of_date->toDateString())
                            ->where('created_at', '<', $anchor->created_at);
                    });
            })
            ->orderByDesc('as_of_date')
            ->orderByDesc('created_at')
            ->first();
    }

    public function needsInitialAnchor(): bool
    {
        return ! BalanceAnchor::query()->exists();
    }

    public function needsMonthlyCheckin(?Carbon $today = null): bool
    {
        $today = $today ? $today->copy() : now();

        if ($this->needsInitialAnchor()) {
            return false;
        }

        $monthKey = $today->format('Y-m');

        return ! BalanceAnchor::query()
            ->where('checkin_month', $monthKey)
            ->exists();
    }

    public function balanceAt(Carbon $at): ?float
    {
        $anchor = $this->latestAnchor($at);

        if (! $anchor) {
            return null;
        }

        return $this->balanceFromAnchor($anchor, $at);
    }

    /**
     * Saldo de caixa para exibição: ajusta quando a âncora vigente ficou stale
     * (lançamentos retroativos alterados/excluídos depois do snapshot).
     *
     * No mês corrente, deriva do saldo efetivo do fim do mês anterior + fluxos
     * de caixa do mês atual — mesma base da sugestão de recálculo.
     */
    public function effectiveBalanceAt(?Carbon $at = null): ?float
    {
        $at = $at ? $at->copy() : now();

        if ($this->needsInitialAnchor()) {
            return null;
        }

        $anchor = $this->latestAnchor($at);

        if (! $anchor) {
            return null;
        }

        if (! $this->isAnchorStale($anchor)) {
            return $this->balanceAt($at);
        }

        if ($at->isSameMonth(now())) {
            return $this->suggestedBalanceIgnoringLatestAnchor($at);
        }

        return $this->balanceWithStaleDelta($at);
    }

    public function balanceFromAnchor(BalanceAnchor $anchor, Carbon $at): float
    {
        $from = $anchor->as_of_date->toDateString();
        $to = $at->toDateString();

        $income = (float) Transaction::query()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '>', $from)
            ->whereDate('date', '<=', $to)
            ->sum('amount');

        $outflow = (float) $this->cashOutflowQuery()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereDate('date', '>', $from)
            ->whereDate('date', '<=', $to)
            ->sum('amount');

        return round((float) $anchor->amount + $income - $outflow, 2);
    }

    /**
     * Saldo na data com ajuste dos lançamentos retroativos / exclusões da âncora vigente.
     */
    public function balanceWithStaleDelta(Carbon $at): ?float
    {
        $anchor = $this->latestAnchor($at);

        if (! $anchor) {
            return null;
        }

        $base = $this->balanceFromAnchor($anchor, $at);
        $delta = $this->staleCashDelta($anchor) + $this->accountStaleAdjustment();

        if (abs($delta) < 0.00001) {
            return $base;
        }

        return round($base + $delta, 2);
    }

    /**
     * Delta de caixa dos lançamentos com data ≤ âncora alterados depois do snapshot.
     */
    public function staleCashDelta(BalanceAnchor $anchor): float
    {
        if (! $this->staleCashAffectingQuery($anchor)->exists()) {
            return 0.0;
        }

        $asOf = $anchor->as_of_date->toDateString();
        $createdAt = $anchor->created_at;

        $staleIncome = (float) Transaction::query()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '<=', $asOf)
            ->where('updated_at', '>', $createdAt)
            ->sum('amount');

        $staleOutflow = (float) $this->cashOutflowQuery()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereDate('date', '<=', $asOf)
            ->where('updated_at', '>', $createdAt)
            ->sum('amount');

        return round($staleIncome - $staleOutflow, 2);
    }

    public function accountStaleAdjustment(): float
    {
        $account = $this->currentAccount();

        if (! $account) {
            return 0.0;
        }

        return round((float) $account->balance_stale_adjustment, 2);
    }

    /**
     * Movimentações de caixa (entradas − saídas) com date > $from e date ≤ $to.
     */
    public function cashFlowBetween(Carbon $from, Carbon $to): float
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $income = (float) Transaction::query()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '>', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('amount');

        $outflow = (float) $this->cashOutflowQuery()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereDate('date', '>', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('amount');

        return round($income - $outflow, 2);
    }

    public function keepPreviousMonth(User $owner, ?Carbon $today = null): BalanceAnchor
    {
        $today = $today ? $today->copy() : now();
        $previousMonthEnd = $today->copy()->startOfMonth()->subDay()->endOfDay();
        $amount = $this->effectiveBalanceAt($previousMonthEnd) ?? 0.0;

        return $this->createAnchor(
            $owner,
            (float) $amount,
            $previousMonthEnd->toDateString(),
            BalanceAnchor::SOURCE_MONTHLY_KEEP,
            $today->format('Y-m'),
        );
    }

    public function upsertAnchor(
        User $owner,
        float $amount,
        string $asOfDate,
        string $source,
        ?string $checkinMonth = null,
    ): BalanceAnchor {
        return $this->createAnchor($owner, $amount, $asOfDate, $source, $checkinMonth);
    }

    /**
     * Lançamentos de caixa com data ≤ âncora vigente, alterados depois dela (deixam o snapshot stale).
     */
    public function staleCashAffectingQuery(BalanceAnchor $anchor): Builder
    {
        $asOf = $anchor->as_of_date->toDateString();
        $createdAt = $anchor->created_at;

        $incomeIds = Transaction::query()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '<=', $asOf)
            ->where('updated_at', '>', $createdAt)
            ->pluck('id');

        $outflowIds = $this->cashOutflowQuery()
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereDate('date', '<=', $asOf)
            ->where('updated_at', '>', $createdAt)
            ->pluck('id');

        $ids = $incomeIds->merge($outflowIds)->unique()->values();

        return Transaction::query()->whereIn('id', $ids);
    }

    public function isAnchorStale(BalanceAnchor $anchor): bool
    {
        if ($this->staleCashAffectingQuery($anchor)->exists()) {
            return true;
        }

        $account = $this->currentAccount();

        return $account
            && $account->balance_stale_at
            && abs((float) $account->balance_stale_adjustment) >= 0.01;
    }

    /**
     * Meta para o dashboard: precisa recalcular? Qual valor sugerir?
     *
     * @return array{
     *     needs_stale_recalc: bool,
     *     suggested_balance: float|null,
     *     stale_recalc_mode: 'update'|'confirm'|null
     * }
     */
    public function staleRecalcMeta(?Carbon $at = null, ?float $displayedBalance = null): array
    {
        $at = $at ? $at->copy() : now();
        $anchor = $this->latestAnchor($at);

        if (! $anchor || $this->needsMonthlyCheckin($at)) {
            return [
                'needs_stale_recalc' => false,
                'suggested_balance' => null,
                'stale_recalc_mode' => null,
            ];
        }

        if (! $this->isAnchorStale($anchor)) {
            return [
                'needs_stale_recalc' => false,
                'suggested_balance' => null,
                'stale_recalc_mode' => null,
            ];
        }

        $suggested = $this->suggestedBalanceIgnoringLatestAnchor($at);
        $displayed = $displayedBalance ?? $this->effectiveBalanceAt($at);
        $snapshot = $this->balanceAt($at);

        if ($suggested === null) {
            return [
                'needs_stale_recalc' => false,
                'suggested_balance' => null,
                'stale_recalc_mode' => null,
            ];
        }

        // Saldo na tela já reflete a sugestão — não alarmar (ex.: após retroativos já corrigidos na exibição).
        if ($displayed !== null && $this->amountsClose($displayed, $suggested)) {
            return [
                'needs_stale_recalc' => false,
                'suggested_balance' => $suggested,
                'stale_recalc_mode' => null,
            ];
        }

        if ($this->isStaleDismissed($anchor)) {
            return [
                'needs_stale_recalc' => false,
                'suggested_balance' => $suggested,
                'stale_recalc_mode' => null,
            ];
        }

        $mode = $snapshot !== null && $this->amountsClose($snapshot, $suggested)
            ? 'update'
            : 'confirm';

        return [
            'needs_stale_recalc' => true,
            'suggested_balance' => $suggested,
            'stale_recalc_mode' => $mode,
        ];
    }

    /**
     * Saldo sugerido após âncora stale: saldo efetivo do fim do mês anterior
     * + movimentações de caixa do mês atual até $at.
     *
     * Sem âncora no mês anterior (ex.: âncora inicial no mês corrente + PIX/fatura
     * no mesmo dia), cai no ajuste da âncora vigente — evita saldo null na tela.
     */
    public function suggestedBalanceIgnoringLatestAnchor(?Carbon $at = null): ?float
    {
        $at = $at ? $at->copy() : now();

        if (! $this->latestAnchor($at)) {
            return null;
        }

        $previousMonthEnd = $at->copy()->startOfMonth()->subDay()->endOfDay();
        $previousMonthBalance = $this->balanceWithStaleDelta($previousMonthEnd);

        if ($previousMonthBalance === null) {
            return $this->balanceWithStaleDelta($at);
        }

        return round(
            $previousMonthBalance + $this->cashFlowBetween($previousMonthEnd, $at),
            2
        );
    }

    public function dismissStaleRecalc(): void
    {
        $now = now();
        session([self::SESSION_STALE_DISMISSED_AT => $now->toIso8601String()]);

        $account = $this->currentAccount();
        if ($account) {
            $account->balance_stale_dismissed_at = $now;
            $account->save();
        }
    }

    /**
     * Antes de excluir: se o lançamento já estava embutido na âncora vigente,
     * registra o ajuste inverso para o banner de recálculo (exclusão some o registro).
     */
    public function recordRetroactiveCashDeletion(Transaction $transaction): void
    {
        if ($transaction->status !== Transaction::STATUS_CONFIRMED) {
            return;
        }

        $effect = $this->cashEffect($transaction);
        if (abs($effect) < 0.00001) {
            return;
        }

        $anchor = $this->latestAnchor();
        if (! $anchor) {
            return;
        }

        if ($transaction->date->toDateString() > $anchor->as_of_date->toDateString()) {
            return;
        }

        // Criado depois da âncora: só vivia no overlay stale; sumir já limpa o detector antigo.
        if ($transaction->created_at && $transaction->created_at->gt($anchor->created_at)) {
            return;
        }

        $account = Account::query()->find($transaction->account_id);
        if (! $account) {
            return;
        }

        $account->balance_stale_adjustment = round(
            (float) $account->balance_stale_adjustment - $effect,
            2
        );
        $account->balance_stale_at = now();
        $account->save();
    }

    /**
     * Efeito no caixa: entrada positiva, saída de dinheiro negativa; 0 se neutro.
     */
    public function cashEffect(Transaction $transaction): float
    {
        if ($transaction->type === Transaction::TYPE_INCOME) {
            return (float) $transaction->amount;
        }

        if ($this->cashOutflowQuery()->whereKey($transaction->id)->exists()) {
            return -1 * (float) $transaction->amount;
        }

        return 0.0;
    }

    /**
     * Query de lançamentos que reduzem o caixa (saídas de dinheiro).
     * Cartão de crédito e benefício não entram — só débito (tipo debit) debita no ramo card.
     */
    public function cashOutflowQuery(): Builder
    {
        return Transaction::query()->where(function (Builder $q) {
            $q->where(function (Builder $expense) {
                $expense->where('type', Transaction::TYPE_EXPENSE)
                    ->where(function (Builder $method) {
                        $method->whereNull('payment_method')
                            ->orWhereIn('payment_method', [
                                Transaction::PAYMENT_CASH,
                                Transaction::PAYMENT_PIX,
                                Transaction::PAYMENT_TRANSFER,
                                Transaction::PAYMENT_DEBIT,
                                Transaction::PAYMENT_AUTO_DEBIT,
                            ])
                            ->orWhere(function (Builder $debitCard) {
                                $debitCard->where('payment_method', Transaction::PAYMENT_CARD)
                                    ->whereHas(
                                        'paymentCard',
                                        fn (Builder $card) => $card->where('type', PaymentCard::TYPE_DEBIT)
                                    );
                            });
                    });
            })->orWhere(function (Builder $invoicePay) {
                $invoicePay->where('type', Transaction::TYPE_TRANSFER)
                    ->whereNotNull('credit_card_invoice_id')
                    ->where(function (Builder $method) {
                        $method->whereNull('payment_method')
                            ->orWhere('payment_method', '!=', Transaction::PAYMENT_CARD);
                    });
            })->orWhere('type', Transaction::TYPE_INVESTMENT);
        });
    }

    protected function latestStaleMoment(BalanceAnchor $anchor): ?Carbon
    {
        $moments = [];

        $staleQuery = $this->staleCashAffectingQuery($anchor);
        if ($staleQuery->exists()) {
            $maxUpdated = (clone $staleQuery)->max('updated_at');
            if ($maxUpdated) {
                $moments[] = Carbon::parse($maxUpdated);
            }
        }

        $account = $this->currentAccount();
        if ($account?->balance_stale_at) {
            $moments[] = $account->balance_stale_at->copy();
        }

        if ($moments === []) {
            return null;
        }

        return collect($moments)->sortByDesc(fn (Carbon $c) => $c->timestamp)->first();
    }

    protected function currentAccount(): ?Account
    {
        if (! auth()->check()) {
            return null;
        }

        return Account::query()->find(auth()->user()->account_id);
    }

    protected function clearAccountStaleState(string $accountId): void
    {
        Account::query()->whereKey($accountId)->update([
            'balance_stale_at' => null,
            'balance_stale_adjustment' => 0,
            'balance_stale_dismissed_at' => null,
        ]);
    }

    protected function isStaleDismissed(BalanceAnchor $anchor): bool
    {
        $latestStaleMoment = $this->latestStaleMoment($anchor);
        if (! $latestStaleMoment) {
            return false;
        }

        $dismissedMoments = [];

        $sessionDismissed = session(self::SESSION_STALE_DISMISSED_AT);
        if ($sessionDismissed) {
            $dismissedMoments[] = Carbon::parse($sessionDismissed);
        }

        $account = $this->currentAccount();
        if ($account?->balance_stale_dismissed_at) {
            $dismissedMoments[] = $account->balance_stale_dismissed_at->copy();
        }

        if ($dismissedMoments === []) {
            return false;
        }

        $lastDismissed = collect($dismissedMoments)
            ->sortByDesc(fn (Carbon $moment) => $moment->timestamp)
            ->first();

        return $lastDismissed->gte($latestStaleMoment);
    }

    protected function amountsClose(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return abs($a - $b) < 0.01;
    }

    protected function createAnchor(
        User $owner,
        float $amount,
        string $asOfDate,
        string $source,
        ?string $checkinMonth,
    ): BalanceAnchor {
        return DB::transaction(function () use ($owner, $amount, $asOfDate, $source, $checkinMonth) {
            $this->clearAccountStaleState($owner->account_id);

            return BalanceAnchor::create([
                'account_id' => $owner->account_id,
                'user_id' => $owner->id,
                'amount' => $amount,
                'as_of_date' => $asOfDate,
                'source' => $source,
                'checkin_month' => $checkinMonth,
            ]);
        });
    }
}
