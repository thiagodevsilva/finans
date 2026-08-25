<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Company;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ViewContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function family(): array
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        return compact('account', 'owner', 'dependent', 'category');
    }

    public function test_mine_view_hides_other_member_transactions(): void
    {
        ['owner' => $owner, 'dependent' => $dependent, 'category' => $category] = $this->family();

        Transaction::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 100,
            'date' => now()->toDateString(),
            'status' => Transaction::STATUS_CONFIRMED,
            'is_shared' => false,
        ]);

        Transaction::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $dependent->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 50,
            'date' => now()->toDateString(),
            'status' => Transaction::STATUS_CONFIRMED,
            'is_shared' => false,
        ]);

        $this->actingAs($dependent)
            ->withSession([ViewContext::SESSION_SCOPE => ViewContext::SCOPE_MINE])
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.user_id', $dependent->id)
            );
    }

    public function test_shared_transaction_only_in_family_view(): void
    {
        ['owner' => $owner, 'category' => $category] = $this->family();

        Transaction::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 200,
            'date' => now()->toDateString(),
            'status' => Transaction::STATUS_CONFIRMED,
            'is_shared' => true,
        ]);

        $this->actingAs($owner)
            ->withSession([ViewContext::SESSION_SCOPE => ViewContext::SCOPE_MINE])
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('transactions.data', 0));

        $this->actingAs($owner)
            ->withSession([ViewContext::SESSION_SCOPE => ViewContext::SCOPE_FAMILY])
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('transactions.data', 1));
    }

    public function test_owner_can_create_transaction_for_dependent(): void
    {
        ['owner' => $owner, 'dependent' => $dependent, 'category' => $category] = $this->family();

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 33.5,
                'description' => 'Lanche do filho',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_PIX,
                'user_id' => $dependent->id,
                'is_shared' => false,
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $dependent->id,
            'created_by' => $owner->id,
            'description' => 'Lanche do filho',
            'amount' => 33.5,
        ]);
    }

    public function test_dependent_cannot_create_for_other_member(): void
    {
        ['owner' => $owner, 'dependent' => $dependent, 'category' => $category] = $this->family();

        $this->actingAs($dependent)
            ->from(route('transactions.create'))
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 10,
                'description' => 'Tentativa',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CASH,
                'user_id' => $owner->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_per_member_cash_balance_is_isolated(): void
    {
        ['owner' => $owner, 'dependent' => $dependent] = $this->family();

        $this->actingAs($owner)
            ->post(route('balance-anchors.store'), [
                'amount' => 1000,
                'as_of_date' => now()->subDay()->toDateString(),
                'source' => 'initial',
                'member_id' => $owner->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('balance-anchors.store'), [
                'amount' => 200,
                'as_of_date' => now()->subDay()->toDateString(),
                'source' => 'initial',
                'member_id' => $dependent->id,
            ])
            ->assertRedirect();

        $balances = app(\App\Services\BalanceService::class);

        $this->assertSame(1000.0, $balances->effectiveBalanceAt(now(), $owner));
        $this->assertSame(200.0, $balances->effectiveBalanceAt(now(), $dependent));
        $this->assertSame(1200.0, $balances->familyEffectiveBalanceAt(now(), $owner->account_id));
    }

    public function test_company_filter_restricts_listing(): void
    {
        ['owner' => $owner, 'category' => $category] = $this->family();

        $company = Company::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
        ]);

        Transaction::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'company_id' => $company->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 80,
            'date' => now()->toDateString(),
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        Transaction::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'company_id' => null,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 40,
            'date' => now()->toDateString(),
            'status' => Transaction::STATUS_CONFIRMED,
        ]);

        $this->actingAs($owner)
            ->withSession([
                ViewContext::SESSION_SCOPE => ViewContext::SCOPE_MINE,
                ViewContext::SESSION_COMPANY => $company->id,
            ])
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.company_id', $company->id)
            );
    }

    public function test_mine_view_hides_spouse_payment_cards(): void
    {
        ['owner' => $owner, 'dependent' => $dependent] = $this->family();

        PaymentCard::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
            'type' => PaymentCard::TYPE_CREDIT,
        ]);

        PaymentCard::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $dependent->id,
            'type' => PaymentCard::TYPE_CREDIT,
        ]);

        $this->actingAs($dependent)
            ->withSession([ViewContext::SESSION_SCOPE => ViewContext::SCOPE_MINE])
            ->get(route('payment-cards.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('cards', 1)
                ->where('cards.0.user_id', $dependent->id)
            );
    }

    public function test_view_context_update_persists_in_session(): void
    {
        ['owner' => $owner, 'dependent' => $dependent] = $this->family();

        $this->actingAs($owner)
            ->put(route('view-context.update'), [
                'scope' => 'member',
                'member_id' => $dependent->id,
                'company' => 'all',
            ])
            ->assertRedirect();

        $this->assertEquals(ViewContext::SCOPE_MEMBER, session(ViewContext::SESSION_SCOPE));
        $this->assertEquals($dependent->id, session(ViewContext::SESSION_MEMBER_ID));
    }
}
