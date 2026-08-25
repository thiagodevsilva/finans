<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\ViewContext;
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

        $viewContext = null;
        $viewMembers = [];
        $viewCompanies = [];

        if ($user && $user->account_id) {
            $ctx = app(ViewContext::class);
            $viewContext = $ctx->toArray();

            $viewMembers = User::query()
                ->where('account_id', $user->account_id)
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'role' => $m->role,
                    'is_owner' => $m->isOwner(),
                ])
                ->values()
                ->all();

            $focusId = $ctx->focusMemberId() ?? $user->id;
            $viewCompanies = Company::query()
                ->where('user_id', $focusId)
                ->orderBy('name')
                ->get(['id', 'name', 'cnpj'])
                ->map(fn (Company $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'cnpj' => $c->cnpj,
                    'cnpj_formatted' => $c->formattedCnpj(),
                ])
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name', 'Levita'),
                'url' => rtrim(config('app.url'), '/'),
                'assetVersion' => $this->assetVersion(),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'role' => $user->role,
                    'is_owner' => $user->isOwner(),
                    'is_admin' => $user->isAdmin(),
                    'account_id' => $user->account_id,
                    'onboarding_status' => $user->onboarding_status,
                    'pinned_dashboard_chart' => $user->pinned_dashboard_chart,
                    'marketing_emails_opted_in' => (bool) $user->marketing_emails_opted_in,
                ] : null,
                'account' => $user?->account ? [
                    'id' => $user->account->id,
                    'name' => $user->account->name,
                ] : null,
            ],
            'viewContext' => $viewContext,
            'viewMembers' => $viewMembers,
            'viewCompanies' => $viewCompanies,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }

    private function assetVersion(): string
    {
        $parts = [];

        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $parts[] = (string) filemtime($manifest);
        }

        $imagesDir = public_path('images');
        if (is_dir($imagesDir)) {
            foreach (glob($imagesDir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    $parts[] = basename($file).(string) filemtime($file);
                }
            }
        }

        return $parts === []
            ? '1'
            : substr(hash('sha256', implode('|', $parts)), 0, 10);
    }
}
