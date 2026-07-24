<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\CreditCardInvoice;
use App\Models\InstallmentPlan;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_card_purchase_assigns_invoice_and_payment_does_not_count_as_expense(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $bank = BankAccount::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Conta',
            'color' => '#2563eb',
        ]);
        $card = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Nubank',
            'brand' => 'mastercard',
            'type' => 'credit',
            'last_four' => '1234',
            'color' => '#820ad1',
            'closing_day' => 10,
            'due_day' => 17,
        ]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 50,
                'description' => 'Mercado',
                'category_id' => $category->id,
                'date' => '2026-07-05',
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
            ])
            ->assertRedirect(route('transactions.index'));

        $expense = Transaction::withoutGlobalScopes()->where('description', 'Mercado')->first();
        $this->assertNotNull($expense->credit_card_invoice_id);

        $invoice = CreditCardInvoice::withoutGlobalScopes()->find($expense->credit_card_invoice_id);
        $this->assertSame('2026-07-10', $invoice->closing_date->toDateString());

        $this->actingAs($owner)
            ->post(route('credit-card-invoices.pay', $invoice), [
                'bank_account_id' => $bank->id,
                'amount' => 50,
                'date' => '2026-07-17',
            ])
            ->assertRedirect();

        $payment = Transaction::withoutGlobalScopes()
            ->where('type', Transaction::TYPE_TRANSFER)
            ->where('credit_card_invoice_id', $invoice->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertNull($payment->category_id);

        $response = $this->actingAs($owner)->get(route('dashboard', [
            'month' => 7,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.expense', 50)
            ->where('summary.income', 0)
        );
    }

    public function test_installment_plan_creates_monthly_expenses_with_cent_adjustment(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $card = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Visa',
            'brand' => 'visa',
            'type' => 'credit',
            'color' => '#ffc107',
            'closing_day' => 1,
            'due_day' => 10,
        ]);

        $this->actingAs($owner)
            ->post(route('installment-plans.store'), [
                'description' => 'Notebook',
                'category_id' => $category->id,
                'payment_card_id' => $card->id,
                'total_amount' => 100,
                'installments_count' => 3,
                'purchase_date' => '2026-07-01',
                'first_installment_date' => '2026-07-01',
            ])
            ->assertRedirect();

        $plan = InstallmentPlan::withoutGlobalScopes()->first();
        $this->assertNotNull($plan);

        $installments = Transaction::withoutGlobalScopes()
            ->where('installment_plan_id', $plan->id)
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $installments);
        $this->assertEquals(33.34, (float) $installments[0]->amount);
        $this->assertEquals(33.33, (float) $installments[1]->amount);
        $this->assertEquals(33.33, (float) $installments[2]->amount);
        $this->assertSame('Notebook (1/3)', $installments[0]->description);
    }

    public function test_recurring_bill_creates_planned_and_confirm_counts_as_expense(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Internet',
                'category_id' => $category->id,
                'estimated_amount' => 120,
                'day_of_month' => 15,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => now()->startOfMonth()->toDateString(),
            ])
            ->assertRedirect();

        $bill = RecurringBill::withoutGlobalScopes()->first();
        $this->assertNotNull($bill);

        $planned = Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->first();

        $this->assertNotNull($planned);

        $this->actingAs($owner)
            ->post(route('recurring-transactions.confirm', $planned), [
                'amount' => 135.50,
                'date' => $planned->date->toDateString(),
            ])
            ->assertRedirect();

        $planned->refresh();
        $this->assertSame(Transaction::STATUS_CONFIRMED, $planned->status);
        $this->assertEquals(135.50, (float) $planned->amount);

        $month = (int) $planned->date->month;
        $year = (int) $planned->date->year;

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => $month, 'year' => $year]))
            ->assertInertia(fn ($page) => $page->where('summary.expense', 135.5));
    }

    public function test_dependent_cannot_cancel_other_members_installment_plan(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $card = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Cartão',
            'brand' => 'visa',
            'type' => 'credit',
            'color' => '#ffc107',
            'closing_day' => 10,
            'due_day' => 17,
        ]);

        $this->actingAs($owner)
            ->post(route('installment-plans.store'), [
                'description' => 'TV',
                'category_id' => $category->id,
                'payment_card_id' => $card->id,
                'total_amount' => 300,
                'installments_count' => 3,
                'purchase_date' => now()->toDateString(),
                'first_installment_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $plan = InstallmentPlan::withoutGlobalScopes()->first();

        $this->actingAs($dependent)
            ->delete(route('installment-plans.destroy', $plan))
            ->assertForbidden();
    }

    public function test_users_cannot_pay_invoice_from_other_account(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $userA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $userB = User::factory()->owner()->create(['account_id' => $accountB->id]);
        $bankA = BankAccount::withoutGlobalScopes()->create([
            'account_id' => $accountA->id,
            'user_id' => $userA->id,
            'name' => 'Banco A',
            'color' => '#2563eb',
        ]);
        $cardB = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'user_id' => $userB->id,
            'name' => 'Cartão B',
            'brand' => 'visa',
            'type' => 'credit',
            'color' => '#111111',
            'closing_day' => 10,
            'due_day' => 17,
        ]);
        $invoiceB = CreditCardInvoice::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'payment_card_id' => $cardB->id,
            'closing_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => CreditCardInvoice::STATUS_OPEN,
            'paid_amount' => 0,
        ]);

        $this->actingAs($userA)
            ->post(route('credit-card-invoices.pay', $invoiceB), [
                'bank_account_id' => $bankA->id,
                'amount' => 10,
                'date' => now()->toDateString(),
            ])
            ->assertNotFound();
    }
}
