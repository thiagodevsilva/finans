<?php

namespace App\Services;

use App\Models\PaymentCard;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportChartService
{
    public const CHART_BY_CATEGORY = 'by_category';

    public const CHART_MONTHLY_FLOW = 'monthly_flow';

    public const CHART_BY_MEMBER = 'by_member';

    public const CHART_PAYMENT_MIX = 'payment_mix';

    public const CHART_CASH_MOVEMENT = 'cash_movement';

    public const CHART_IDS = [
        self::CHART_BY_CATEGORY,
        self::CHART_MONTHLY_FLOW,
        self::CHART_BY_MEMBER,
        self::CHART_PAYMENT_MIX,
        self::CHART_CASH_MOVEMENT,
    ];

    /**
     * Catálogo de gráficos disponíveis (fixos + UI).
     *
     * @return array<string, array{id: string, title: string, description: string, chart_type: string}>
     */
    public function catalog(): array
    {
        return [
            self::CHART_BY_CATEGORY => [
                'id' => self::CHART_BY_CATEGORY,
                'title' => 'Gastos por categoria',
                'description' => 'Onde o dinheiro foi gasto no mês (compras confirmadas).',
                'chart_type' => 'doughnut',
            ],
            self::CHART_MONTHLY_FLOW => [
                'id' => self::CHART_MONTHLY_FLOW,
                'title' => 'Entradas × gastos × investimentos',
                'description' => 'Evolução dos últimos 6 meses para ver tendências.',
                'chart_type' => 'bar',
            ],
            self::CHART_BY_MEMBER => [
                'id' => self::CHART_BY_MEMBER,
                'title' => 'Gastos por membro',
                'description' => 'Quanto cada pessoa da conta gastou no mês.',
                'chart_type' => 'bar',
            ],
            self::CHART_PAYMENT_MIX => [
                'id' => self::CHART_PAYMENT_MIX,
                'title' => 'Gastos por forma de pagamento',
                'description' => 'Crédito, benefício e pagamentos à vista (débito/PIX/dinheiro).',
                'chart_type' => 'doughnut',
            ],
            self::CHART_CASH_MOVEMENT => [
                'id' => self::CHART_CASH_MOVEMENT,
                'title' => 'Movimentação de caixa',
                'description' => 'Entradas, gastos à vista, pagamentos de cartão e investimentos (o que mexe no saldo).',
                'chart_type' => 'bar',
            ],
        ];
    }

    public function isValidChartId(?string $chartId): bool
    {
        return $chartId !== null && in_array($chartId, self::CHART_IDS, true);
    }

    /**
     * @return array{id: string, title: string, description: string, chart_type: string, series: array}|null
     */
    public function build(string $chartId, int $month, int $year): ?array
    {
        if (! $this->isValidChartId($chartId)) {
            return null;
        }

        $meta = $this->catalog()[$chartId];
        $series = match ($chartId) {
            self::CHART_BY_CATEGORY => $this->byCategory($month, $year),
            self::CHART_MONTHLY_FLOW => $this->monthlyFlow($month, $year),
            self::CHART_BY_MEMBER => $this->byMember($month, $year),
            self::CHART_PAYMENT_MIX => $this->paymentMix($month, $year),
            self::CHART_CASH_MOVEMENT => $this->cashMovement($month, $year),
            default => [],
        };

        return [
            ...$meta,
            'series' => $series,
        ];
    }

    /**
     * @return list<array{name: string, color: string, total: float}>
     */
    public function byCategory(int $month, int $year): array
    {
        [$start, $end] = $this->monthRange($month, $year);

        return Transaction::query()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category:id,name,color')
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$start, $end])
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->category?->name ?? 'Sem categoria',
                'color' => $row->category?->color ?? '#64748b',
                'total' => (float) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, income: float, expense: float, investments: float}>
     */
    public function monthlyFlow(int $month, int $year): array
    {
        $monthly = [];

        for ($i = 5; $i >= 0; $i--) {
            $period = Carbon::create($year, $month, 1)->subMonths($i);
            [$pStart, $pEnd] = $this->monthRange($period->month, $period->year);

            $income = (float) Transaction::query()
                ->where('type', Transaction::TYPE_INCOME)
                ->where('status', Transaction::STATUS_CONFIRMED)
                ->whereBetween('date', [$pStart, $pEnd])
                ->sum('amount');

            $expense = (float) Transaction::query()
                ->where('type', Transaction::TYPE_EXPENSE)
                ->where('status', Transaction::STATUS_CONFIRMED)
                ->whereBetween('date', [$pStart, $pEnd])
                ->sum('amount');

            $investments = (float) Transaction::query()
                ->where('type', Transaction::TYPE_INVESTMENT)
                ->where('status', Transaction::STATUS_CONFIRMED)
                ->whereBetween('date', [$pStart, $pEnd])
                ->sum('amount');

            $monthly[] = [
                'label' => $period->translatedFormat('M/Y'),
                'income' => $income,
                'expense' => $expense,
                'investments' => $investments,
            ];
        }

        return $monthly;
    }

    /**
     * @return list<array{name: string, color: string, total: float}>
     */
    public function byMember(int $month, int $year): array
    {
        [$start, $end] = $this->monthRange($month, $year);

        return Transaction::query()
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->with('user:id,name')
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$start, $end])
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->user?->name ?? 'Desconhecido',
                'color' => $this->colorForIndex($row->user_id),
                'total' => (float) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, color: string, total: float}>
     */
    public function paymentMix(int $month, int $year): array
    {
        [$start, $end] = $this->monthRange($month, $year);

        $confirmedExpenses = fn () => Transaction::query()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$start, $end]);

        $credit = (float) (clone $confirmedExpenses())
            ->where('payment_method', Transaction::PAYMENT_CARD)
            ->whereHas('paymentCard', fn ($q) => $q->where('type', PaymentCard::TYPE_CREDIT))
            ->sum('amount');

        $benefit = (float) (clone $confirmedExpenses())
            ->where('payment_method', Transaction::PAYMENT_CARD)
            ->whereHas('paymentCard', fn ($q) => $q->where('type', PaymentCard::TYPE_BENEFIT))
            ->sum('amount');

        $cashLike = (float) (clone $confirmedExpenses())
            ->where(function ($q) {
                $q->whereNull('payment_method')
                    ->orWhereIn('payment_method', [
                        Transaction::PAYMENT_CASH,
                        Transaction::PAYMENT_PIX,
                        Transaction::PAYMENT_TRANSFER,
                        Transaction::PAYMENT_DEBIT,
                        Transaction::PAYMENT_AUTO_DEBIT,
                    ])
                    ->orWhere(function ($debitCard) {
                        $debitCard->where('payment_method', Transaction::PAYMENT_CARD)
                            ->whereHas(
                                'paymentCard',
                                fn ($card) => $card->where('type', PaymentCard::TYPE_DEBIT)
                            );
                    });
            })
            ->sum('amount');

        return collect([
            ['name' => 'À vista (débito/PIX/dinheiro)', 'color' => '#ef4444', 'total' => $cashLike],
            ['name' => 'Crédito', 'color' => '#f59e0b', 'total' => $credit],
            ['name' => 'Benefício', 'color' => '#8b5cf6', 'total' => $benefit],
        ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values()
            ->all();
    }

    /**
     * Movimentação que afeta o caixa (não inclui compras no crédito/benefício).
     *
     * @return list<array{label: string, income: float, cash_expense: float, card_payments: float, investments: float}>
     */
    public function cashMovement(int $month, int $year): array
    {
        $rows = [];

        for ($i = 5; $i >= 0; $i--) {
            $period = Carbon::create($year, $month, 1)->subMonths($i);
            [$pStart, $pEnd] = $this->monthRange($period->month, $period->year);

            $income = (float) Transaction::query()
                ->where('type', Transaction::TYPE_INCOME)
                ->where('status', Transaction::STATUS_CONFIRMED)
                ->whereBetween('date', [$pStart, $pEnd])
                ->sum('amount');

            $cashExpense = $this->cashExpenseTotal($pStart, $pEnd);
            $cardPayments = $this->cardPaymentsTotal($pStart, $pEnd);

            $investments = (float) Transaction::query()
                ->where('type', Transaction::TYPE_INVESTMENT)
                ->where('status', Transaction::STATUS_CONFIRMED)
                ->whereBetween('date', [$pStart, $pEnd])
                ->sum('amount');

            $rows[] = [
                'label' => $period->translatedFormat('M/Y'),
                'income' => $income,
                'cash_expense' => $cashExpense,
                'card_payments' => $cardPayments,
                'investments' => $investments,
            ];
        }

        return $rows;
    }

    public function cardPaymentsTotal(string $start, string $end): float
    {
        return (float) Transaction::query()
            ->where('type', Transaction::TYPE_TRANSFER)
            ->whereNotNull('credit_card_invoice_id')
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$start, $end])
            ->where(function ($method) {
                $method->whereNull('payment_method')
                    ->orWhere('payment_method', '!=', Transaction::PAYMENT_CARD);
            })
            ->sum('amount');
    }

    public function cashExpenseTotal(string $start, string $end): float
    {
        $expense = (float) Transaction::query()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $credit = (float) Transaction::query()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$start, $end])
            ->where('payment_method', Transaction::PAYMENT_CARD)
            ->whereHas('paymentCard', fn ($q) => $q->where('type', PaymentCard::TYPE_CREDIT))
            ->sum('amount');

        $benefit = (float) Transaction::query()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->whereBetween('date', [$start, $end])
            ->where('payment_method', Transaction::PAYMENT_CARD)
            ->whereHas('paymentCard', fn ($q) => $q->where('type', PaymentCard::TYPE_BENEFIT))
            ->sum('amount');

        return round($expense - $credit - $benefit, 2);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function monthRange(int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    protected function colorForIndex(mixed $seed): string
    {
        $palette = ['#2563eb', '#ffc107', '#0d9488', '#ef4444', '#8b5cf6', '#f97316', '#06b6d4'];
        $hash = crc32((string) $seed);

        return $palette[$hash % count($palette)];
    }
}
