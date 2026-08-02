<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BalanceAnchor;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_needs_initial_anchor_without_balance(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_initial', true)
                ->where('balanceMeta.has_anchor', false)
                ->where('summary.balance', null)
            );
    }

    public function test_owner_can_create_initial_anchor_and_balance_recalculates(): void
    {
        $this->travelTo('2026-07-20');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $creditCard = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Crédito',
            'brand' => 'visa',
            'type' => PaymentCard::TYPE_CREDIT,
            'last_four' => '1111',
            'color' => '#820ad1',
            'closing_day' => 10,
            'due_day' => 17,
        ]);

        $this->actingAs($owner)
            ->post(route('balance-anchors.store'), [
                'amount' => 1000,
                'as_of_date' => '2026-07-10',
                'source' => BalanceAnchor::SOURCE_INITIAL,
            ])
            ->assertRedirect();

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 200,
            'description' => 'Extra',
            'date' => '2026-07-12',
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 50,
            'description' => 'PIX',
            'date' => '2026-07-13',
            'payment_method' => Transaction::PAYMENT_PIX,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 80,
            'description' => 'Crédito',
            'date' => '2026-07-14',
            'payment_method' => Transaction::PAYMENT_CARD,
            'payment_card_id' => $creditCard->id,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_INVESTMENT,
            'amount' => 100,
            'description' => 'Aporte',
            'date' => '2026-07-15',
            'payment_method' => Transaction::PAYMENT_DEBIT,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        // 1000 + 200 - 50 - 100 = 1050 (crédito 80 não reduz caixa)
        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_initial', false)
                ->where('summary.balance', 1050)
                ->where('summary.month_balance', 50) // 200 - 50(PIX) - 100(invest); crédito 80 não entra
                ->where('summary.expense_credit', 80)
                ->where('summary.expense_debit', 50)
                ->where('summary.investments', 100)
            );
    }

    public function test_debit_and_auto_debit_are_accepted_as_expense(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 33.5,
                'description' => 'Débito avulso',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_DEBIT,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 99,
                'description' => 'Débito automático',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_AUTO_DEBIT,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Débito avulso',
            'payment_method' => Transaction::PAYMENT_DEBIT,
        ]);
        $this->assertDatabaseHas('transactions', [
            'description' => 'Débito automático',
            'payment_method' => Transaction::PAYMENT_AUTO_DEBIT,
        ]);
    }

    public function test_monthly_keep_creates_checkin_anchor(): void
    {
        $this->travelTo('2026-08-02');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 500,
            'as_of_date' => '2026-07-01',
            'source' => BalanceAnchor::SOURCE_INITIAL,
            'checkin_month' => '2026-07',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_monthly_checkin', true)
                ->where('balanceMeta.previous_month_balance', 500)
            );

        $this->actingAs($owner)
            ->post(route('balance-anchors.keep'))
            ->assertRedirect();

        $this->assertDatabaseHas('balance_anchors', [
            'account_id' => $account->id,
            'source' => BalanceAnchor::SOURCE_MONTHLY_KEEP,
            'checkin_month' => '2026-08',
            'amount' => 500,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_monthly_checkin', false)
                ->where('summary.balance', 500)
            );
    }

    public function test_dependent_cannot_create_balance_anchor(): void
    {
        $account = Account::factory()->create();
        User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);

        $this->actingAs($dependent)
            ->post(route('balance-anchors.store'), [
                'amount' => 100,
                'as_of_date' => now()->toDateString(),
                'source' => BalanceAnchor::SOURCE_INITIAL,
            ])
            ->assertForbidden();

        $this->actingAs($dependent)
            ->post(route('balance-anchors.keep'))
            ->assertForbidden();
    }

    public function test_balance_anchors_are_isolated_by_account(): void
    {
        $accountA = Account::factory()->create();
        $ownerA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $accountB = Account::factory()->create();
        $ownerB = User::factory()->owner()->create(['account_id' => $accountB->id]);

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $accountA->id,
            'user_id' => $ownerA->id,
            'amount' => 9999,
            'as_of_date' => now()->toDateString(),
            'source' => BalanceAnchor::SOURCE_INITIAL,
            'checkin_month' => now()->format('Y-m'),
        ]);

        $this->actingAs($ownerB)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_initial', true)
                ->where('summary.balance', null)
            );
    }

    public function test_recurring_percent_uses_amount_not_count(): void
    {
        $this->travelTo('2026-07-15');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $bill = RecurringBill::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'description' => 'Aluguel',
            'estimated_amount' => 1400,
            'day_of_month' => 5,
            'payment_method' => Transaction::PAYMENT_PIX,
            'start_date' => '2026-01-01',
            'active' => true,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'recurring_bill_id' => $bill->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 1400,
            'description' => 'Aluguel',
            'date' => '2026-07-05',
            'payment_method' => Transaction::PAYMENT_PIX,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'recurring_bill_id' => $bill->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 120,
            'description' => 'Internet',
            'date' => '2026-07-10',
            'payment_method' => Transaction::PAYMENT_PIX,
            'status' => Transaction::STATUS_PLANNED,
        ]);

        // 1400 / 1520 ≈ 92% (não 50% por contagem)
        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.paid_percent', 92)
                ->where('recurringSummary.total_count', 2)
                ->where('recurringSummary.paid_amount', 1400)
                ->where('recurringSummary.pending_amount', 120)
            );
    }

    public function test_benefit_card_expense_is_neutral_on_cash_and_month_balance(): void
    {
        $this->travelTo('2026-07-20');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $benefitCard = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'VR',
            'brand' => 'other',
            'type' => PaymentCard::TYPE_BENEFIT,
            'last_four' => '9999',
            'color' => '#16a34a',
            'closing_day' => null,
            'due_day' => null,
        ]);

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 1000,
            'as_of_date' => '2026-07-01',
            'source' => BalanceAnchor::SOURCE_INITIAL,
            'checkin_month' => '2026-07',
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 80,
            'description' => 'Almoço VR',
            'date' => '2026-07-15',
            'payment_method' => Transaction::PAYMENT_CARD,
            'payment_card_id' => $benefitCard->id,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 40,
            'description' => 'PIX',
            'date' => '2026-07-16',
            'payment_method' => Transaction::PAYMENT_PIX,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.balance', 960)
                ->where('summary.expense', 120)
                ->where('summary.expense_credit', 0)
                ->where('summary.expense_debit', 40)
                ->where('summary.month_balance', -40)
            );
    }

    public function test_stale_retroactive_cash_movement_prompts_recalc(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->travelTo('2026-07-01 12:00:00');

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 1000,
            'as_of_date' => '2026-07-01',
            'source' => BalanceAnchor::SOURCE_INITIAL,
            'checkin_month' => '2026-07',
        ]);

        $this->travelTo('2026-08-01 09:00:00');

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 1000,
            'as_of_date' => '2026-07-31',
            'source' => BalanceAnchor::SOURCE_MONTHLY_KEEP,
            'checkin_month' => '2026-08',
        ]);

        $this->travelTo('2026-08-05 10:00:00');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_stale_recalc', false)
                ->where('summary.balance', 1000)
            );

        // Lançamento retroativo depois do keep — data ≤ âncora vigente.
        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 150,
            'description' => 'PIX esquecido',
            'date' => '2026-07-20',
            'payment_method' => Transaction::PAYMENT_PIX,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_stale_recalc', true)
                ->where('balanceMeta.suggested_balance', 850)
                ->where('summary.balance', 850)
            );

        $this->actingAs($owner)
            ->post(route('balance-anchors.dismiss-stale'))
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_stale_recalc', false)
            );
    }

    public function test_retroactive_income_updates_month_balance_when_viewing_past_month(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->travelTo('2026-07-01 12:00:00');

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 1000,
            'as_of_date' => '2026-07-01',
            'source' => BalanceAnchor::SOURCE_INITIAL,
            'checkin_month' => '2026-07',
        ]);

        $this->travelTo('2026-08-01 09:00:00');

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 1000,
            'as_of_date' => '2026-07-31',
            'source' => BalanceAnchor::SOURCE_MONTHLY_KEEP,
            'checkin_month' => '2026-08',
        ]);

        $this->travelTo('2026-08-05 10:00:00');

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 300,
            'description' => 'Freela esquecido',
            'date' => '2026-07-20',
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.income', 300)
                ->where('summary.month_balance', 300)
                ->where('summary.balance', 1300)
            );

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.month_balance', 0)
                ->where('balanceMeta.needs_stale_recalc', true)
                ->where('balanceMeta.suggested_balance', 1300)
                ->where('summary.balance', 1300)
            );
    }

    public function test_effective_balance_at_uses_stale_adjustment_for_past_month_end(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->travelTo('2026-08-01 09:00:00');

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 1000,
            'as_of_date' => '2026-07-31',
            'source' => BalanceAnchor::SOURCE_MONTHLY_KEEP,
            'checkin_month' => '2026-08',
        ]);

        $this->travelTo('2026-08-05 10:00:00');

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 150,
            'description' => 'PIX esquecido',
            'date' => '2026-07-20',
            'payment_method' => Transaction::PAYMENT_PIX,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $balances = app(\App\Services\BalanceService::class);
        $julyEnd = now()->copy()->startOfMonth()->subDay()->endOfDay();

        $this->assertSame(1000.0, $balances->balanceAt($julyEnd));
        $this->assertSame(850.0, $balances->effectiveBalanceAt($julyEnd));
    }

    public function test_stale_suggestion_includes_same_day_retroactive_entries_as_july_anchor(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->travelTo('2026-08-01 09:00:00');

        BalanceAnchor::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'amount' => 25908.59,
            'as_of_date' => '2026-07-31',
            'source' => BalanceAnchor::SOURCE_MONTHLY_KEEP,
            'checkin_month' => '2026-08',
        ]);

        $this->travelTo('2026-08-05 10:00:00');

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 982.12,
            'description' => 'Restituição',
            'date' => '2026-07-31',
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'description' => 'Teste',
            'date' => '2026-07-31',
            'payment_method' => Transaction::PAYMENT_CASH,
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.balance', 26790.71)
                ->where('summary.month_balance', 882.12)
            );

        // Agosto sem movimentação de caixa: sugestão = Saldo de julho.
        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceMeta.needs_stale_recalc', true)
                ->where('balanceMeta.suggested_balance', 26790.71)
                ->where('summary.balance', 26790.71)
            );
    }
}
