<?php

namespace App\Providers;

use App\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        App::setLocale('pt_BR');
        Carbon::setLocale('pt_BR');

        // Só no Docker/VPS (porta publicada ≠ :80 no container). No local com artisan serve
        // isso quebra Ziggy/Inertia (ex.: APP_URL=localhost vs 127.0.0.1:8001 → CORS).
        if (filter_var(env('APP_FORCE_ROOT_URL', false), FILTER_VALIDATE_BOOLEAN)) {
            if ($root = config('app.url')) {
                URL::forceRootUrl($root);
            }
        }

        Route::bind('support_ticket', function (string $value) {
            $query = SupportTicket::query();

            if (auth()->user()?->isAdmin()) {
                $query->withoutGlobalScope('account');
            }

            return $query->whereKey($value)->firstOrFail();
        });
    }
}
