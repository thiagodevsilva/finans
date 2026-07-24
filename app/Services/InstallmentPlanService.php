<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InstallmentPlanService
{
    public function __construct(
        private readonly CreditCardInvoiceService $invoiceService
    ) {}

    public function create(User $user, array $data): InstallmentPlan
    {
        return DB::transaction(function () use ($user, $data) {
            $card = PaymentCard::query()->findOrFail($data['payment_card_id']);
            $count = (int) $data['installments_count'];
            $totalCents = (int) round(((float) $data['total_amount']) * 100);
            $baseCents = intdiv($totalCents, $count);
            $remainder = $totalCents - ($baseCents * $count);

            $plan = InstallmentPlan::create([
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'category_id' => $data['category_id'],
                'payment_card_id' => $card->id,
                'description' => $data['description'],
                'total_amount' => $data['total_amount'],
                'installments_count' => $count,
                'purchase_date' => $data['purchase_date'],
                'first_installment_date' => $data['first_installment_date'],
                'status' => InstallmentPlan::STATUS_ACTIVE,
            ]);

            $firstDate = Carbon::parse($data['first_installment_date']);

            for ($i = 1; $i <= $count; $i++) {
                $amountCents = $baseCents + ($i === 1 ? $remainder : 0);
                $date = $firstDate->copy()->addMonthsNoOverflow($i - 1);
                $invoice = $this->invoiceService->resolveForPurchase($card, $date);

                Transaction::create([
                    'account_id' => $user->account_id,
                    'user_id' => $user->id,
                    'category_id' => $data['category_id'],
                    'type' => Transaction::TYPE_EXPENSE,
                    'amount' => $amountCents / 100,
                    'description' => sprintf('%s (%d/%d)', $data['description'], $i, $count),
                    'date' => $date->toDateString(),
                    'payment_method' => Transaction::PAYMENT_CARD,
                    'payment_card_id' => $card->id,
                    'credit_card_invoice_id' => $invoice?->id,
                    'installment_plan_id' => $plan->id,
                    'installment_number' => $i,
                    'status' => Transaction::STATUS_CONFIRMED,
                ]);
            }

            return $plan->load('installments');
        });
    }

    public function cancelFuture(InstallmentPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan->installments()
                ->where('date', '>', now()->toDateString())
                ->delete();

            $remaining = $plan->installments()->count();

            $plan->update([
                'status' => $remaining > 0
                    ? InstallmentPlan::STATUS_COMPLETED
                    : InstallmentPlan::STATUS_CANCELLED,
            ]);
        });
    }
}
