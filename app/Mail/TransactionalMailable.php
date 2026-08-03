<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class TransactionalMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    abstract public function envelope(): Envelope;

    abstract public function content(): Content;
}
