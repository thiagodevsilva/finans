<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing('account');
        }

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name', 'Levita'),
                'url' => rtrim(config('app.url'), '/'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_owner' => $user->isOwner(),
                    'account_id' => $user->account_id,
                ] : null,
                'account' => $user?->account ? [
                    'id' => $user->account->id,
                    'name' => $user->account->name,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
