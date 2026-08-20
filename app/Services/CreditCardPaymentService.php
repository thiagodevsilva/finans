<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CreditCardInvoice;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditCardPaymentService
{
    public function __construct(
        private readonly CreditCardInvoiceService $invoiceService,
        private readonly DefaultCategoryService $categoryService,
    ) {}

    public function pay(
        User $user,
        CreditCardInvoice $invoice,
        float $amount,
        string $date,
        string $paymentMethod,
        ?BankAccount $bankAccount = null,
        ?string $description = null
    ): Transaction {
        return DB::transaction(function () use ($user, $invoice, $amount, $date, $paymentMethod, $bankAccount) {
            $card = PaymentCard::query()->findOrFail($invoice->payment_card_id);
            $category = $this->categoryService->ensureInvoicePaymentCategory(
                $user->account
            );

            $transaction = Transaction::create([
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'category_id' => $category->id,
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => $amount,
                'description' => $this->descriptionFor($card, $invoice),
                'date' => $date,
                'payment_method' => $paymentMethod,
                'payment_card_id' => $card->id,
                'bank_account_id' => $bankAccount?->id,
                'credit_card_invoice_id' => $invoice->id,
                'status' => Transaction::STATUS_CONFIRMED,
            ]);

            $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);
            $invoice->save();

            $this->invoiceService->refreshStatus($invoice->fresh());

            return $transaction;
        });
    }

    /**
     * Atualiza pagamento de fatura (valor, data, cartão, fatura de referência).
     */
    public function update(
        Transaction $payment,
        CreditCardInvoice $invoice,
        float $amount,
        string $date,
        string $paymentMethod,
        ?BankAccount $bankAccount = null,
    ): Transaction {
        if ($payment->type !== Transaction::TYPE_TRANSFER) {
            throw new \InvalidArgumentException('Somente pagamentos de fatura podem ser atualizados por este serviço.');
        }

        return DB::transaction(function () use ($payment, $invoice, $amount, $date, $paymentMethod, $bankAccount) {
            $previousInvoiceId = $payment->credit_card_invoice_id;
            $previousAmount = (float) $payment->amount;

            if ($previousInvoiceId) {
                $previous = CreditCardInvoice::query()->find($previousInvoiceId);
                if ($previous) {
                    $previous->paid_amount = round(max(0, (float) $previous->paid_amount - $previousAmount), 2);
                    $previous->save();
                    $this->invoiceService->refreshStatus($previous->fresh());
                }
            }

            $card = PaymentCard::query()->findOrFail($invoice->payment_card_id);
            $account = $payment->account
                ?? \App\Models\Account::query()->findOrFail($payment->account_id);
            $category = $this->categoryService->ensureInvoicePaymentCategory($account);

            $payment->update([
                'category_id' => $category->id,
                'amount' => $amount,
                'description' => $this->descriptionFor($card, $invoice),
                'date' => $date,
                'payment_method' => $paymentMethod,
                'payment_card_id' => $card->id,
                'bank_account_id' => $bankAccount?->id,
                'credit_card_invoice_id' => $invoice->id,
            ]);

            $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);
            $invoice->save();
            $this->invoiceService->refreshStatus($invoice->fresh());

            return $payment->fresh();
        });
    }

    public function delete(Transaction $payment): void
    {
        if ($payment->type !== Transaction::TYPE_TRANSFER) {
            $payment->delete();

            return;
        }

        DB::transaction(function () use ($payment) {
            if ($payment->credit_card_invoice_id) {
                $invoice = CreditCardInvoice::query()->find($payment->credit_card_invoice_id);
                if ($invoice) {
                    $invoice->paid_amount = round(
                        max(0, (float) $invoice->paid_amount - (float) $payment->amount),
                        2
                    );
                    $invoice->save();
                    $this->invoiceService->refreshStatus($invoice->fresh());
                }
            }

            $payment->delete();
        });
    }

    /**
     * Realinha um pagamento ao ciclo sugerido pela data (opcional / manutenção).
     */
    public function realignToPaymentDate(Transaction $payment): ?CreditCardInvoice
    {
        if (
            $payment->type !== Transaction::TYPE_TRANSFER
            || ! $payment->credit_card_invoice_id
            || ! $payment->payment_card_id
        ) {
            return null;
        }

        $card = PaymentCard::query()->find($payment->payment_card_id);
        if (! $card || $card->type !== PaymentCard::TYPE_CREDIT) {
            return null;
        }

        $target = $this->invoiceService->suggestInvoiceForPayment($card, $payment->date);
        if (! $target || $target->id === $payment->credit_card_invoice_id) {
            return null;
        }

        $this->update(
            $payment,
            $target,
            (float) $payment->amount,
            $payment->date->toDateString(),
            $payment->payment_method,
            $payment->bank_account_id
                ? BankAccount::query()->find($payment->bank_account_id)
                : null,
        );

        return $target->fresh();
    }

    protected function descriptionFor(PaymentCard $card, CreditCardInvoice $invoice): string
    {
        $description = 'Pagamento de fatura · '.$card->name;
        $dueLabel = $invoice->due_date?->format('d/m/Y');
        if ($dueLabel) {
            $description .= ' · venc. '.$dueLabel;
        }

        return $description;
    }
}
