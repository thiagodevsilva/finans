<?php

namespace Tests\Unit;

use App\Models\PaymentCard;
use App\Services\CreditCardInvoiceService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Testes puros (sem banco): só a regra de ciclo da fatura.
 */
class CreditCardInvoiceServiceTest extends TestCase
{
    public function test_purchase_before_closing_falls_on_current_cycle(): void
    {
        $service = new CreditCardInvoiceService;
        $card = $this->fakeCard(10, 17);

        $closing = $service->closingDateForPurchase($card, Carbon::parse('2026-07-05'));
        $due = $service->dueDateForClosing($card, $closing);

        $this->assertSame('2026-07-10', $closing->toDateString());
        $this->assertSame('2026-07-17', $due->toDateString());
    }

    public function test_purchase_after_closing_falls_on_next_cycle(): void
    {
        $service = new CreditCardInvoiceService;
        $card = $this->fakeCard(10, 17);

        $closing = $service->closingDateForPurchase($card, Carbon::parse('2026-07-15'));
        $due = $service->dueDateForClosing($card, $closing);

        $this->assertSame('2026-08-10', $closing->toDateString());
        $this->assertSame('2026-08-17', $due->toDateString());
    }

    public function test_payment_uses_same_cycle_rule_as_purchase(): void
    {
        $service = new CreditCardInvoiceService;
        $card = $this->fakeCard(15, 20);

        // Antes do fechamento → ciclo atual (vence no mês corrente).
        $before = $service->closingDateForPurchase($card, Carbon::parse('2026-08-10'));
        $this->assertSame('2026-08-15', $before->toDateString());
        $this->assertSame('2026-08-20', $service->dueDateForClosing($card, $before)->toDateString());

        // No dia do fechamento ou depois → ciclo seguinte.
        $after = $service->closingDateForPurchase($card, Carbon::parse('2026-08-16'));
        $this->assertSame('2026-09-15', $after->toDateString());
        $this->assertSame('2026-09-20', $service->dueDateForClosing($card, $after)->toDateString());
    }

    public function test_due_day_before_closing_moves_to_next_month(): void
    {
        $service = new CreditCardInvoiceService;
        $card = $this->fakeCard(25, 5);

        $closing = $service->closingDateForPurchase($card, Carbon::parse('2026-07-10'));
        $due = $service->dueDateForClosing($card, $closing);

        $this->assertSame('2026-07-25', $closing->toDateString());
        $this->assertSame('2026-08-05', $due->toDateString());
    }

    private function fakeCard(int $closingDay, int $dueDay): PaymentCard
    {
        $card = new PaymentCard;
        $card->type = PaymentCard::TYPE_CREDIT;
        $card->closing_day = $closingDay;
        $card->due_day = $dueDay;

        return $card;
    }
}
