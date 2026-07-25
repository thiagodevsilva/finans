<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmRecurringTransactionRequest;
use App\Http\Requests\RecurringBillRequest;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Services\RecurringBillService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecurringBillController extends Controller
{
    public function __construct(
        private readonly RecurringBillService $service
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', RecurringBill::class);

        $user = auth()->user();

        RecurringBill::query()
            ->where('active', true)
            ->get()
            ->each(fn (RecurringBill $bill) => $this->service->materializeAhead($bill, 3));

        $bills = RecurringBill::query()
            ->with(['category:id,name,color', 'user:id,name', 'paymentCard:id,name,color', 'bankAccount:id,name,color'])
            ->where('active', true)
            ->orderBy('description')
            ->get()
            ->map(fn (RecurringBill $bill) => [
                'id' => $bill->id,
                'description' => $bill->description,
                'estimated_amount' => (float) $bill->estimated_amount,
                'day_of_month' => $bill->day_of_month,
                'payment_method' => $bill->payment_method,
                'payment_card_id' => $bill->payment_card_id,
                'payment_card' => $bill->paymentCard,
                'bank_account_id' => $bill->bank_account_id,
                'bank_account' => $bill->bankAccount,
                'category_id' => $bill->category_id,
                'category' => $bill->category,
                'start_date' => $bill->start_date->toDateString(),
                'end_date' => $bill->end_date?->toDateString(),
                'active' => $bill->active,
                'user_id' => $bill->user_id,
                'user' => $bill->user,
                'can_edit' => $user->isOwner() || $bill->user_id === $user->id,
            ]);

        $upcoming = Transaction::query()
            ->with(['category:id,name,color', 'recurringBill:id,description'])
            ->whereNotNull('recurring_bill_id')
            ->where('status', Transaction::STATUS_PLANNED)
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->addMonthsNoOverflow(2)->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'description' => $tx->description,
                'amount' => (float) $tx->amount,
                'date' => $tx->date->toDateString(),
                'category' => $tx->category,
                'recurring_bill_id' => $tx->recurring_bill_id,
                'can_edit' => $user->isOwner() || $tx->user_id === $user->id,
            ]);

        return Inertia::render('RecurringBills/Index', [
            'bills' => $bills,
            'upcoming' => $upcoming,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'paymentCards' => PaymentCard::query()->orderBy('name')->get(['id', 'name', 'brand', 'type', 'last_four', 'color']),
            'bankAccounts' => BankAccount::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(RecurringBillRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringBill::class);

        $this->service->create($request->user(), $request->validated());

        return back()->with('success', 'Conta fixa cadastrada.');
    }

    public function update(RecurringBillRequest $request, RecurringBill $recurringBill): RedirectResponse
    {
        $this->authorize('update', $recurringBill);

        $data = $request->validated();
        $propagate = $data['propagate'] ?? 'none';
        $propagateFrom = $data['propagate_from'] ?? null;
        unset($data['propagate'], $data['propagate_from']);

        $recurringBill->update($data);

        if ($recurringBill->active) {
            $this->service->materializeAhead($recurringBill->fresh(), 3);
        }

        if (in_array($propagate, ['open', 'from_date'], true)) {
            $updated = $this->service->propagateToPlanned(
                $recurringBill->fresh(),
                $propagate === 'from_date' ? 'from_date' : 'open',
                $propagateFrom
            );

            return back()->with('success', "Conta fixa atualizada. {$updated} lançamento(s) pendente(s) ajustado(s).");
        }

        return back()->with('success', 'Conta fixa atualizada.');
    }

    public function destroy(RecurringBill $recurringBill): RedirectResponse
    {
        $this->authorize('delete', $recurringBill);

        Transaction::query()
            ->where('recurring_bill_id', $recurringBill->id)
            ->where('status', Transaction::STATUS_PLANNED)
            ->delete();

        $hasHistory = Transaction::query()
            ->where('recurring_bill_id', $recurringBill->id)
            ->whereIn('status', [Transaction::STATUS_CONFIRMED, Transaction::STATUS_SKIPPED])
            ->exists();

        if ($hasHistory) {
            $recurringBill->update(['active' => false]);

            return back()->with('success', 'Conta fixa desativada (já havia pagamentos no histórico).');
        }

        $recurringBill->delete();

        return back()->with('success', 'Conta fixa excluída.');
    }

    public function confirm(
        ConfirmRecurringTransactionRequest $request,
        Transaction $transaction
    ): RedirectResponse {
        $this->authorize('update', $transaction);

        if (! $transaction->recurring_bill_id || $transaction->status !== Transaction::STATUS_PLANNED) {
            return back()->with('error', 'Lançamento inválido para confirmação.');
        }

        $this->service->confirm(
            $transaction,
            (float) $request->validated('amount'),
            $request->validated('date')
        );

        return back()->with('success', 'Conta fixa confirmada.');
    }

    public function skip(Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        if (! $transaction->recurring_bill_id || $transaction->status !== Transaction::STATUS_PLANNED) {
            return back()->with('error', 'Lançamento inválido para pular.');
        }

        $this->service->skip($transaction);

        return back()->with('success', 'Lançamento pulado neste mês.');
    }
}
