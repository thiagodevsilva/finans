<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Services\CreditCardInvoiceService;
use App\Services\CreditCardPaymentService;
use App\Services\InstallmentPlanService;
use App\Services\RecurringBillService;
use App\Services\BalanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly CreditCardInvoiceService $invoiceService,
        private readonly CreditCardPaymentService $paymentService,
        private readonly InstallmentPlanService $installmentPlanService,
        private readonly RecurringBillService $recurringBillService,
        private readonly BalanceService $balances,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $type = $request->input('type');
        $categoryId = $request->input('category_id');

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $transactions = Transaction::query()
            ->with([
                'category:id,name,color',
                'user:id,name',
                'paymentCard:id,name,brand,type,last_four,color',
                'bankAccount:id,name,color',
                'installmentPlan:id,description,total_amount,installments_count',
            ])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) {
                $q->where('status', Transaction::STATUS_CONFIRMED)
                    ->orWhereNull('status');
            })
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => [
                'month' => $month,
                'year' => $year,
                'type' => $type,
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        $allowedTypes = [
            Transaction::TYPE_INCOME,
            Transaction::TYPE_EXPENSE,
            Transaction::TYPE_TRANSFER,
            Transaction::TYPE_INVESTMENT,
        ];

        $type = $request->input('type');
        if (! in_array($type, $allowedTypes, true)) {
            $type = null;
        }

        $paymentCardId = $request->input('payment_card_id');
        if ($paymentCardId) {
            $exists = PaymentCard::query()
                ->whereKey($paymentCardId)
                ->exists();
            if (! $exists) {
                $paymentCardId = null;
            }
        }

        $this->refreshRecurringHorizon();

        return Inertia::render('Transactions/Form', [
            'transaction' => null,
            'defaults' => [
                'type' => $type,
                'payment_card_id' => $paymentCardId,
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'paymentCards' => $this->paymentCardsForForm(),
            'bankAccounts' => $this->bankAccountsForForm(),
            'pendingRecurring' => $this->pendingRecurringForForm(),
            'recurringBills' => $this->recurringBillsForForm(),
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $data = $request->validated();

        if ($data['type'] === Transaction::TYPE_TRANSFER) {
            return $this->storeInvoicePayment($request, $data);
        }

        if (! empty($data['is_installment'])) {
            $plan = $this->installmentPlanService->create($request->user(), [
                'description' => $data['description'],
                'category_id' => $data['category_id'],
                'payment_card_id' => $data['payment_card_id'],
                'total_amount' => $data['total_amount'],
                'installments_count' => $data['installments_count'],
                'purchase_date' => $data['date'],
                'first_installment_date' => $data['date'],
            ]);

            return redirect()
                ->route('installment-plans.show', $plan)
                ->with('success', 'Compra parcelada cadastrada.');
        }

        if (! empty($data['recurring_transaction_id'])) {
            $planned = Transaction::query()->findOrFail($data['recurring_transaction_id']);
            $this->authorize('update', $planned);

            $this->recurringBillService->confirm(
                $planned,
                (float) $data['amount'],
                $data['date'],
                [
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_card_id' => $data['payment_card_id'] ?? null,
                ]
            );

            if (isset($data['description']) || isset($data['category_id'])) {
                $planned->update([
                    'description' => $data['description'] ?? $planned->description,
                    'category_id' => $data['category_id'] ?? $planned->category_id,
                ]);
            }

            return redirect()->route('transactions.index')->with('success', 'Conta fixa marcada como paga.');
        }

        $data['status'] = Transaction::STATUS_CONFIRMED;
        $data['credit_card_invoice_id'] = $this->resolveInvoiceId($data);
        $recurringBillId = $data['recurring_bill_id'] ?? null;
        unset(
            $data['is_installment'],
            $data['total_amount'],
            $data['installments_count'],
            $data['installment_amount'],
            $data['recurring_transaction_id'],
            $data['recurring_bill_id']
        );

        if ($data['type'] === Transaction::TYPE_INVESTMENT) {
            $data['credit_card_invoice_id'] = null;
            $data['payment_card_id'] = null;
            $data['recurring_bill_id'] = null;
        }

        if ($recurringBillId && $data['type'] === Transaction::TYPE_EXPENSE) {
            $bill = RecurringBill::query()->findOrFail($recurringBillId);
            $this->recurringBillService->settleForBill($request->user(), $bill, [
                ...$data,
                'credit_card_invoice_id' => $data['credit_card_invoice_id'],
            ]);

            return redirect()->route('transactions.index')->with('success', 'Transação vinculada à conta fixa.');
        }

        Transaction::create([
            ...$data,
            'user_id' => $request->user()->id,
            'account_id' => $request->user()->account_id,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transação criada com sucesso.');
    }

    public function edit(Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        $this->refreshRecurringHorizon();

        $paymentCards = $this->paymentCardsForForm();

        if (
            $transaction->type === Transaction::TYPE_TRANSFER
            && $transaction->credit_card_invoice_id
            && $transaction->payment_card_id
        ) {
            $paymentCards = $paymentCards->map(function (array $card) use ($transaction) {
                if ($card['id'] !== $transaction->payment_card_id) {
                    return $card;
                }

                $ids = collect($card['invoices'])->pluck('id');
                if (! $ids->contains($transaction->credit_card_invoice_id)) {
                    $invoice = $transaction->creditCardInvoice
                        ?? \App\Models\CreditCardInvoice::query()->find($transaction->credit_card_invoice_id);
                    if ($invoice) {
                        array_unshift($card['invoices'], $this->invoiceService->invoiceOptionPayload($invoice));
                    }
                }

                return $card;
            });
        }

        return Inertia::render('Transactions/Form', [
            'transaction' => $transaction->load([
                'paymentCard:id,name,brand,type,last_four,color,closing_day,due_day',
                'bankAccount:id,name,color',
                'recurringBill:id,description',
                'creditCardInvoice:id,payment_card_id,closing_date,due_date,status',
            ]),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'paymentCards' => $paymentCards,
            'bankAccounts' => $this->bankAccountsForForm(),
            'pendingRecurring' => $this->pendingRecurringForForm(),
            'recurringBills' => $this->recurringBillsForForm(),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $data = $request->validated();

        if ($transaction->type === Transaction::TYPE_TRANSFER || $data['type'] === Transaction::TYPE_TRANSFER) {
            if ($transaction->type !== Transaction::TYPE_TRANSFER || $data['type'] !== Transaction::TYPE_TRANSFER) {
                return back()->with('error', 'Não é possível alterar o tipo de um pagamento de fatura.');
            }

            return $this->updateInvoicePayment($request, $transaction, $data);
        }

        if (! empty($data['is_installment'])) {
            return back()->with('error', 'Não é possível converter um lançamento existente em compra parcelada.');
        }

        $recurringBillId = $data['recurring_bill_id'] ?? null;
        unset(
            $data['is_installment'],
            $data['total_amount'],
            $data['installments_count'],
            $data['installment_amount'],
            $data['recurring_transaction_id'],
            $data['recurring_bill_id']
        );
        $data['credit_card_invoice_id'] = $this->resolveInvoiceId($data);
        $data['recurring_bill_id'] = $recurringBillId;

        if ($data['type'] === Transaction::TYPE_INVESTMENT) {
            $data['credit_card_invoice_id'] = null;
            $data['payment_card_id'] = null;
            $data['recurring_bill_id'] = null;
        }

        if (
            ($data['type'] ?? null) === Transaction::TYPE_EXPENSE
            && ! empty($data['recurring_bill_id'])
        ) {
            $bill = RecurringBill::query()->findOrFail($data['recurring_bill_id']);
            $this->recurringBillService->linkExpenseToBill(
                $request->user(),
                $transaction,
                $bill,
                $data
            );

            return redirect()->route('transactions.index')->with('success', 'Conta fixa marcada como paga.');
        }

        $transaction->update($data);

        return redirect()->route('transactions.index')->with('success', 'Transação atualizada com sucesso.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        if ($transaction->type === Transaction::TYPE_TRANSFER) {
            $this->paymentService->delete($transaction);
        } else {
            $this->balances->recordRetroactiveCashDeletion($transaction);
            $transaction->delete();
        }

        return redirect()->route('transactions.index')->with('success', 'Transação excluída com sucesso.');
    }

    private function storeInvoicePayment(TransactionRequest $request, array $data): RedirectResponse
    {
        $bank = ! empty($data['bank_account_id'])
            ? BankAccount::query()->findOrFail($data['bank_account_id'])
            : null;

        $invoice = \App\Models\CreditCardInvoice::query()->findOrFail($data['credit_card_invoice_id']);

        $this->authorize('pay', $invoice);

        $this->paymentService->pay(
            $request->user(),
            $invoice,
            (float) $data['amount'],
            $data['date'],
            $data['payment_method'],
            $bank,
            $data['description'] ?? null
        );

        return redirect()->route('payment-cards.index')->with('success', 'Pagamento de fatura registrado.');
    }

    private function updateInvoicePayment(
        TransactionRequest $request,
        Transaction $transaction,
        array $data,
    ): RedirectResponse {
        $bank = ! empty($data['bank_account_id'])
            ? BankAccount::query()->findOrFail($data['bank_account_id'])
            : null;

        $invoice = \App\Models\CreditCardInvoice::query()->findOrFail($data['credit_card_invoice_id']);

        $this->authorize('pay', $invoice);

        $this->paymentService->update(
            $transaction,
            $invoice,
            (float) $data['amount'],
            $data['date'],
            $data['payment_method'],
            $bank,
        );

        return redirect()->route('payment-cards.index')->with('success', 'Pagamento de fatura atualizado.');
    }

    private function resolveInvoiceId(array $data): ?string
    {
        if (
            ($data['type'] ?? null) !== Transaction::TYPE_EXPENSE
            || ($data['payment_method'] ?? null) !== Transaction::PAYMENT_CARD
            || empty($data['payment_card_id'])
        ) {
            return null;
        }

        $card = PaymentCard::query()->find($data['payment_card_id']);

        if (! $card || $card->type !== PaymentCard::TYPE_CREDIT) {
            return null;
        }

        return $this->invoiceService->resolveForPurchase($card, $data['date'])->id;
    }

    private function paymentCardsForForm()
    {
        return PaymentCard::query()
            ->with('user:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'brand', 'type', 'last_four', 'color', 'user_id', 'closing_day', 'due_day'])
            ->map(function (PaymentCard $card) {
                $payload = [
                    'id' => $card->id,
                    'name' => $card->name,
                    'brand' => $card->brand,
                    'type' => $card->type,
                    'last_four' => $card->last_four,
                    'color' => $card->color,
                    'user_id' => $card->user_id,
                    'closing_day' => $card->closing_day,
                    'due_day' => $card->due_day,
                    'user' => $card->user,
                    'invoices' => [],
                ];

                if ($card->type === PaymentCard::TYPE_CREDIT) {
                    $payload['invoices'] = $this->invoiceService->invoiceOptionsForCard($card);
                }

                return $payload;
            })
            ->values();
    }

    private function bankAccountsForForm()
    {
        return BankAccount::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);
    }

    private function recurringBillsForForm(): array
    {
        return RecurringBill::query()
            ->where('active', true)
            ->orderBy('description')
            ->get(['id', 'description', 'category_id', 'estimated_amount', 'day_of_month'])
            ->map(fn (RecurringBill $bill) => [
                'id' => $bill->id,
                'description' => $bill->description,
                'category_id' => $bill->category_id,
                'estimated_amount' => (float) $bill->estimated_amount,
                'day_of_month' => $bill->day_of_month,
                'label' => sprintf('%s · dia %d · R$ %s', $bill->description, $bill->day_of_month, number_format((float) $bill->estimated_amount, 2, ',', '.')),
            ])
            ->all();
    }

    private function pendingRecurringForForm(): array
    {
        $today = now()->toDateString();

        return Transaction::query()
            ->with(['category:id,name,color', 'recurringBill:id,description'])
            ->whereNotNull('recurring_bill_id')
            ->where('status', Transaction::STATUS_PLANNED)
            ->where('date', '<=', now()->addMonthsNoOverflow(2)->endOfMonth()->toDateString())
            ->orderByRaw('CASE WHEN date < ? THEN 0 ELSE 1 END', [$today])
            ->orderBy('date')
            ->get()
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'description' => $tx->description,
                'amount' => (float) $tx->amount,
                'date' => $tx->date->toDateString(),
                'category_id' => $tx->category_id,
                'category' => $tx->category,
                'payment_method' => $tx->payment_method,
                'payment_card_id' => $tx->payment_card_id,
                'recurring_bill_id' => $tx->recurring_bill_id,
                'overdue' => $tx->date->toDateString() < $today,
                'label' => sprintf(
                    '%s · %s · %s%s',
                    $tx->description,
                    $tx->date->format('d/m/Y'),
                    number_format((float) $tx->amount, 2, ',', '.'),
                    $tx->date->toDateString() < $today ? ' · vencida' : ' · pendente'
                ),
            ])
            ->all();
    }

    private function refreshRecurringHorizon(): void
    {
        RecurringBill::query()
            ->where('active', true)
            ->get()
            ->each(fn (RecurringBill $bill) => $this->recurringBillService->materializeAhead($bill, 3));
    }
}
