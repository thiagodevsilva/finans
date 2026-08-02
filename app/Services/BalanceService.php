<?php

namespace App\Services;

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
     * (lançamentos retroativos alterados depois do snapshot).
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

        if ($this->staleCashAffectingQuery($anchor)->exists()) {
            return $this->suggestedBalanceIgnoringLatestAnchor($at);
        }

        return $this->balanceAt($at);
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

    /**
     * Meta para o dashboard: precisa recalcular? Qual valor sugerir?
     *
     * @return array{needs_stale_recalc: bool, suggested_balance: float|null}
     */
    public function staleRecalcMeta(?Carbon $at = null): array
    {
        $at = $at ? $at->copy() : now();
        $anchor = $this->latestAnchor($at);

        if (! $anchor || $this->needsMonthlyCheckin($at)) {
            return ['needs_stale_recalc' => false, 'suggested_balance' => null];
        }

        $staleQuery = $this->staleCashAffectingQuery($anchor);

        if (! $staleQuery->exists()) {
            return ['needs_stale_recalc' => false, 'suggested_balance' => null];
        }

        $dismissedAt = session(self::SESSION_STALE_DISMISSED_AT);
        if ($dismissedAt) {
            $latestStaleUpdate = (clone $staleQuery)->max('updated_at');
            if ($latestStaleUpdate && Carbon::parse($latestStaleUpdate)->lte(Carbon::parse($dismissedAt))) {
                return ['needs_stale_recalc' => false, 'suggested_balance' => null];
            }
        }

        return [
            'needs_stale_recalc' => true,
            'suggested_balance' => $this->suggestedBalanceIgnoringLatestAnchor($at),
        ];
    }

    /**
     * Saldo sugerido tratando a âncora vigente como inválida.
     *
     * Soma o saldo calculado a partir da âncora vigente com os lançamentos de caixa
     * retroativos (data ≤ âncora) alterados depois do snapshot. Não recalcula a partir
     * da âncora anterior: isso falhava quando os retroativos tinham a mesma data da
     * âncora (ex.: keep de 31/07 + lançamentos em 31/07), pois balanceFromAnchor usa
     * date > as_of_date e ignorava esses valores.
     */
    public function suggestedBalanceIgnoringLatestAnchor(?Carbon $at = null): ?float
    {
        $at = $at ? $at->copy() : now();
        $latest = $this->latestAnchor($at);

        if (! $latest) {
            return null;
        }

        $asOf = $latest->as_of_date->toDateString();
        $createdAt = $latest->created_at;

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

        $current = $this->balanceFromAnchor($latest, $at);

        return round($current + $staleIncome - $staleOutflow, 2);
    }

    public function dismissStaleRecalc(): void
    {
        session([self::SESSION_STALE_DISMISSED_AT => now()->toIso8601String()]);
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

    protected function createAnchor(
        User $owner,
        float $amount,
        string $asOfDate,
        string $source,
        ?string $checkinMonth,
    ): BalanceAnchor {
        return DB::transaction(fn () => BalanceAnchor::create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
            'amount' => $amount,
            'as_of_date' => $asOfDate,
            'source' => $source,
            'checkin_month' => $checkinMonth,
        ]));
    }
}
