<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContasCartoesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_recurring_bill_is_hard_deleted(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Luz',
                'category_id' => $category->id,
                'estimated_amount' => 100,
                'day_of_month' => 10,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => now()->startOfMonth()->toDateString(),
            ])
            ->assertRedirect();

        $bill = RecurringBill::withoutGlobalScopes()->first();
        $this->assertNotNull($bill);

        $this->actingAs($owner)
            ->delete(route('recurring-bills.destroy', $bill))
            ->assertRedirect();

        $this->assertDatabaseMissing('recurring_bills', ['id' => $bill->id]);
    }

    public function test_recurring_bill_with_confirmed_history_is_deactivated(): void
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
        $planned = Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->first();

        $this->actingAs($owner)
            ->post(route('recurring-transactions.confirm', $planned), [
                'amount' => 120,
                'date' => $planned->date->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->delete(route('recurring-bills.destroy', $bill))
            ->assertRedirect();

        $bill->refresh();
        $this->assertFalse($bill->active);
    }

    public function test_expense_can_link_to_recurring_bill_and_settle_planned(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Água',
                'category_id' => $category->id,
                'estimated_amount' => 80,
                'day_of_month' => now()->day,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => now()->startOfMonth()->toDateString(),
            ])
            ->assertRedirect();

        $bill = RecurringBill::withoutGlobalScopes()->first();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 85,
                'description' => 'Água',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_PIX,
                'recurring_bill_id' => $bill->id,
            ])
            ->assertRedirect(route('transactions.index'));

        $confirmed = Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->first();

        $this->assertNotNull($confirmed);
        $this->assertEquals(85, (float) $confirmed->amount);
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()
                ->where('recurring_bill_id', $bill->id)
                ->where('status', Transaction::STATUS_PLANNED)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->count()
        );
    }

    public function test_invoice_payment_via_transaction_form(): void
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
                'amount' => 40,
                'description' => 'Compra',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $card->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => 40,
                'date' => now()->toDateString(),
                'payment_card_id' => $card->id,
                'payment_method' => Transaction::PAYMENT_PIX,
                'bank_account_id' => $bank->id,
                'description' => 'Pagamento fatura',
            ])
            ->assertRedirect(route('payment-cards.index'));

        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 40,
            'payment_card_id' => $card->id,
            'payment_method' => Transaction::PAYMENT_PIX,
            'bank_account_id' => $bank->id,
        ]);
    }

    public function test_admin_can_access_admin_dashboard_and_family_user_cannot(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@levita.com',
        ]);
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($owner)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_last_seen_marks_user_online_within_29_minutes(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create([
            'account_id' => $account->id,
            'last_seen_at' => now()->subMinutes(10),
        ]);
        $offline = User::factory()->owner()->create([
            'account_id' => Account::factory(),
            'last_seen_at' => now()->subMinutes(40),
        ]);
        $admin = User::factory()->admin()->create();

        $this->assertTrue($owner->isOnline());
        $this->assertFalse($offline->isOnline());

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('onlineCount', 1)
            );
    }

    public function test_expense_can_be_converted_to_invoice_payment_on_edit(): void
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
                'amount' => 1000,
                'description' => 'Fatura nubank',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CASH,
            ])
            ->assertRedirect();

        $expense = Transaction::withoutGlobalScopes()->where('description', 'Fatura nubank')->first();

        $this->actingAs($owner)
            ->put(route('transactions.update', $expense), [
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => 1000,
                'date' => now()->toDateString(),
                'payment_card_id' => $card->id,
                'payment_method' => Transaction::PAYMENT_CARD,
                'description' => 'Pagamento fatura',
            ])
            ->assertRedirect(route('payment-cards.index'));

        $this->assertDatabaseMissing('transactions', ['id' => $expense->id]);
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 1000,
            'payment_card_id' => $card->id,
            'payment_method' => Transaction::PAYMENT_CARD,
            'bank_account_id' => null,
        ]);
    }

    public function test_recurring_bill_update_can_propagate_to_open_planned(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Luz',
                'category_id' => $category->id,
                'estimated_amount' => 100,
                'day_of_month' => 10,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => now()->startOfMonth()->toDateString(),
            ])
            ->assertRedirect();

        $bill = RecurringBill::withoutGlobalScopes()->first();

        $this->actingAs($owner)
            ->put(route('recurring-bills.update', $bill), [
                'description' => 'Conta de Luz',
                'category_id' => $category->id,
                'estimated_amount' => 150,
                'day_of_month' => 10,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => $bill->start_date->toDateString(),
                'propagate' => 'open',
            ])
            ->assertRedirect();

        $planned = Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->first();

        $this->assertSame('Conta de Luz', $planned->description);
        $this->assertEquals(150, (float) $planned->amount);
    }
}
