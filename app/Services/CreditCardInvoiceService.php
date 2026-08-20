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

    /**
     * Fatura do pagamento: mesma regra da compra.
     * Antes do fechamento → ciclo atual; no dia do fechamento ou depois → ciclo seguinte.
     * (Não usa “fatura aberta mais antiga” — pagar o cartão a qualquer momento
     * não significa quitar a fatura atrasada automaticamente.)
     */
    public function resolvePayableInvoice(
        PaymentCard $card,
        CarbonInterface|string|null $paymentDate = null,
    ): ?CreditCardInvoice {
        if ($card->type !== PaymentCard::TYPE_CREDIT) {
            return null;
        }

        return $this->resolveForPurchase($card, $paymentDate ?? now());
    }

    /**
     * Sugestão de fatura para o pagamento (mesma regra da compra).
     * A UI pode sobrescrever — o usuário escolhe qual fatura está pagando.
     */
    public function suggestInvoiceForPayment(
        PaymentCard $card,
        CarbonInterface|string|null $paymentDate = null,
    ): ?CreditCardInvoice {
        return $this->resolvePayableInvoice($card, $paymentDate);
    }

    /**
     * Opções de fatura para o formulário de pagamento (passados + próximos ciclos).
     *
     * @return list<array{id: string, label: string, due_date: string, closing_date: string, month_key: string}>
     */
    public function invoiceOptionsForCard(PaymentCard $card, int $monthsBack = 4, int $monthsAhead = 2): array
    {
        if ($card->type !== PaymentCard::TYPE_CREDIT || ! $card->closing_day || ! $card->due_day) {
            return [];
        }

        $this->ensureUpcomingInvoices($card, $monthsAhead);

        $cursor = now()->startOfMonth()->subMonthsNoOverflow($monthsBack);
        $end = now()->startOfMonth()->addMonthsNoOverflow($monthsAhead);

        while ($cursor->lte($end)) {
            $probe = $cursor->copy()->day(min((int) $card->closing_day, $cursor->daysInMonth));
            $this->resolveForPurchase($card, $probe);
            $cursor->addMonthNoOverflow();
        }

        return CreditCardInvoice::query()
            ->where('payment_card_id', $card->id)
            ->orderByDesc('due_date')
            ->limit($monthsBack + $monthsAhead + 2)
            ->get()
            ->map(fn (CreditCardInvoice $invoice) => $this->invoiceOptionPayload($invoice))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, label: string, due_date: string, closing_date: string, month_key: string}
     */
    public function invoiceOptionPayload(CreditCardInvoice $invoice): array
    {
        $due = $invoice->due_date;
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        $monthName = $months[(int) $due->month] ?? $due->format('m');

        return [
            'id' => $invoice->id,
            'label' => sprintf(
                '%s/%d · venc. %s',
                $monthName,
                (int) $due->year,
                $due->format('d/m/Y'),
            ),
            'due_date' => $due->toDateString(),
            'closing_date' => $invoice->closing_date->toDateString(),
            'month_key' => $due->format('Y-m'),
        ];
    }

    private function clampDay(int $year, int $month, int $day): Carbon
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, min($day, $daysInMonth))->startOfDay();
    }
}
