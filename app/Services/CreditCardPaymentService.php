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

            $dueLabel = $invoice->due_date?->format('d/m/Y');
            $description = 'Pagamento de fatura · '.$card->name;
            if ($dueLabel) {
                $description .= ' · venc. '.$dueLabel;
            }

            $transaction = Transaction::create([
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'category_id' => $category->id,
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => $amount,
                'description' => $description,
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
}
