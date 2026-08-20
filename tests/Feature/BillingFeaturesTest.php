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
                'payment_method' => Transaction::PAYMENT_PIX,
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
        $this->assertNotNull($payment->category_id);
        $categoryName = Category::withoutGlobalScopes()->find($payment->category_id)?->name;
        $this->assertSame('Fatura cartão', $categoryName);
        $this->assertStringStartsWith('Pagamento de fatura · Nubank', $payment->description);

        // Competência: compra no crédito. Caixa: pagamento da fatura. Pagamento não é despesa.
        $this->travelTo('2026-07-20');

        $response = $this->actingAs($owner)->get(route('dashboard', [
            'month' => 7,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.expense', 50)
            ->where('summary.expense_credit', 50)
            ->where('summary.expense_debit', 0)
            ->where('summary.card_payments', 50)
            ->where('summary.month_balance', 0)
            ->where('summary.income', 0)
            ->where('balanceMeta.needs_initial', true)
            ->where('summary.balance', null)
        );
    }

    public function test_dashboard_separates_accrual_from_cash_and_future_invoices(): void
    {
        $this->travelTo('2026-07-25');

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
            'name' => 'NuBank',
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
                'amount' => 457,
                'description' => 'Conta de Luz',
                'category_id' => $category->id,
                'date' => '2026-07-24',
                'payment_method' => Transaction::PAYMENT_PIX,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 15,
                'description' => 'Bolacha',
                'category_id' => $category->id,
                'date' => '2026-07-01',
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 10,
                'description' => 'Chocolate',
                'category_id' => $category->id,
                'date' => '2026-07-25',
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
            ])
            ->assertRedirect();

        $bolacha = Transaction::withoutGlobalScopes()->where('description', 'Bolacha')->first();
        $chocolate = Transaction::withoutGlobalScopes()->where('description', 'Chocolate')->first();
        $currentInvoice = CreditCardInvoice::withoutGlobalScopes()->find($bolacha->credit_card_invoice_id);
        $futureInvoice = CreditCardInvoice::withoutGlobalScopes()->find($chocolate->credit_card_invoice_id);

        $this->assertSame('2026-07-10', $currentInvoice->closing_date->toDateString());
        $this->assertSame('2026-08-10', $futureInvoice->closing_date->toDateString());

        $this->actingAs($owner)
            ->post(route('credit-card-invoices.pay', $currentInvoice), [
                'payment_method' => Transaction::PAYMENT_PIX,
                'bank_account_id' => $bank->id,
                'amount' => 1000,
                'date' => '2026-07-25',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.expense', 482)
                ->where('summary.expense_credit', 25)
                ->where('summary.expense_debit', 457)
                ->where('summary.card_payments', 1000)
                ->where('summary.month_balance', -457)
                ->where('balanceMeta.needs_initial', true)
                ->where('summary.balance', null)
            );
    }

    public function test_purchase_on_closing_day_belongs_to_next_invoice(): void
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
            'closing_day' => 10,
            'due_day' => 17,
        ]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 20,
                'description' => 'No fechamento',
                'category_id' => $category->id,
                'date' => '2026-07-10',
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
            ])
            ->assertRedirect();

        $expense = Transaction::withoutGlobalScopes()->where('description', 'No fechamento')->first();
        $invoice = CreditCardInvoice::withoutGlobalScopes()->find($expense->credit_card_invoice_id);

        $this->assertSame('2026-08-10', $invoice->closing_date->toDateString());
    }

    public function test_partial_invoice_payments_are_allowed_same_month(): void
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
            'name' => 'NuBank',
            'brand' => 'mastercard',
            'type' => 'credit',
            'color' => '#820ad1',
            'closing_day' => 10,
            'due_day' => 17,
        ]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 100,
                'description' => 'Compra',
                'category_id' => $category->id,
                'date' => '2026-07-05',
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
            ])
            ->assertRedirect();

        $invoice = CreditCardInvoice::withoutGlobalScopes()
            ->where('payment_card_id', $card->id)
            ->first();

        $this->actingAs($owner)
            ->post(route('credit-card-invoices.pay', $invoice), [
                'payment_method' => Transaction::PAYMENT_PIX,
                'bank_account_id' => $bank->id,
                'amount' => 40,
                'date' => '2026-07-18',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('credit-card-invoices.pay', $invoice), [
                'payment_method' => Transaction::PAYMENT_PIX,
                'bank_account_id' => $bank->id,
                'amount' => 60,
                'date' => '2026-07-20',
            ])
            ->assertRedirect();

        $payments = Transaction::withoutGlobalScopes()
            ->where('type', Transaction::TYPE_TRANSFER)
            ->where('credit_card_invoice_id', $invoice->id)
            ->orderBy('date')
            ->get();

        $this->assertCount(2, $payments);
        $this->assertEquals(40, (float) $payments[0]->amount);
        $this->assertEquals(60, (float) $payments[1]->amount);
        $this->assertSame(
            Category::withoutGlobalScopes()->find($payments[0]->category_id)?->name,
            Category::withoutGlobalScopes()->find($payments[1]->category_id)?->name
        );
        $this->assertSame('Fatura cartão', Category::withoutGlobalScopes()->find($payments[0]->category_id)?->name);

        $invoice->refresh();
        $this->assertSame(CreditCardInvoice::STATUS_PAID, $invoice->status);
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
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'description' => 'Notebook',
                'category_id' => $category->id,
                'date' => '2026-07-01',
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
                'is_installment' => true,
                'total_amount' => 100,
                'installments_count' => 3,
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

    public function test_recurring_bills_index_groups_pending_and_paid_with_period_summary(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

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
                'start_date' => '2026-07-01',
            ])
            ->assertRedirect();

        $bill = RecurringBill::withoutGlobalScopes()->first();
        $this->assertNotNull($bill);

        $augustPlanned = Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->whereBetween('date', ['2026-08-01', '2026-08-31'])
            ->first();

        $this->assertNotNull($augustPlanned);

        $this->actingAs($owner)
            ->post(route('recurring-transactions.confirm', $augustPlanned), [
                'amount' => 125,
                'date' => $augustPlanned->date->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('recurring-bills.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RecurringBills/Index')
                ->has('paid', 1)
                ->where('paid.0.amount', 125)
                ->where('paid.0.status', Transaction::STATUS_CONFIRMED)
                ->where('periodSummary.current.month', '2026-08')
                ->where('periodSummary.current.paid_count', 1)
                ->where('periodSummary.current.paid_amount', 125)
                ->where('periodSummary.next.month', '2026-09')
                ->where('periodSummary.next.pending_count', 1)
                ->where('periodSummary.next.pending_amount', 120)
            );
    }

    public function test_expense_form_can_confirm_planned_recurring_bill(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Luz',
                'category_id' => $category->id,
                'estimated_amount' => 200,
                'day_of_month' => 10,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => now()->startOfMonth()->toDateString(),
            ])
            ->assertRedirect();

        $planned = Transaction::withoutGlobalScopes()
            ->where('description', 'Luz')
            ->where('status', Transaction::STATUS_PLANNED)
            ->first();

        $this->assertNotNull($planned);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 210,
                'description' => 'Luz',
                'category_id' => $category->id,
                'date' => $planned->date->toDateString(),
                'payment_method' => Transaction::PAYMENT_PIX,
                'recurring_transaction_id' => $planned->id,
            ])
            ->assertRedirect(route('transactions.index'));

        $planned->refresh();
        $this->assertSame(Transaction::STATUS_CONFIRMED, $planned->status);
        $this->assertEquals(210, (float) $planned->amount);
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
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'description' => 'TV',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
                'is_installment' => true,
                'total_amount' => 300,
                'installments_count' => 3,
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
                'payment_method' => Transaction::PAYMENT_TRANSFER,
                'bank_account_id' => $bankA->id,
                'amount' => 10,
                'date' => now()->toDateString(),
            ])
            ->assertNotFound();
    }

    public function test_transfer_payment_follows_closing_cycle_not_oldest_unpaid(): void
    {
        $this->travelTo('2026-08-16 12:00:00');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $bank = BankAccount::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Mercado Pago',
            'color' => '#00a0e3',
        ]);
        $card = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Mercado Pago',
            'brand' => 'mastercard',
            'type' => 'credit',
            'color' => '#00a0e3',
            'closing_day' => 15,
            'due_day' => 20,
        ]);

        // Fatura de julho ainda aberta com saldo — o bug antigo jogava o pagamento nela.
        $julyInvoice = CreditCardInvoice::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'payment_card_id' => $card->id,
            'closing_date' => '2026-07-15',
            'due_date' => '2026-07-20',
            'status' => CreditCardInvoice::STATUS_CLOSED,
            'paid_amount' => 0,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 5000,
            'description' => 'Compra julho',
            'date' => '2026-07-10',
            'payment_method' => Transaction::PAYMENT_CARD,
            'payment_card_id' => $card->id,
            'credit_card_invoice_id' => $julyInvoice->id,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $suggested = app(\App\Services\CreditCardInvoiceService::class)
            ->suggestInvoiceForPayment($card, '2026-08-16');

        // Usuário pode escolher a fatura de julho mesmo com sugestão de setembro.
        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => 1000,
                'date' => '2026-08-16',
                'payment_method' => Transaction::PAYMENT_PIX,
                'payment_card_id' => $card->id,
                'bank_account_id' => $bank->id,
                'credit_card_invoice_id' => $julyInvoice->id,
            ])
            ->assertRedirect();

        $payment = Transaction::withoutGlobalScopes()
            ->where('type', Transaction::TYPE_TRANSFER)
            ->where('payment_card_id', $card->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame($julyInvoice->id, $payment->credit_card_invoice_id);
        $this->assertStringContainsString('venc. 20/07/2026', $payment->description);
        $this->assertSame('2026-09-15', $suggested->closing_date->toDateString());

        $julyInvoice->refresh();
        $this->assertSame(1000.0, (float) $julyInvoice->paid_amount);
    }

    public function test_user_can_edit_invoice_payment_reference(): void
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
            'name' => 'Mercado Pago',
            'brand' => 'mastercard',
            'type' => 'credit',
            'color' => '#00a0e3',
            'closing_day' => 15,
            'due_day' => 20,
        ]);

        $july = CreditCardInvoice::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'payment_card_id' => $card->id,
            'closing_date' => '2026-07-15',
            'due_date' => '2026-07-20',
            'status' => CreditCardInvoice::STATUS_PARTIAL,
            'paid_amount' => 500,
        ]);
        $august = CreditCardInvoice::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'payment_card_id' => $card->id,
            'closing_date' => '2026-08-15',
            'due_date' => '2026-08-20',
            'status' => CreditCardInvoice::STATUS_OPEN,
            'paid_amount' => 0,
        ]);

        $payment = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 500,
            'description' => 'Pagamento de fatura · Mercado Pago · venc. 20/07/2026',
            'date' => '2026-08-22',
            'payment_method' => Transaction::PAYMENT_PIX,
            'payment_card_id' => $card->id,
            'bank_account_id' => $bank->id,
            'credit_card_invoice_id' => $july->id,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $this->actingAs($owner)
            ->get(route('transactions.edit', $payment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Transactions/Form')
                ->where('transaction.id', $payment->id)
                ->where('transaction.credit_card_invoice_id', $july->id)
            );

        $this->actingAs($owner)
            ->put(route('transactions.update', $payment), [
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => 500,
                'date' => '2026-08-22',
                'payment_method' => Transaction::PAYMENT_PIX,
                'payment_card_id' => $card->id,
                'bank_account_id' => $bank->id,
                'credit_card_invoice_id' => $august->id,
            ])
            ->assertRedirect();

        $payment->refresh();
        $july->refresh();
        $august->refresh();

        $this->assertSame($august->id, $payment->credit_card_invoice_id);
        $this->assertStringContainsString('venc. 20/08/2026', $payment->description);
        $this->assertSame(0.0, (float) $july->paid_amount);
        $this->assertSame(500.0, (float) $august->paid_amount);
    }

    public function test_realign_moves_misassigned_payment_to_closing_cycle(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $card = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Mercado Pago',
            'brand' => 'mastercard',
            'type' => 'credit',
            'color' => '#00a0e3',
            'closing_day' => 15,
            'due_day' => 20,
        ]);

        $wrong = CreditCardInvoice::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'payment_card_id' => $card->id,
            'closing_date' => '2026-07-15',
            'due_date' => '2026-07-20',
            'status' => CreditCardInvoice::STATUS_PARTIAL,
            'paid_amount' => 4362.60,
        ]);

        $payment = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 4362.60,
            'description' => 'Pagamento de fatura · Mercado Pago · venc. 20/07/2026',
            'date' => '2026-08-16',
            'payment_method' => Transaction::PAYMENT_PIX,
            'payment_card_id' => $card->id,
            'credit_card_invoice_id' => $wrong->id,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $this->actingAs($owner);
        $target = app(\App\Services\CreditCardPaymentService::class)->realignToPaymentDate($payment);

        $this->assertNotNull($target);
        $this->assertSame('2026-09-20', $target->due_date->toDateString());
        $this->assertSame(4362.60, (float) $target->paid_amount);

        $wrong->refresh();
        $payment->refresh();
        $this->assertSame(0.0, (float) $wrong->paid_amount);
        $this->assertSame($target->id, $payment->credit_card_invoice_id);
        $this->assertStringContainsString('venc. 20/09/2026', $payment->description);
    }
}
