<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseSupportTicketRequest;
use App\Http\Requests\StoreSupportTicketReplyRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\SupportSlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function __construct(
        private SupportSlaService $sla
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SupportTicket::class);

        $status = $request->string('status')->toString();
        $query = SupportTicket::query()
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if ($status !== '' && in_array($status, SupportTicket::STATUSES, true)) {
            $query->where('status', $status);
        }

        $tickets = $query->paginate(20)->withQueryString()->through(fn (SupportTicket $ticket) => [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'status' => $ticket->status,
            'status_label' => $this->statusLabel($ticket->status),
            'author_name' => $ticket->user?->name,
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'awaiting_response' => in_array($ticket->status, [
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
            ], true),
        ]);

        return Inertia::render('SupportTickets/Index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => in_array($status, SupportTicket::STATUSES, true) ? $status : '',
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SupportTicket::class);

        return Inertia::render('SupportTickets/Create');
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $this->authorize('create', SupportTicket::class);

        $user = $request->user();
        $now = now();

        $ticket = DB::transaction(function () use ($request, $user, $now) {
            $ticket = SupportTicket::create([
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'status' => SupportTicket::STATUS_OPEN,
                'sla_due_at' => $this->sla->dueAt($now),
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store("support-tickets/{$ticket->id}", 'local');

                $ticket->attachments()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $ticket;
        });

        return redirect()
            ->route('support-tickets.show', $ticket)
            ->with('success', 'Chamado aberto com sucesso.');
    }

    public function show(SupportTicket $supportTicket): Response
    {
        $this->authorize('view', $supportTicket);

        $supportTicket->load([
            'user:id,name',
            'attachments',
            'replies.user:id,name',
            'closedByUser:id,name',
        ]);

        return Inertia::render('SupportTickets/Show', [
            'ticket' => $this->serializeTicket($supportTicket),
            'canReply' => auth()->user()->can('reply', $supportTicket),
            'canClose' => auth()->user()->can('close', $supportTicket),
        ]);
    }

    public function storeReply(StoreSupportTicketReplyRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('reply', $supportTicket);

        $supportTicket->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_staff' => false,
        ]);

        if ($supportTicket->status === SupportTicket::STATUS_ANSWERED) {
            $supportTicket->update(['status' => SupportTicket::STATUS_OPEN]);
        }

        return back()->with('success', 'Mensagem enviada.');
    }

    public function close(CloseSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('close', $supportTicket);

        $supportTicket->update([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_reason' => $request->validated('closed_reason'),
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return redirect()
            ->route('support-tickets.show', $supportTicket)
            ->with('success', 'Chamado fechado.');
    }

    public function showAttachment(SupportTicketAttachment $attachment): StreamedResponse
    {
        $ticket = SupportTicket::withoutGlobalScope('account')
            ->findOrFail($attachment->support_ticket_id);

        $this->authorize('view', $ticket);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime]
        );
    }

    private function serializeTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'status_label' => $this->statusLabel($ticket->status),
            'author_name' => $ticket->user?->name,
            'closed_reason' => $ticket->closed_reason,
            'closed_by_name' => $ticket->closedByUser?->name,
            'closed_at' => $ticket->closed_at?->toDateTimeString(),
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'attachments' => $ticket->attachments->map(fn (SupportTicketAttachment $a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'url' => route('support-tickets.attachments.show', $a),
            ]),
            'replies' => $ticket->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'body' => $reply->body,
                'is_staff' => $reply->is_staff,
                'author_name' => $reply->user?->name,
                'created_at' => $reply->created_at?->toDateTimeString(),
            ]),
        ];
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
