<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $days = max(7, min(90, (int) $request->input('days', 30)));
        $start = now()->subDays($days - 1)->startOfDay();

        $signups = User::query()
            ->where('is_admin', false)
            ->where('created_at', '>=', $start)
            ->orderBy('created_at')
            ->get(['created_at'])
            ->groupBy(fn (User $user) => $user->created_at->toDateString())
            ->map->count();

        $labels = [];
        $counts = [];
        $cursor = $start->copy();

        while ($cursor->lte(now()->endOfDay())) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $counts[] = (int) ($signups[$key] ?? 0);
            $cursor->addDay();
        }

        $onlineThreshold = now()->subMinutes(User::ONLINE_MINUTES);

        return Inertia::render('Admin/Dashboard', [
            'onlineCount' => User::query()
                ->where('is_admin', false)
                ->where('last_seen_at', '>=', $onlineThreshold)
                ->count(),
            'totalUsers' => User::query()->where('is_admin', false)->count(),
            'signupsChart' => [
                'labels' => $labels,
                'data' => $counts,
            ],
            'filters' => [
                'days' => $days,
            ],
            'onlineWindowMinutes' => User::ONLINE_MINUTES,
        ]);
    }
}
