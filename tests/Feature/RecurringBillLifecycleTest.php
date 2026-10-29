<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBillLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_skipped_fixed_bill_leaves_the_paid_percent(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();

        $paid = $this->storeFixed($owner, $category, 'Aluguel', 3000, 5);
        $skipped = $this->storeFixed($owner, $category, 'Internet', 1000, 10);

        $paidPlanned = $this->plannedInMonth($paid, '2026-10-01', '2026-10-31');
        $this->actingAs($owner)
            ->post(route('recurring-transactions.confirm', $paidPlanned), [
                'amount' => 3000,
                'date' => '2026-10-05',
            ])
            ->assertRedirect();

        $toSkip = $this->plannedInMonth($skipped, '2026-10-01', '2026-10-31');
        $this->actingAs($owner)
            ->post(route('recurring-transactions.skip', $toSkip))
            ->assertRedirect();

        $toSkip->refresh();
        $this->assertSame(Transaction::STATUS_SKIPPED, $toSkip->status);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.paid_amount', 3000)
                ->where('recurringSummary.pending_amount', 0)
                ->where('recurringSummary.total_amount', 3000)
                ->where('recurringSummary.paid_percent', 100)
                ->where('recurringSummary.skipped_count', 1)
            );

        $next = Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $skipped->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->whereBetween('date', ['2026-11-01', '2026-11-30'])
            ->first();
        $this->assertNotNull($next);
    }

    public function test_variable_month_without_charges_drops_out_of_percent_when_skipped(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();

        $fixed = $this->storeFixed($owner, $category, 'Aluguel', 3000, 5);
        $planned = $this->plannedInMonth($fixed, '2026-10-01', '2026-10-31');
        $this->actingAs($owner)
            ->post(route('recurring-transactions.confirm', $planned), [
                'amount' => 3000,
                'date' => '2026-10-05',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Combustível',
                'kind' => RecurringBill::KIND_VARIABLE,
                'category_id' => $category->id,
                'estimated_amount' => 1000,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => '2026-10-01',
            ])
            ->assertRedirect();

        $variable = RecurringBill::withoutGlobalScopes()->where('description', 'Combustível')->first();

        $this->actingAs($owner)
            ->get(route('recurring-bills.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('periodSummary.current.paid_percent', 100)
                ->where('periodSummary.current.pending_amount', 0)
                ->where('periodSummary.current.total_amount', 3000)
            );

        $this->actingAs($owner)
            ->post(route('recurring-bills.skip-month', $variable))
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.paid_amount', 3000)
                ->where('recurringSummary.pending_amount', 0)
                ->where('recurringSummary.total_amount', 3000)
                ->where('recurringSummary.paid_percent', 100)
                ->where('recurringSummary.skipped_count', 0)
            );
    }

    public function test_unskip_puts_the_bill_back_into_the_percent(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $bill = $this->storeFixed($owner, $category, 'Internet', 1000, 10);
        $planned = $this->plannedInMonth($bill, '2026-10-01', '2026-10-31');

        $this->actingAs($owner)
            ->post(route('recurring-transactions.skip', $planned))
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('recurring-transactions.unskip', $planned))
            ->assertRedirect();

        $planned->refresh();
        $this->assertSame(Transaction::STATUS_PLANNED, $planned->status);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.pending_amount', 1000)
                ->where('recurringSummary.paid_percent', 0)
                ->where('recurringSummary.skipped_count', 0)
            );
    }

    public function test_deactivate_current_removes_this_month_and_keeps_the_record(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $bill = $this->storeFixed($owner, $category, 'Luz', 180, 8);

        $this->actingAs($owner)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'current'])
            ->assertRedirect();

        $bill->refresh();
        $this->assertFalse($bill->active);
        $this->assertSame('2026-10-01', $bill->suspended_from->toDateString());
        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()
                ->where('recurring_bill_id', $bill->id)
                ->where('status', Transaction::STATUS_PLANNED)
                ->whereDate('date', '>=', '2026-10-01')
                ->count()
        );

        $this->actingAs($owner)
            ->get(route('recurring-bills.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bills.0.id', $bill->id)
                ->where('bills.0.active', false)
            );
    }

    public function test_deactivate_future_keeps_the_current_month(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $bill = $this->storeFixed($owner, $category, 'Água', 90, 12);

        $this->actingAs($owner)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'future'])
            ->assertRedirect();

        $bill->refresh();
        $this->assertFalse($bill->active);
        $this->assertSame('2026-11-01', $bill->suspended_from->toDateString());
        $this->assertNotNull($this->plannedInMonth($bill, '2026-10-01', '2026-10-31'));
        $this->assertNull(
            Transaction::withoutGlobalScopes()
                ->where('recurring_bill_id', $bill->id)
                ->where('status', Transaction::STATUS_PLANNED)
                ->whereDate('date', '>=', '2026-11-01')
                ->first()
        );

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.pending_amount', 90)
                ->where('recurringSummary.pending_count', 1)
            );
    }

    public function test_activate_current_recreates_this_month(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $bill = $this->storeFixed($owner, $category, 'Gás', 70, 6);

        $this->actingAs($owner)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'current'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('recurring-bills.activate', $bill), ['scope' => 'current'])
            ->assertRedirect();

        $bill->refresh();
        $this->assertTrue($bill->active);
        $this->assertNull($bill->suspended_from);
        $this->assertNotNull($this->plannedInMonth($bill, '2026-10-01', '2026-10-31'));
        $this->assertNotNull($this->plannedInMonth($bill, '2026-11-01', '2026-11-30'));
    }

    public function test_activate_future_does_not_create_the_current_month(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $bill = $this->storeFixed($owner, $category, 'Condomínio', 400, 10);

        $this->actingAs($owner)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'current'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('recurring-bills.activate', $bill), ['scope' => 'future'])
            ->assertRedirect();

        $bill->refresh();
        $this->assertTrue($bill->active);
        $this->assertNull($this->plannedInMonth($bill, '2026-10-01', '2026-10-31'));
        $this->assertNotNull(
            Transaction::withoutGlobalScopes()
                ->where('recurring_bill_id', $bill->id)
                ->where('status', Transaction::STATUS_SKIPPED)
                ->whereBetween('date', ['2026-10-01', '2026-10-31'])
                ->first()
        );
        $this->assertNotNull($this->plannedInMonth($bill, '2026-11-01', '2026-11-30'));

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.pending_amount', 0)
                ->where('recurringSummary.paid_percent', 100)
            );
    }

    public function test_activate_future_keeps_an_existing_current_bill(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $bill = $this->storeFixed($owner, $category, 'Internet', 120, 9);

        $this->actingAs($owner)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'future'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('recurring-bills.activate', $bill), ['scope' => 'future'])
            ->assertRedirect();

        $kept = $this->plannedInMonth($bill, '2026-10-01', '2026-10-31');
        $this->assertNotNull($kept);
        $this->assertSame(Transaction::STATUS_PLANNED, $kept->status);
        $this->assertNotNull($this->plannedInMonth($bill, '2026-11-01', '2026-11-30'));
    }

    public function test_inactive_variable_kept_for_this_month_still_counts(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Mercado',
                'kind' => RecurringBill::KIND_VARIABLE,
                'category_id' => $category->id,
                'estimated_amount' => 800,
                'start_date' => '2026-10-01',
            ])
            ->assertRedirect();

        $bill = RecurringBill::withoutGlobalScopes()->where('description', 'Mercado')->first();

        $this->actingAs($owner)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'future'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.pending_amount', 0)
                ->where('recurringSummary.paid_percent', 0)
            );
    }

    public function test_deactivating_variable_for_the_current_month_drops_the_estimate(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Mercado',
                'kind' => RecurringBill::KIND_VARIABLE,
                'category_id' => $category->id,
                'estimated_amount' => 800,
                'start_date' => '2026-10-01',
            ])
            ->assertRedirect();

        $bill = RecurringBill::withoutGlobalScopes()->where('description', 'Mercado')->first();

        $this->actingAs($owner)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'current'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.pending_amount', 0)
                ->where('recurringSummary.total_amount', 0)
                ->where('recurringSummary.paid_percent', 0)
            );
    }

    public function test_dependent_cannot_deactivate_another_members_bill(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $dependent = User::factory()->dependent()->create(['account_id' => $owner->account_id]);
        $bill = $this->storeFixed($owner, $category, 'Aluguel', 1000, 5);

        $this->actingAs($dependent)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'current'])
            ->assertForbidden();

        $this->assertTrue($bill->fresh()->active);
    }

    public function test_other_account_cannot_deactivate_bill(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $bill = $this->storeFixed($owner, $category, 'Aluguel', 1000, 5);
        $other = User::factory()->owner()->create();

        $this->actingAs($other)
            ->post(route('recurring-bills.deactivate', $bill), ['scope' => 'future'])
            ->assertNotFound();

        $this->assertTrue($bill->fresh()->active);
    }

    public function test_variable_payment_can_be_recorded_without_changing_fixed_percent(): void
    {
        $this->travelTo('2026-10-15 12:00:00');

        [$owner, $category] = $this->family();
        $fixed = $this->storeFixed($owner, $category, 'Aluguel', 3000, 5);
        $planned = $this->plannedInMonth($fixed, '2026-10-01', '2026-10-31');

        $this->actingAs($owner)
            ->post(route('recurring-transactions.confirm', $planned), [
                'amount' => 3000,
                'date' => '2026-10-05',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => 'Tauste Pay',
                'kind' => RecurringBill::KIND_VARIABLE,
                'category_id' => $category->id,
                'estimated_amount' => 1000,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => '2026-10-01',
            ])
            ->assertRedirect();

        $variable = RecurringBill::withoutGlobalScopes()->where('description', 'Tauste Pay')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('recurring-bills.pay', $variable), [
                'amount' => 180.5,
                'date' => '2026-10-12',
                'description' => 'Compra no Tauste',
            ])
            ->assertRedirect();

        $payment = Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $variable->id)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame('Compra no Tauste', $payment->description);
        $this->assertEquals(180.5, (float) $payment->amount);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recurringSummary.paid_amount', 3000)
                ->where('recurringSummary.pending_amount', 0)
                ->where('recurringSummary.paid_percent', 100)
            );

        $this->actingAs($owner)
            ->get(route('recurring-bills.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bills', fn ($bills) => collect($bills)
                    ->contains(fn ($bill) => $bill['id'] === $variable->id && (float) $bill['month_paid'] === 180.5))
            );
    }

    /**
     * @return array{0: User, 1: Category}
     */
    private function family(): array
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        return [$owner, $category];
    }

    private function storeFixed(User $owner, Category $category, string $description, float $amount, int $day): RecurringBill
    {
        $this->actingAs($owner)
            ->post(route('recurring-bills.store'), [
                'description' => $description,
                'category_id' => $category->id,
                'estimated_amount' => $amount,
                'day_of_month' => $day,
                'payment_method' => Transaction::PAYMENT_PIX,
                'start_date' => '2026-10-01',
            ])
            ->assertRedirect();

        return RecurringBill::withoutGlobalScopes()->where('description', $description)->firstOrFail();
    }

    private function plannedInMonth(RecurringBill $bill, string $start, string $end): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->where('recurring_bill_id', $bill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->whereBetween('date', [$start, $end])
            ->first();
    }
}
