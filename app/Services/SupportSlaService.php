<?php

namespace App\Services;

use App\Models\SupportTicket;
use Carbon\Carbon;

class SupportSlaService
{
    public const BUSINESS_HOURS = 72;

    /**
     * Adiciona N horas úteis (seg–sex, 24h por dia útil; pula sáb/dom).
     */
    public function dueAt(Carbon $from, int $hours = self::BUSINESS_HOURS): Carbon
    {
        $cursor = $from->copy();
        $remaining = $hours;

        while ($remaining > 0) {
            $cursor->addHour();

            if ($cursor->isWeekend()) {
                continue;
            }

            $remaining--;
        }

        return $cursor;
    }

    public function isBreached(SupportTicket $ticket, ?Carbon $now = null): bool
    {
        if ($ticket->first_responded_at !== null) {
            return $ticket->first_responded_at->gt($ticket->sla_due_at);
        }

        return ($now ?? now())->gt($ticket->sla_due_at);
    }

    public function wasMet(SupportTicket $ticket): ?bool
    {
        if ($ticket->first_responded_at === null) {
            return null;
        }

        return $ticket->first_responded_at->lte($ticket->sla_due_at);
    }

    /**
     * @return 'on_time'|'breached'|'met'|'missed'
     */
    public function statusLabel(SupportTicket $ticket, ?Carbon $now = null): string
    {
        if ($ticket->first_responded_at !== null) {
            return $this->wasMet($ticket) ? 'met' : 'missed';
        }

        return $this->isBreached($ticket, $now) ? 'breached' : 'on_time';
    }
}
