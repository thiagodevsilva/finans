<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseSupportTicketRequest;
use App\Http\Requests\StoreSupportTicketReplyRequest;
use App\Http\Requests\UpdateAdminSupportTicketRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\SupportSlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSupportTicketController extends Controller
{
    public function __construct(
        private SupportSlaService $sla
    ) {
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $slaFilter = $request->string('sla')->toString();

        $query = SupportTicket::withoutGlobalScope('account')
            ->with(['user:id,name,email', 'account:id,name'])
            ->orderByDesc('created_at');

        if ($status !== '' && in_array($status, SupportTicket::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($slaFilter === 'breached') {
            $query->whereNull('first_responded_at')
                ->where('sla_due_at', '<', now())
                ->where('status', '!=', SupportTicket::STATUS_CLOSED);
        }

        $tickets = $query->paginate(30)->withQueryString()->through(function (SupportTicket $ticket) {
            $slaKey = $this->sla->statusLabel($ticket);

            return [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'status_label' => $this->statusLabel($ticket->status),
                'author_name' => $ticket->user?->name,
                'author_email' => $ticket->user?->email,
                'family_name' => $ticket->account?->name,
                'created_at' => $ticket->created_at?->toDateTimeString(),
                'sla_due_at' => $ticket->sla_due_at?->toDateTimeString(),
                'sla_status' => $slaKey,
                'sla_label' => $this->slaLabel($slaKey),
            ];
        });

        return Inertia::render('Admin/SupportTickets/Index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => in_array($status, SupportTicket::STATUSES, true) ? $status : '',
                'sla' => $slaFilter === 'breached' ? 'breached' : '',
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function show(SupportTicket $supportTicket): Response
    {
        $supportTicket->load([
            'user:id,name,email',
            'account:id,name',
            'attachments',
            'replies.user:id,name',
            'closedByUser:id,name',
        ]);

        $slaKey = $this->sla->statusLabel($supportTicket);

        return Inertia::render('Admin/SupportTickets/Show', [
            'ticket' => [
                'id' => $supportTicket->id,
                'title' => $supportTicket->title,
                'description' => $supportTicket->description,
                'status' => $supportTicket->status,
                'status_label' => $this->statusLabel($supportTicket->status),
                'author_name' => $supportTicket->user?->name,
                'author_email' => $supportTicket->user?->email,
                'family_name' => $supportTicket->account?->name,
                'closed_reason' => $supportTicket->closed_reason,
                'closed_by_name' => $supportTicket->closedByUser?->name,
                'closed_at' => $supportTicket->closed_at?->toDateTimeString(),
                'created_at' => $supportTicket->created_at?->toDateTimeString(),
                'sla_due_at' => $supportTicket->sla_due_at?->toDateTimeString(),
                'first_responded_at' => $supportTicket->first_responded_at?->toDateTimeString(),
                'sla_status' => $slaKey,
                'sla_label' => $this->slaLabel($slaKey),
                'attachments' => $supportTicket->attachments->map(fn (SupportTicketAttachment $a) => [
                    'id' => $a->id,
                    'original_name' => $a->original_name,
                    'url' => route('support-tickets.attachments.show', $a),
                ]),
                'replies' => $supportTicket->replies->map(fn ($reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'is_staff' => $reply->is_staff,
                    'author_name' => $reply->user?->name,
                    'created_at' => $reply->created_at?->toDateTimeString(),
                ]),
            ],
            'statuses' => $this->statusOptions(),
            'canReply' => ! $supportTicket->isClosed(),
        ]);
    }

    public function update(UpdateAdminSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $status = $request->validated('status');
        $data = ['status' => $status];

        if ($status === SupportTicket::STATUS_CLOSED) {
            $data['closed_reason'] = $request->validated('closed_reason');
            $data['closed_by'] = $request->user()->id;
            $data['closed_at'] = now();
        } else {
            $data['closed_reason'] = null;
            $data['closed_by'] = null;
            $data['closed_at'] = null;
        }

        $supportTicket->update($data);

        return back()->with('success', 'Status atualizado.');
    }

    public function storeReply(StoreSupportTicketReplyRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        abort_if($supportTicket->isClosed(), 422, 'Chamado fechado.');

        $supportTicket->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_staff' => true,
        ]);

        $updates = ['status' => SupportTicket::STATUS_ANSWERED];

        if ($supportTicket->first_responded_at === null) {
            $updates['first_responded_at'] = now();
        }

        $supportTicket->update($updates);

        return back()->with('success', 'Resposta enviada.');
    }

    public function close(CloseSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        abort_if($supportTicket->isClosed(), 422, 'Chamado já fechado.');

        $supportTicket->update([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_reason' => $request->validated('closed_reason'),
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return back()->with('success', 'Chamado fechado.');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            SupportTicket::STATUS_OPEN => 'Aberto',
            SupportTicket::STATUS_IN_PROGRESS => 'Em andamento',
            SupportTicket::STATUS_ANSWERED => 'Respondido',
            SupportTicket::STATUS_CLOSED => 'Fechado',
            default => $status,
        };
    }

    private function slaLabel(string $key): string
    {
        return match ($key) {
            'on_time' => 'No prazo',
            'breached' => 'Atrasado',
            'met' => 'Respondido no prazo',
            'missed' => 'Respondido atrasado',
            default => $key,
        };
    }

    private function statusOptions(): array
    {
        return collect(SupportTicket::STATUSES)
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => $this->statusLabel($status),
            ])
            ->values()
            ->all();
    }
}
