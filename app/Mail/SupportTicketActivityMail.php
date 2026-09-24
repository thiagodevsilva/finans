<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public string $event,
        public ?string $detail = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $prefix = match ($this->event) {
            'opened' => 'Novo chamado',
            'replied' => 'Nova mensagem no chamado',
            'closed' => 'Chamado fechado',
            default => 'Atualização de suporte',
        };

        return new Envelope(
            subject: "{$prefix}: {$this->ticket->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support.activity',
            with: [
                'ticket' => $this->ticket,
                'event' => $this->event,
                'eventLabel' => match ($this->event) {
                    'opened' => 'Novo chamado aberto',
                    'replied' => 'Nova mensagem da família',
                    'closed' => 'Chamado fechado pela família',
                    default => 'Atualização',
                },
                'detail' => $this->detail,
                'adminUrl' => route('admin.support-tickets.show', $this->ticket),
                'authorName' => $this->ticket->user?->name,
                'authorEmail' => $this->ticket->user?->email,
                'familyName' => $this->ticket->account?->name,
            ],
        );
    }
}
