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
        private readonly CreditCardInvoiceService $invoiceService
    ) {}

    public function pay(
        User $user,
        CreditCardInvoice $invoice,
        BankAccount $bankAccount,
        float $amount,
        string $date,
        ?string $description = null
    ): Transaction {
        return DB::transaction(function () use ($user, $invoice, $bankAccount, $amount, $date, $description) {
            $card = PaymentCard::query()->findOrFail($invoice->payment_card_id);

            $transaction = Transaction::create([
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'category_id' => null,
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => $amount,
                'description' => $description ?: ('Pagamento de fatura · '.$card->name),
                'date' => $date,
                'payment_method' => null,
                'payment_card_id' => $card->id,
                'bank_account_id' => $bankAccount->id,
                'credit_card_invoice_id' => $invoice->id,
                'status' => Transaction::STATUS_CONFIRMED,
            ]);

            $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);
            $invoice->save();

            $this->invoiceService->refreshStatus($invoice->fresh());

            return $transaction;
        });
    }
}
