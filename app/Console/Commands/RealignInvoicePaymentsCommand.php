<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\CreditCardPaymentService;
use Illuminate\Console\Command;

class RealignInvoicePaymentsCommand extends Command
{
    protected $signature = 'invoices:realign-payments
                            {--dry-run : Só lista o que mudaria, sem gravar}
                            {--account= : Limita a um account_id}';

    protected $description = 'Realinha pagamentos de fatura ao ciclo da data (regra do fechamento)';

    public function handle(CreditCardPaymentService $payments): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $accountId = $this->option('account');

        $query = Transaction::withoutGlobalScopes()
            ->where('type', Transaction::TYPE_TRANSFER)
            ->whereNotNull('credit_card_invoice_id')
            ->whereNotNull('payment_card_id')
            ->orderBy('date')
            ->orderBy('created_at');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $moved = 0;
        $checked = 0;

        $query->chunkById(100, function ($chunk) use ($payments, $dryRun, &$moved, &$checked) {
            foreach ($chunk as $payment) {
                $checked++;

                if ($dryRun) {
                    $card = $payment->paymentCard()->withoutGlobalScopes()->first();
                    if (! $card) {
                        continue;
                    }

                    $target = app(\App\Services\CreditCardInvoiceService::class)
                        ->resolveForPurchase($card, $payment->date);

                    if ($target && $target->id !== $payment->credit_card_invoice_id) {
                        $this->line(sprintf(
                            '[dry-run] %s R$ %s (%s) %s → venc. %s',
                            $payment->id,
                            $payment->amount,
                            $payment->date?->toDateString(),
                            $payment->description,
                            $target->due_date?->toDateString(),
                        ));
                        $moved++;
                    }

                    continue;
                }

                $target = $payments->realignToPaymentDate($payment);
                if ($target) {
                    $this->line(sprintf(
                        'OK %s → venc. %s (%s)',
                        $payment->id,
                        $target->due_date?->toDateString(),
                        $payment->fresh()->description,
                    ));
                    $moved++;
                }
            }
        });

        $this->info(($dryRun ? 'Simulados' : 'Realinhados').": {$moved} de {$checked} pagamentos.");

        return self::SUCCESS;
    }
}
