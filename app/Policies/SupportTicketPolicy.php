<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->account_id !== null;
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->account_id === $ticket->account_id;
    }

    public function create(User $user): bool
    {
        return ! $user->isAdmin() && $user->account_id !== null;
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->account_id === $ticket->account_id;
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->account_id !== $ticket->account_id) {
            return false;
        }

        return $ticket->user_id === $user->id || $user->isOwner();
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->isAdmin();
    }
}
