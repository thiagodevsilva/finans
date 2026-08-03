<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeMarketingMail extends MarketingMailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo ao Levita',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing.welcome',
            with: [
                'userName' => $this->user->name,
                'url' => url('/dashboard'),
                'unsubscribeUrl' => $this->marketingUnsubscribeUrl($this->user),
                'title' => 'Bem-vindo ao Levita',
            ],
        );
    }
}
