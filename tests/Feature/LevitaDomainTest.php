<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
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
        ]);

        $others = Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 80,
            'description' => 'Do dono',
            'date' => now()->toDateString(),
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
        ]);

        $this->actingAs($userA)
            ->get(route('transactions.edit', $txB->id))
            ->assertNotFound();
    }
}
