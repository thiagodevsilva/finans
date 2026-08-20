<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportChartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_returns_catalog_and_chart_series(): void
    {
        $this->travelTo('2026-07-20');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create([
            'account_id' => $account->id,
            'name' => 'Alimentação',
            'color' => '#f59e0b',
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 120,
            'description' => 'Mercado',
            'date' => '2026-07-10',
            'status' => Transaction::STATUS_CONFIRMED,
            'payment_method' => Transaction::PAYMENT_PIX,
        ]);

        $this->actingAs($owner)
            ->get(route('reports.index', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index')
                ->has('catalog', 5)
                ->has('charts.by_category')
                ->has('charts.monthly_flow')
                ->has('charts.by_member')
                ->has('charts.payment_mix')
                ->has('charts.cash_movement')
                ->where('charts.by_category.series.0.name', 'Alimentação')
                ->where('charts.by_category.series.0.total', 120)
                ->where('pinnedChartId', null)
            );
    }

    public function test_user_can_pin_and_unpin_chart_on_dashboard(): void
    {
        $this->travelTo('2026-07-20');

        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $category = Category::factory()->create(['account_id' => $account->id]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 80,
            'description' => 'Café',
            'date' => '2026-07-12',
            'status' => Transaction::STATUS_CONFIRMED,
            'payment_method' => Transaction::PAYMENT_CASH,
        ]);

        $this->actingAs($owner)
            ->put(route('dashboard.pinned-chart'), [
                'chart_id' => ReportChartService::CHART_BY_CATEGORY,
            ])
            ->assertRedirect();

        $owner->refresh();
        $this->assertSame(ReportChartService::CHART_BY_CATEGORY, $owner->pinned_dashboard_chart);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pinnedChart.id', ReportChartService::CHART_BY_CATEGORY)
                ->where('pinnedChart.series.0.total', 80)
                ->where('summary.card_payments', 0)
            );

        $this->actingAs($owner)
            ->put(route('dashboard.pinned-chart'), [
                'chart_id' => null,
            ])
            ->assertRedirect();

        $owner->refresh();
        $this->assertNull($owner->pinned_dashboard_chart);

        $this->actingAs($owner)
            ->get(route('dashboard', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pinnedChart', null)
            );
    }

    public function test_reports_do_not_leak_other_account_data(): void
    {
        $this->travelTo('2026-07-20');

        $accountA = Account::factory()->create();
        $ownerA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $categoryA = Category::factory()->create(['account_id' => $accountA->id, 'name' => 'A']);

        $accountB = Account::factory()->create();
        $ownerB = User::factory()->owner()->create(['account_id' => $accountB->id]);
        $categoryB = Category::factory()->create(['account_id' => $accountB->id, 'name' => 'Segredo']);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $accountA->id,
            'user_id' => $ownerA->id,
            'category_id' => $categoryA->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 10,
            'description' => 'A',
            'date' => '2026-07-10',
            'status' => Transaction::STATUS_CONFIRMED,
            'payment_method' => Transaction::PAYMENT_PIX,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'user_id' => $ownerB->id,
            'category_id' => $categoryB->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 999,
            'description' => 'B',
            'date' => '2026-07-10',
            'status' => Transaction::STATUS_CONFIRMED,
            'payment_method' => Transaction::PAYMENT_PIX,
        ]);

        $this->actingAs($ownerA)
            ->get(route('reports.index', ['month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('charts.by_category.series.0.name', 'A')
                ->where('charts.by_category.series.0.total', 10)
                ->missing('charts.by_category.series.1')
            );
    }
}
