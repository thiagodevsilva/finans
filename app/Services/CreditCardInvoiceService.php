<?php

namespace App\Services;

use App\Models\CreditCardInvoice;
use App\Models\PaymentCard;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CreditCardInvoiceService
{
    public function resolveForPurchase(PaymentCard $card, CarbonInterface|string $purchaseDate): ?CreditCardInvoice
    {
        if ($card->type !== PaymentCard::TYPE_CREDIT || ! $card->closing_day || ! $card->due_day) {
            return null;
        }

        $date = Carbon::parse($purchaseDate)->startOfDay();
        $closingDate = $this->closingDateForPurchase($card, $date);
        $dueDate = $this->dueDateForClosing($card, $closingDate);
        $closing = $closingDate->toDateString();

        $existing = CreditCardInvoice::query()
            ->where('payment_card_id', $card->id)
            ->whereDate('closing_date', $closing)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return CreditCardInvoice::query()->create([
                'payment_card_id' => $card->id,
                'closing_date' => $closing,
                'account_id' => $card->account_id,
                'due_date' => $dueDate->toDateString(),
                'status' => CreditCardInvoice::STATUS_OPEN,
                'paid_amount' => 0,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return CreditCardInvoice::query()
                ->where('payment_card_id', $card->id)
                ->whereDate('closing_date', $closing)
                ->firstOrFail();
        }
    }

    public function closingDateForPurchase(PaymentCard $card, CarbonInterface $purchaseDate): Carbon
    {
        $year = (int) $purchaseDate->year;
        $month = (int) $purchaseDate->month;

        if ((int) $purchaseDate->day >= (int) $card->closing_day) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return $this->clampDay($year, $month, (int) $card->closing_day);
    }

    public function dueDateForClosing(PaymentCard $card, CarbonInterface $closingDate): Carbon
    {
        $year = (int) $closingDate->year;
        $month = (int) $closingDate->month;
        $dueDay = (int) $card->due_day;

        if ($dueDay <= (int) $card->closing_day) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return $this->clampDay($year, $month, $dueDay);
    }

    public function refreshStatus(CreditCardInvoice $invoice): void
    {
        $total = (float) $invoice->charges()->sum('amount');
        $paid = (float) $invoice->paid_amount;
        $closed = $invoice->closing_date->endOfDay()->isPast();

        if ($total <= 0 && $paid <= 0) {
            $status = $closed ? CreditCardInvoice::STATUS_CLOSED : CreditCardInvoice::STATUS_OPEN;
        } elseif ($paid <= 0) {
            $status = $closed ? CreditCardInvoice::STATUS_CLOSED : CreditCardInvoice::STATUS_OPEN;
        } elseif ($paid + 0.001 >= $total) {
            $status = CreditCardInvoice::STATUS_PAID;
        } else {
            $status = CreditCardInvoice::STATUS_PARTIAL;
        }

        $invoice->update(['status' => $status]);
    }

    public function ensureUpcomingInvoices(PaymentCard $card, int $monthsAhead = 2): void
    {
        if ($card->type !== PaymentCard::TYPE_CREDIT || ! $card->closing_day || ! $card->due_day) {
            return;
        }

        $cursor = now()->startOfMonth();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $probe = $cursor->copy()->day(min((int) $card->closing_day, $cursor->daysInMonth));
            $this->resolveForPurchase($card, $probe);
            $cursor->addMonthNoOverflow();
        }
    }

    public function resolvePayableInvoice(PaymentCard $card): ?CreditCardInvoice
    {
        if ($card->type !== PaymentCard::TYPE_CREDIT) {
            return null;
        }

        $this->ensureUpcomingInvoices($card);

        $invoices = CreditCardInvoice::query()
            ->where('payment_card_id', $card->id)
            ->whereIn('status', [
                CreditCardInvoice::STATUS_OPEN,
                CreditCardInvoice::STATUS_CLOSED,
                CreditCardInvoice::STATUS_PARTIAL,
            ])
            ->orderBy('due_date')
            ->get();

        return $invoices->first(fn (CreditCardInvoice $invoice) => $invoice->remainingAmount() > 0)
            ?? $invoices->first();
    }

    private function clampDay(int $year, int $month, int $day): Carbon
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, min($day, $daysInMonth))->startOfDay();
    }
}
