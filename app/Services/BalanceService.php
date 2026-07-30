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
    public function latestAnchor(?Carbon $at = null): ?BalanceAnchor
    {
        $query = BalanceAnchor::query()->orderByDesc('as_of_date')->orderByDesc('created_at');

        if ($at) {
            $query->whereDate('as_of_date', '<=', $at->toDateString());
        }

        return $query->first();
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
        $amount = $this->balanceAt($previousMonthEnd);

        if ($amount === null) {
            $amount = 0.0;
        }

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
     * Query de lançamentos que reduzem o caixa (saídas de dinheiro).
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
