<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsMarketingUnsubscribeUrl;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

abstract class MarketingMailable extends Mailable
{
    use BuildsMarketingUnsubscribeUrl;
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    abstract public function envelope(): Envelope;

    abstract public function content(): Content;

    /**
     * Envia só se o destinatário aceitar e-mails de marketing.
     * Preferir este método a Mail::to()->send() para respeitar o opt-out também sob Mail::fake().
     */
    public static function sendTo(User $user, mixed ...$arguments): void
    {
        if (! $user->wantsMarketingEmails()) {
            return;
        }

        Mail::to($user)->send(new static($user, ...$arguments));
    }

    /**
     * Defesa em profundidade quando o Mailable é enviado direto (mailer real).
     */
    public function send($mailer)
    {
        if (! $this->user->wantsMarketingEmails()) {
            return null;
        }

        return parent::send($mailer);
    }
}
