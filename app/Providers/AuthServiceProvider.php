<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\MemberPolicy;
use App\Policies\PaymentCardPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Category::class => CategoryPolicy::class,
        PaymentCard::class => PaymentCardPolicy::class,
        Transaction::class => TransactionPolicy::class,
        User::class => MemberPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
