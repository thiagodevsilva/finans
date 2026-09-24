<?php

namespace App\Services;

use App\Mail\SupportTicketActivityMail;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Mail;

class SupportTicketNotifier
{
    public function notify(SupportTicket $ticket, string $event, ?string $detail = null): void
    {
        $email = config('support.notify_email');

        if (! is_string($email) || $email === '') {
            return;
        }

        $ticket->loadMissing(['user:id,name,email', 'account:id,name']);

        try {
            Mail::to($email)->send(new SupportTicketActivityMail($ticket, $event, $detail));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
