<?php

namespace App\Providers;

use App\Models\BalanceAnchor;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\CreditCardInvoice;
use App\Models\InstallmentPlan;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\BalanceAnchorPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CreditCardInvoicePolicy;
use App\Policies\InstallmentPlanPolicy;
use App\Policies\MemberPolicy;
use App\Policies\PaymentCardPolicy;
use App\Policies\RecurringBillPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        BalanceAnchor::class => BalanceAnchorPolicy::class,
        BankAccount::class => BankAccountPolicy::class,
        Category::class => CategoryPolicy::class,
        CreditCardInvoice::class => CreditCardInvoicePolicy::class,
        InstallmentPlan::class => InstallmentPlanPolicy::class,
        PaymentCard::class => PaymentCardPolicy::class,
        RecurringBill::class => RecurringBillPolicy::class,
        SupportTicket::class => SupportTicketPolicy::class,
        Transaction::class => TransactionPolicy::class,
        User::class => MemberPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
