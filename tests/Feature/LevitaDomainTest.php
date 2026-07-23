<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\DefaultCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevitaDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_account_owner_and_default_categories(): void
    {
        $response = $this->post('/register', [
            'name' => 'Thiago Owner',
            'account_name' => 'Família Teste',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticated();

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_OWNER, $user->role);
        $this->assertSame('Família Teste', $user->account->name);
        $this->assertCount(count(DefaultCategoryService::defaults()), Category::withoutGlobalScopes()->where('account_id', $user->account_id)->get());
    }

    public function test_dependent_cannot_manage_categories(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);

        $this->actingAs($dependent)
            ->post(route('categories.store'), [
                'name' => 'Proibida',
                'color' => '#123456',
            ])
            ->assertForbidden();

        $category = Category::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name' => 'Existente',
            'color' => '#ffc107',
        ]);

        $this->actingAs($dependent)
            ->delete(route('categories.destroy', $category))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('categories.store'), [
                'name' => 'Permitida',
                'color' => '#abcdef',
            ])
            ->assertRedirect();
    }

    public function test_dependent_can_only_delete_own_transactions(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $own = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $dependent->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 50,
            'description' => 'Própria',
            'date' => now()->toDateString(),
            'payment_method' => Transaction::PAYMENT_CASH,
        ]);

        $others = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 80,
            'description' => 'Do dono',
            'date' => now()->toDateString(),
            'payment_method' => Transaction::PAYMENT_CASH,
        ]);

        $this->actingAs($dependent)
            ->delete(route('transactions.destroy', $others))
            ->assertForbidden();

        $this->actingAs($dependent)
            ->delete(route('transactions.destroy', $own))
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseMissing('transactions', ['id' => $own->id]);
    }

    public function test_users_cannot_see_other_account_transactions(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $userA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $userB = User::factory()->owner()->create(['account_id' => $accountB->id]);
        $categoryB = Category::factory()->create(['account_id' => $accountB->id]);

        $txB = Transaction::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'user_id' => $userB->id,
            'category_id' => $categoryB->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 100,
            'description' => 'Secreta',
            'date' => now()->toDateString(),
            'payment_method' => Transaction::PAYMENT_CASH,
        ]);

        $this->actingAs($userA)
            ->get(route('transactions.edit', $txB->id))
            ->assertNotFound();
    }

    public function test_dependent_can_create_own_payment_card(): void
    {
        $account = Account::factory()->create();
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);

        $this->actingAs($dependent)
            ->post(route('payment-cards.store'), [
                'name' => 'Meu Nubank',
                'brand' => 'mastercard',
                'type' => 'credit',
                'last_four' => '4321',
                'color' => '#820ad1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_cards', [
            'name' => 'Meu Nubank',
            'user_id' => $dependent->id,
            'account_id' => $account->id,
            'type' => 'credit',
            'last_four' => '4321',
        ]);
    }

    public function test_payment_card_last_four_is_optional(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('payment-cards.store'), [
                'name' => 'Cartão sem final',
                'brand' => 'visa',
                'type' => 'credit',
                'last_four' => '',
                'color' => '#ffc107',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_cards', [
            'name' => 'Cartão sem final',
            'user_id' => $owner->id,
            'last_four' => null,
        ]);
    }

    public function test_payment_card_requires_debit_or_credit_type(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('payment-cards.store'), [
                'name' => 'Débito Itaú',
                'brand' => 'mastercard',
                'type' => 'debit',
                'last_four' => '9876',
                'color' => '#ff6600',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_cards', [
            'name' => 'Débito Itaú',
            'type' => 'debit',
            'last_four' => '9876',
        ]);
    }

    public function test_dependent_cannot_edit_other_members_payment_card(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);

        $ownerCard = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Cartão do dono',
            'brand' => 'visa',
            'type' => 'credit',
            'last_four' => '1111',
            'color' => '#ffc107',
        ]);

        $this->actingAs($dependent)
            ->put(route('payment-cards.update', $ownerCard), [
                'name' => 'Hack',
                'brand' => 'visa',
                'type' => 'credit',
                'last_four' => '9999',
                'color' => '#000000',
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->put(route('payment-cards.update', $ownerCard), [
                'name' => 'Cartão atualizado',
                'brand' => 'visa',
                'type' => 'credit',
                'last_four' => '1111',
                'color' => '#ffc107',
            ])
            ->assertRedirect();
    }

    public function test_owner_can_edit_dependent_payment_card(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);

        $card = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $dependent->id,
            'name' => 'Cartão dep',
            'brand' => 'elo',
            'type' => 'credit',
            'last_four' => '5555',
            'color' => '#00a868',
        ]);

        $this->actingAs($owner)
            ->put(route('payment-cards.update', $card), [
                'name' => 'Ajustado pelo dono',
                'brand' => 'elo',
                'type' => 'credit',
                'last_four' => '5555',
                'color' => '#00a868',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_cards', [
            'id' => $card->id,
            'name' => 'Ajustado pelo dono',
        ]);
    }

    public function test_users_cannot_see_other_account_payment_cards(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $userA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $userB = User::factory()->owner()->create(['account_id' => $accountB->id]);

        $cardB = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'user_id' => $userB->id,
            'name' => 'Secreto',
            'brand' => 'visa',
            'type' => 'credit',
            'last_four' => '0000',
            'color' => '#111111',
        ]);

        $this->actingAs($userA)
            ->put(route('payment-cards.update', $cardB), [
                'name' => 'Leak',
                'brand' => 'visa',
                'type' => 'credit',
                'last_four' => '0000',
                'color' => '#111111',
            ])
            ->assertNotFound();
    }

    public function test_transaction_with_card_requires_same_account_card(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $userA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $userB = User::factory()->owner()->create(['account_id' => $accountB->id]);
        $categoryA = Category::factory()->create(['account_id' => $accountA->id]);

        $cardB = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'user_id' => $userB->id,
            'name' => 'Outro',
            'brand' => 'visa',
            'type' => 'credit',
            'last_four' => '2222',
            'color' => '#222222',
        ]);

        $cardA = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $accountA->id,
            'user_id' => $userA->id,
            'name' => 'Meu',
            'brand' => 'visa',
            'type' => 'credit',
            'last_four' => '3333',
            'color' => '#333333',
        ]);

        $this->actingAs($userA)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 25,
                'description' => 'Cartão errado',
                'category_id' => $categoryA->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $cardB->id,
            ])
            ->assertSessionHasErrors('payment_card_id');

        $this->actingAs($userA)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 25,
                'description' => 'Cartão ok',
                'category_id' => $categoryA->id,
                'date' => now()->toDateString(),
                'payment_method' => Transaction::PAYMENT_CARD,
                'payment_card_id' => $cardA->id,
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'description' => 'Cartão ok',
            'payment_method' => Transaction::PAYMENT_CARD,
            'payment_card_id' => $cardA->id,
        ]);
    }

    public function test_cannot_delete_payment_card_with_transactions(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $card = PaymentCard::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Em uso',
            'brand' => 'visa',
            'type' => 'credit',
            'last_four' => '4444',
            'color' => '#444444',
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 10,
            'description' => 'Com cartão',
            'date' => now()->toDateString(),
            'payment_method' => Transaction::PAYMENT_CARD,
            'payment_card_id' => $card->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('payment-cards.destroy', $card))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payment_cards', ['id' => $card->id]);
    }

    public function test_income_can_be_created_without_bank_account_or_payment_method(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_INCOME,
                'amount' => 1500,
                'description' => 'Salário',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'description' => 'Salário',
            'type' => Transaction::TYPE_INCOME,
            'payment_method' => null,
            'payment_card_id' => null,
            'bank_account_id' => null,
        ]);
    }

    public function test_income_can_link_optional_bank_account(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);
        $bank = BankAccount::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Nubank',
            'color' => '#820ad1',
        ]);

        $this->actingAs($owner)
            ->post(route('transactions.store'), [
                'type' => Transaction::TYPE_INCOME,
                'amount' => 200,
                'description' => 'Freelance',
                'category_id' => $category->id,
                'date' => now()->toDateString(),
                'bank_account_id' => $bank->id,
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'description' => 'Freelance',
            'bank_account_id' => $bank->id,
            'payment_method' => null,
        ]);
    }

    public function test_payment_card_can_link_bank_account_optionally(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $bank = BankAccount::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Itaú',
            'color' => '#ff6600',
        ]);

        $this->actingAs($owner)
            ->post(route('payment-cards.store'), [
                'name' => 'Cartão Itaú',
                'brand' => 'visa',
                'type' => 'credit',
                'last_four' => '',
                'color' => '#ffc107',
                'bank_account_id' => $bank->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_cards', [
            'name' => 'Cartão Itaú',
            'bank_account_id' => $bank->id,
        ]);
    }

    public function test_dependent_can_create_bank_account(): void
    {
        $account = Account::factory()->create();
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);

        $this->actingAs($dependent)
            ->post(route('bank-accounts.store'), [
                'name' => 'Caixinha',
                'color' => '#2563eb',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'Caixinha',
            'user_id' => $dependent->id,
            'account_id' => $account->id,
        ]);
    }
}
