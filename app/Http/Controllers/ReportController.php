<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $byCategory = Transaction::query()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->with('category:id,name,color')
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category_id')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->category?->name ?? 'Sem categoria',
                'color' => $row->category?->color ?? '#64748b',
                'total' => (float) $row->total,
            ]);

        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $period = Carbon::create($year, $month, 1)->subMonths($i);
            $pStart = $period->copy()->startOfMonth();
            $pEnd = $period->copy()->endOfMonth();

            $income = Transaction::query()
                ->where('type', Transaction::TYPE_INCOME)
                ->whereBetween('date', [$pStart->toDateString(), $pEnd->toDateString()])
                ->sum('amount');

            $expense = Transaction::query()
                ->where('type', Transaction::TYPE_EXPENSE)
                ->whereBetween('date', [$pStart->toDateString(), $pEnd->toDateString()])
                ->sum('amount');

            $monthly[] = [
                'label' => $period->translatedFormat('M/Y'),
                'income' => (float) $income,
                'expense' => (float) $expense,
            ];
        }

        return Inertia::render('Reports/Index', [
            'byCategory' => $byCategory,
            'monthly' => $monthly,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }
}
