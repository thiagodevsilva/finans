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

        $users = User::query()
            ->with('account:id,name')
            ->where('is_admin', false)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'family_name' => $user->account?->name,
                'created_at' => $user->created_at?->toDateString(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'is_online' => $user->isOnline(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'onlineCount' => User::query()
                ->where('is_admin', false)
                ->where('last_seen_at', '>=', $onlineThreshold)
                ->count(),
            'totalUsers' => User::query()->where('is_admin', false)->count(),
            'users' => $users,
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
