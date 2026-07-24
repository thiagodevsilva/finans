<?php

namespace App\Policies;

use App\Models\CreditCardInvoice;
use App\Models\User;

class CreditCardInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditCardInvoice $invoice): bool
    {
        return $user->account_id === $invoice->account_id;
    }

    public function pay(User $user, CreditCardInvoice $invoice): bool
    {
        return $user->account_id === $invoice->account_id;
    }
}
