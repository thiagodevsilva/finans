<?php

namespace App\Listeners;

use App\Mail\WelcomeMarketingMail;
use Illuminate\Auth\Events\Registered;

class SendWelcomeMarketingEmail
{
    public function handle(Registered $event): void
    {
        WelcomeMarketingMail::sendTo($event->user);
    }
}
