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
                'assetVersion' => $this->assetVersion(),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_owner' => $user->isOwner(),
                    'is_admin' => $user->isAdmin(),
                    'account_id' => $user->account_id,
                    'onboarding_status' => $user->onboarding_status,
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

    /**
     * Muda a cada build Vite ou alteração em /public/images — usado em ?v= das imagens.
     */
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
