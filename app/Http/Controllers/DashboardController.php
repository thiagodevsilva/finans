<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $base = Transaction::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', Transaction::STATUS_CONFIRMED);

        $income = (clone $base)->where('type', Transaction::TYPE_INCOME)->sum('amount');
        $expense = (clone $base)->where('type', Transaction::TYPE_EXPENSE)->sum('amount');

        $recent = Transaction::query()
            ->with([
                'category:id,name,color',
                'user:id,name',
                'paymentCard:id,name,brand,type,last_four,color',
                'bankAccount:id,name,color',
            ])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', Transaction::STATUS_CONFIRMED)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $upcomingBills = Transaction::query()
            ->with(['category:id,name,color'])
            ->where('status', Transaction::STATUS_PLANNED)
            ->whereNotNull('recurring_bill_id')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->limit(8)
            ->get();

        return Inertia::render('Dashboard', [
            'summary' => [
                'income' => (float) $income,
                'expense' => (float) $expense,
                'balance' => (float) $income - (float) $expense,
            ],
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'recentTransactions' => $recent,
            'upcomingBills' => $upcomingBills,
        ]);
    }
}
