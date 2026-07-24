<?php

use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallmentPlanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentCardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringBillController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Landing'))->name('home');

Route::get('/sitemap.xml', function () {
    $base = rtrim(config('app.url'), '/');
    $urls = [
        ['loc' => $base.'/', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ];

    $xml = view('sitemap', ['urls' => $urls])->render();

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('transactions', TransactionController::class)->except(['show']);

    Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::put('/bank-accounts/{bank_account}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
    Route::delete('/bank-accounts/{bank_account}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

    Route::get('/payment-cards', [PaymentCardController::class, 'index'])->name('payment-cards.index');
    Route::post('/payment-cards', [PaymentCardController::class, 'store'])->name('payment-cards.store');
    Route::put('/payment-cards/{payment_card}', [PaymentCardController::class, 'update'])->name('payment-cards.update');
    Route::delete('/payment-cards/{payment_card}', [PaymentCardController::class, 'destroy'])->name('payment-cards.destroy');
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
