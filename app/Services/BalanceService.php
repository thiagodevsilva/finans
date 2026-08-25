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

    public function resolveMember(?User $member = null): User
    {
        if ($member) {
            return $member;
        }

        if (app()->bound(ViewContext::class)) {
            $ctx = app(ViewContext::class);
            if ($ctx->isPersonal() && ($focus = $ctx->focusMember())) {
                return $focus;
            }
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Usuário autenticado necessário para calcular saldo.');
        }

        return $user;
    }

    public function latestAnchor(?Carbon $at = null, ?User $member = null): ?BalanceAnchor
    {
        $member = $this->resolveMember($member);

        $query = BalanceAnchor::query()
            ->where('user_id', $member->id)
            ->orderByDesc('as_of_date')
            ->orderByDesc('created_at');

        if ($at) {
            $query->whereDate('as_of_date', '<=', $at->toDateString());
        }

        return $query->first();
    }

    public function previousAnchor(BalanceAnchor $anchor): ?BalanceAnchor
    {
        return BalanceAnchor::query()
            ->where('user_id', $anchor->user_id)
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

    public function needsInitialAnchor(?User $member = null): bool
    {
        $member = $this->resolveMember($member);

        return ! BalanceAnchor::query()->where('user_id', $member->id)->exists();
    }

    public function needsMonthlyCheckin(?Carbon $today = null, ?User $member = null): bool
    {
        $member = $this->resolveMember($member);
        $today = $today ? $today->copy() : now();

        if ($this->needsInitialAnchor($member)) {
            return false;
        }

        $monthKey = $today->format('Y-m');

        return ! BalanceAnchor::query()
            ->where('user_id', $member->id)
            ->where('checkin_month', $monthKey)
            ->exists();
    }

    public function balanceAt(Carbon $at, ?User $member = null): ?float
    {
        $member = $this->resolveMember($member);
        $anchor = $this->latestAnchor($at, $member);

        if (! $anchor) {
            return null;
        }

        return $this->balanceFromAnchor($anchor, $at);
    }

    /**
     * Saldo de caixa do membro para exibição.
     */
    public function effectiveBalanceAt(?Carbon $at = null, ?User $member = null): ?float
    {
        $member = $this->resolveMember($member);
        $at = $at ? $at->copy() : now();

        if ($this->needsInitialAnchor($member)) {
            return null;
        }

        $anchor = $this->latestAnchor($at, $member);

        if (! $anchor) {
            return null;
        }

        if (! $this->isAnchorStale($anchor)) {
            return $this->balanceAt($at, $member);
        }

        if ($at->isSameMonth(now())) {
            return $this->suggestedBalanceIgnoringLatestAnchor($at, $member);
        }

        return $this->balanceWithStaleDelta($at, $member);
    }

    /**
     * Soma dos caixas dos membros da conta (visão família).
     */
    public function familyEffectiveBalanceAt(?Carbon $at = null, ?string $accountId = null): ?float
    {
        $accountId ??= auth()->user()?->account_id;
        if (! $accountId) {
            return null;
        }

        $members = User::query()->where('account_id', $accountId)->get();
        $sum = 0.0;
        $any = false;

        foreach ($members as $member) {
            $balance = $this->effectiveBalanceAt($at, $member);
            if ($balance !== null) {
                $sum += $balance;
                $any = true;
            }
        }

        return $any ? round($sum, 2) : null;
    }

    public function balanceFromAnchor(BalanceAnchor $anchor, Carbon $at): float
    {
        $from = $anchor->as_of_date->toDateString();
        $to = $at->toDateString();
        $memberId = $anchor->user_id;

        $income = (float) Transaction::query()
            ->where('user_id', $memberId)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '>', $from)
            ->whereDate('date', '<=', $to)
            ->sum('amount');

        $outflow = (float) $this->cashOutflowQuery($memberId)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereDate('date', '>', $from)
            ->whereDate('date', '<=', $to)
            ->sum('amount');

        return round((float) $anchor->amount + $income - $outflow, 2);
    }

    public function balanceWithStaleDelta(Carbon $at, ?User $member = null): ?float
    {
        $member = $this->resolveMember($member);
        $anchor = $this->latestAnchor($at, $member);

        if (! $anchor) {
            return null;
        }

        $base = $this->balanceFromAnchor($anchor, $at);
        $delta = $this->staleCashDelta($anchor) + $this->memberStaleAdjustment($member);

        if (abs($delta) < 0.00001) {
            return $base;
        }

        return round($base + $delta, 2);
    }

    public function staleCashDelta(BalanceAnchor $anchor): float
    {
        if (! $this->staleCashAffectingQuery($anchor)->exists()) {
            return 0.0;
        }

        $asOf = $anchor->as_of_date->toDateString();
        $createdAt = $anchor->created_at;
        $memberId = $anchor->user_id;

        $staleIncome = (float) Transaction::query()
            ->where('user_id', $memberId)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '<=', $asOf)
            ->where('updated_at', '>', $createdAt)
            ->sum('amount');

        $staleOutflow = (float) $this->cashOutflowQuery($memberId)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereDate('date', '<=', $asOf)
            ->where('updated_at', '>', $createdAt)
            ->sum('amount');

        return round($staleIncome - $staleOutflow, 2);
    }

    public function memberStaleAdjustment(User $member): float
    {
        $member->refresh();

        return round((float) $member->balance_stale_adjustment, 2);
    }

    /** @deprecated Use memberStaleAdjustment */
    public function accountStaleAdjustment(): float
    {
        return $this->memberStaleAdjustment($this->resolveMember());
    }

    public function cashFlowBetween(Carbon $from, Carbon $to, ?User $member = null): float
    {
        $member = $this->resolveMember($member);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $income = (float) Transaction::query()
            ->where('user_id', $member->id)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '>', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('amount');

        $outflow = (float) $this->cashOutflowQuery($member->id)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereDate('date', '>', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('amount');

        return round($income - $outflow, 2);
    }

    public function keepPreviousMonth(User $member, ?Carbon $today = null, ?User $actor = null): BalanceAnchor
    {
        $today = $today ? $today->copy() : now();
        $previousMonthEnd = $today->copy()->startOfMonth()->subDay()->endOfDay();
        $amount = $this->effectiveBalanceAt($previousMonthEnd, $member) ?? 0.0;

        return $this->createAnchor(
            $member,
            (float) $amount,
            $previousMonthEnd->toDateString(),
            BalanceAnchor::SOURCE_MONTHLY_KEEP,
            $today->format('Y-m'),
            $actor ?? $member,
        );
    }

    public function upsertAnchor(
        User $member,
        float $amount,
        string $asOfDate,
        string $source,
        ?string $checkinMonth = null,
        ?User $actor = null,
    ): BalanceAnchor {
        return $this->createAnchor($member, $amount, $asOfDate, $source, $checkinMonth, $actor ?? $member);
    }

    public function staleCashAffectingQuery(BalanceAnchor $anchor): Builder
    {
        $asOf = $anchor->as_of_date->toDateString();
        $createdAt = $anchor->created_at;
        $memberId = $anchor->user_id;

        $incomeIds = Transaction::query()
            ->where('user_id', $memberId)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->where('type', Transaction::TYPE_INCOME)
            ->whereDate('date', '<=', $asOf)
            ->where('updated_at', '>', $createdAt)
            ->pluck('id');

        $outflowIds = $this->cashOutflowQuery($memberId)
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

        $member = User::query()->find($anchor->user_id);

        return $member
            && $member->balance_stale_at
            && abs((float) $member->balance_stale_adjustment) >= 0.01;
    }

    /**
     * @return array{
     *     needs_stale_recalc: bool,
     *     suggested_balance: float|null,
     *     stale_recalc_mode: 'update'|'confirm'|null
     * }
     */
    public function staleRecalcMeta(?Carbon $at = null, ?float $displayedBalance = null, ?User $member = null): array
    {
        $member = $this->resolveMember($member);
        $at = $at ? $at->copy() : now();
        $anchor = $this->latestAnchor($at, $member);

        if (! $anchor || $this->needsMonthlyCheckin($at, $member)) {
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

        $suggested = $this->suggestedBalanceIgnoringLatestAnchor($at, $member);
        $displayed = $displayedBalance ?? $this->effectiveBalanceAt($at, $member);
        $snapshot = $this->balanceAt($at, $member);

        if ($suggested === null) {
            return [
                'needs_stale_recalc' => false,
                'suggested_balance' => null,
                'stale_recalc_mode' => null,
            ];
        }

        if ($displayed !== null && $this->amountsClose($displayed, $suggested)) {
            return [
                'needs_stale_recalc' => false,
                'suggested_balance' => $suggested,
                'stale_recalc_mode' => null,
            ];
        }

        if ($this->isStaleDismissed($anchor, $member)) {
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

    public function suggestedBalanceIgnoringLatestAnchor(?Carbon $at = null, ?User $member = null): ?float
    {
        $member = $this->resolveMember($member);
        $at = $at ? $at->copy() : now();

        if (! $this->latestAnchor($at, $member)) {
            return null;
        }

        $previousMonthEnd = $at->copy()->startOfMonth()->subDay()->endOfDay();
        $previousMonthBalance = $this->balanceWithStaleDelta($previousMonthEnd, $member);

        if ($previousMonthBalance === null) {
            return $this->balanceWithStaleDelta($at, $member);
        }

        return round(
            $previousMonthBalance + $this->cashFlowBetween($previousMonthEnd, $at, $member),
            2
        );
    }

    public function dismissStaleRecalc(?User $member = null): void
    {
        $member = $this->resolveMember($member);
        $now = now();
        session([self::SESSION_STALE_DISMISSED_AT => $now->toIso8601String()]);

        $member->balance_stale_dismissed_at = $now;
        $member->save();
    }

    public function recordRetroactiveCashDeletion(Transaction $transaction): void
    {
        if ($transaction->status !== Transaction::STATUS_CONFIRMED) {
            return;
        }

        $effect = $this->cashEffect($transaction);
        if (abs($effect) < 0.00001) {
            return;
        }

        $member = User::query()->find($transaction->user_id);
        if (! $member) {
            return;
        }

        $anchor = $this->latestAnchor(null, $member);
        if (! $anchor) {
            return;
        }

        if ($transaction->date->toDateString() > $anchor->as_of_date->toDateString()) {
            return;
        }

        if ($transaction->created_at && $transaction->created_at->gt($anchor->created_at)) {
            return;
        }

        $member->balance_stale_adjustment = round(
            (float) $member->balance_stale_adjustment - $effect,
            2
        );
        $member->balance_stale_at = now();
        $member->save();
    }

    public function cashEffect(Transaction $transaction): float
    {
        if ($transaction->type === Transaction::TYPE_INCOME) {
            return (float) $transaction->amount;
        }

        if ($this->cashOutflowQuery($transaction->user_id)->whereKey($transaction->id)->exists()) {
            return -1 * (float) $transaction->amount;
        }

        return 0.0;
    }

    /**
     * Query de lançamentos que reduzem o caixa (saídas de dinheiro).
     */
    public function cashOutflowQuery(?string $memberId = null): Builder
    {
        $memberId ??= $this->resolveMember()->id;

        return Transaction::query()
            ->where('user_id', $memberId)
            ->where(function (Builder $q) {
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

    protected function latestStaleMoment(BalanceAnchor $anchor, User $member): ?Carbon
    {
        $moments = [];

        $staleQuery = $this->staleCashAffectingQuery($anchor);
        if ($staleQuery->exists()) {
            $maxUpdated = (clone $staleQuery)->max('updated_at');
            if ($maxUpdated) {
                $moments[] = Carbon::parse($maxUpdated);
            }
        }

        if ($member->balance_stale_at) {
            $moments[] = $member->balance_stale_at->copy();
        }

        if ($moments === []) {
            return null;
        }

        return collect($moments)->sortByDesc(fn (Carbon $c) => $c->timestamp)->first();
    }

    protected function clearMemberStaleState(User $member): void
    {
        $member->forceFill([
            'balance_stale_at' => null,
            'balance_stale_adjustment' => 0,
            'balance_stale_dismissed_at' => null,
        ])->save();
    }

    protected function isStaleDismissed(BalanceAnchor $anchor, User $member): bool
    {
        $latestStaleMoment = $this->latestStaleMoment($anchor, $member);
        if (! $latestStaleMoment) {
            return false;
        }

        $dismissedMoments = [];

        $sessionDismissed = session(self::SESSION_STALE_DISMISSED_AT);
        if ($sessionDismissed) {
            $dismissedMoments[] = Carbon::parse($sessionDismissed);
        }

        if ($member->balance_stale_dismissed_at) {
            $dismissedMoments[] = $member->balance_stale_dismissed_at->copy();
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
        User $member,
        float $amount,
        string $asOfDate,
        string $source,
        ?string $checkinMonth,
        ?User $actor = null,
    ): BalanceAnchor {
        $actor ??= $member;

        return DB::transaction(function () use ($member, $amount, $asOfDate, $source, $checkinMonth, $actor) {
            $this->clearMemberStaleState($member);

            return BalanceAnchor::create([
                'account_id' => $member->account_id,
                'user_id' => $member->id,
                'created_by' => $actor->id,
                'amount' => $amount,
                'as_of_date' => $asOfDate,
                'source' => $source,
                'checkin_month' => $checkinMonth,
            ]);
        });
    }
}
