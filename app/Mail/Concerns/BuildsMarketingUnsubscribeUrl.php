<?php

namespace App\Mail\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\URL;

trait BuildsMarketingUnsubscribeUrl
{
    protected function marketingUnsubscribeUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'email.unsubscribe',
            now()->addDays(30),
            ['user' => $user->id]
        );
    }
}
