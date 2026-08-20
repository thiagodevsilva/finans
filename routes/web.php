<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminSupportTicketController;
use App\Http\Controllers\BalanceAnchorController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardPinnedChartController;
use App\Http\Controllers\Email\UnsubscribeController;
use App\Http\Controllers\InstallmentPlanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentCardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringBillController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Landing'))->name('home');

Route::get('/email/unsubscribe/{user}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('email.unsubscribe');

Route::get('/robots.txt', function () {
    $base = rtrim(url('/'), '/');

    $body = implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Disallow: /dashboard',
        'Disallow: /transactions',
        'Disallow: /bank-accounts',
        'Disallow: /payment-cards',
        'Disallow: /credit-card-payments',
        'Disallow: /installment-plans',
        'Disallow: /recurring-bills',
        'Disallow: /categories',
        'Disallow: /members',
        'Disallow: /reports',
        'Disallow: /support',
        'Disallow: /profile',
        'Disallow: /admin',
        'Disallow: /login',
        'Disallow: /register',
        'Disallow: /forgot-password',
        'Disallow: /reset-password',
        'Disallow: /verify-email',
        'Disallow: /confirm-password',
        '',
        'Sitemap: '.$base.'/sitemap.xml',
        '',
    ]);

    return response($body, 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

Route::get('/sitemap.xml', function () {
    $base = rtrim(url('/'), '/');
    $urls = [
        [
            'loc' => $base.'/',
            'lastmod' => now()->toDateString(),
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ],
    ];

    return response()
        ->view('sitemap', ['urls' => $urls], 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::middleware(['auth', 'last.seen'])->group(function () {
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('/support', [AdminSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support/{support_ticket}', [AdminSupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::patch('/support/{support_ticket}', [AdminSupportTicketController::class, 'update'])->name('support-tickets.update');
        Route::post('/support/{support_ticket}/replies', [AdminSupportTicketController::class, 'storeReply'])->name('support-tickets.replies.store');
        Route::post('/support/{support_ticket}/close', [AdminSupportTicketController::class, 'close'])->name('support-tickets.close');
    });

    // Anexo acessível por família (mesma conta) ou admin
    Route::get('/support/attachments/{attachment}', [SupportTicketController::class, 'showAttachment'])
        ->name('support-tickets.attachments.show');

    Route::middleware('family')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::put('/dashboard/pinned-chart', [DashboardPinnedChartController::class, 'update'])
            ->name('dashboard.pinned-chart');

        Route::post('/balance-anchors', [BalanceAnchorController::class, 'store'])->name('balance-anchors.store');
        Route::post('/balance-anchors/keep', [BalanceAnchorController::class, 'keep'])->name('balance-anchors.keep');
        Route::post('/balance-anchors/dismiss-stale', [BalanceAnchorController::class, 'dismissStale'])->name('balance-anchors.dismiss-stale');

        Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
        Route::delete('/onboarding', [OnboardingController::class, 'destroy'])->name('onboarding.destroy');

        Route::resource('transactions', TransactionController::class)->except(['show']);

        Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
        Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::put('/bank-accounts/{bank_account}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
        Route::delete('/bank-accounts/{bank_account}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

        Route::get('/payment-cards', [PaymentCardController::class, 'index'])->name('payment-cards.index');
        Route::post('/payment-cards', [PaymentCardController::class, 'store'])->name('payment-cards.store');
        Route::put('/payment-cards/{payment_card}', [PaymentCardController::class, 'update'])->name('payment-cards.update');
        Route::delete('/payment-cards/{payment_card}', [PaymentCardController::class, 'destroy'])->name('payment-cards.destroy');
        Route::get('/credit-card-payments', [PaymentCardController::class, 'payments'])->name('credit-card-payments.index');
        Route::post('/credit-card-invoices/{credit_card_invoice}/pay', [PaymentCardController::class, 'payInvoice'])
            ->name('credit-card-invoices.pay');

        Route::get('/installment-plans/create', [InstallmentPlanController::class, 'create'])->name('installment-plans.create');
        Route::post('/installment-plans', [InstallmentPlanController::class, 'store'])->name('installment-plans.store');
        Route::get('/installment-plans/{installment_plan}', [InstallmentPlanController::class, 'show'])->name('installment-plans.show');
        Route::delete('/installment-plans/{installment_plan}', [InstallmentPlanController::class, 'destroy'])->name('installment-plans.destroy');

        Route::get('/recurring-bills', [RecurringBillController::class, 'index'])->name('recurring-bills.index');
        Route::post('/recurring-bills', [RecurringBillController::class, 'store'])->name('recurring-bills.store');
        Route::put('/recurring-bills/{recurring_bill}', [RecurringBillController::class, 'update'])->name('recurring-bills.update');
        Route::delete('/recurring-bills/{recurring_bill}', [RecurringBillController::class, 'destroy'])->name('recurring-bills.destroy');
        Route::post('/recurring-transactions/{transaction}/confirm', [RecurringBillController::class, 'confirm'])
            ->name('recurring-transactions.confirm');
        Route::post('/recurring-transactions/{transaction}/skip', [RecurringBillController::class, 'skip'])
            ->name('recurring-transactions.skip');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('/reports', ReportController::class)->name('reports.index');

        Route::get('/support', [SupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support-tickets.create');
        Route::post('/support', [SupportTicketController::class, 'store'])->name('support-tickets.store');
        Route::get('/support/{support_ticket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('/support/{support_ticket}/replies', [SupportTicketController::class, 'storeReply'])->name('support-tickets.replies.store');
        Route::post('/support/{support_ticket}/close', [SupportTicketController::class, 'close'])->name('support-tickets.close');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

require __DIR__.'/auth.php';
