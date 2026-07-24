<?php

namespace App\Services;

use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringBillService
{
    public function __construct(
        private readonly CreditCardInvoiceService $invoiceService
    ) {}

    public function create(User $user, array $data): RecurringBill
    {
        return DB::transaction(function () use ($user, $data) {
            $bill = RecurringBill::create([
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'category_id' => $data['category_id'],
                'description' => $data['description'],
                'estimated_amount' => $data['estimated_amount'],
                'day_of_month' => $data['day_of_month'],
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
        if (! $bill->active) {
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

    public function confirm(Transaction $transaction, float $amount, ?string $date = null): Transaction
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

        if (
            $transaction->payment_method === Transaction::PAYMENT_CARD
            && $transaction->payment_card_id
        ) {
            $card = PaymentCard::query()->find($transaction->payment_card_id);
            if ($card) {
                $invoice = $this->invoiceService->resolveForPurchase(
                    $card,
                    $updates['date'] ?? $transaction->date
                );
                $updates['credit_card_invoice_id'] = $invoice?->id;
            }
        }

        $transaction->update($updates);

        return $transaction->fresh();
    }

    public function skip(Transaction $transaction): void
    {
        $transaction->update(['status' => Transaction::STATUS_SKIPPED]);
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
