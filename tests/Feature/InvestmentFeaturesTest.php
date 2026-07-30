<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DefaultCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_investment_uses_system_category_and_is_separate_on_dashboard(): void
    {
        $this->travelTo('2026-07-20');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 100,
                'description' => 'Mercado',
                'category_id' => $category->id,
                'date' => '2026-07-10',
                'payment_method' => Transaction::PAYMENT_CASH,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_INVESTMENT,
                'amount' => 500,
                'description' => 'Aporte Tesouro',
                'date' => '2026-07-15',
                'payment_method' => Transaction::PAYMENT_PIX,
                'category_id' => $category->id,
            ])
            ->assertRedirect(route('transactions.index'));

        $investment = Transaction::withoutGlobalScopes()
            ->where('description', 'Aporte Tesouro')
            ->first();

        $this->assertNotNull($investment);
        $this->assertSame(Transaction::TYPE_INVESTMENT, $investment->type);
        $this->assertNull($investment->payment_card_id);
        $this->assertNull($investment->credit_card_invoice_id);

        $investCategory = Category::withoutGlobalScopes()->find($investment->category_id);
        $this->assertSame(DefaultCategoryService::INVESTMENT_NAME, $investCategory?->name);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.expense', 100)
                ->where('summary.investments', 500)
                ->where('summary.month_balance', -600)
                ->where('summary.expense_debit', 100)
                ->where('summary.expense_credit', 0)
                ->where('balanceMeta.needs_initial', true)
                ->where('summary.balance', null)
            );
    }

    public function test_expense_can_be_converted_to_investment_on_edit(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 200,
                'description' => 'Poupança errada',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CASH,
            ])
            ->assertRedirect();

        $expense = Transaction::withoutGlobalScopes()->where('description', 'Poupança errada')->first();

        $this->actingAs($owner)
            ->put(route('transactions.update', $expense), [
                'type' => Transaction::TYPE_INVESTMENT,
                'amount' => 200,
                'description' => 'Aporte CDB',
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_TRANSFER,
            ])
            ->assertRedirect(route('transactions.index'));

        $expense->refresh();
        $this->assertSame(Transaction::TYPE_INVESTMENT, $expense->type);
        $this->assertSame('Aporte CDB', $expense->description);
        $this->assertSame(
            DefaultCategoryService::INVESTMENT_NAME,
            Category::withoutGlobalScopes()->find($expense->category_id)?->name
        );
    }

    public function test_investment_rejects_card_payment_method(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_INVESTMENT,
                'amount' => 50,
                'description' => 'Inválido',
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CARD,
            ])
            ->assertSessionHasErrors('payment_method');
    }
}
