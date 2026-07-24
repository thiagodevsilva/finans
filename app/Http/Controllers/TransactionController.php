<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Services\CreditCardInvoiceService;
use App\Services\InstallmentPlanService;
use App\Services\RecurringBillService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly CreditCardInvoiceService $invoiceService,
        private readonly InstallmentPlanService $installmentPlanService,
        private readonly RecurringBillService $recurringBillService,
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

    public function create(): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Form', [
            'transaction' => null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'paymentCards' => $this->paymentCardsForForm(),
            'bankAccounts' => $this->bankAccountsForForm(),
            'pendingRecurring' => $this->pendingRecurringForForm(),
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $data = $request->validated();

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
        unset($data['is_installment'], $data['total_amount'], $data['installments_count'], $data['installment_amount'], $data['recurring_transaction_id']);

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

        if ($transaction->type === Transaction::TYPE_TRANSFER) {
            return redirect()->route('transactions.index')
                ->with('error', 'Pagamentos de fatura não podem ser editados por este formulário.');
        }

        return Inertia::render('Transactions/Form', [
            'transaction' => $transaction->load([
                'paymentCard:id,name,brand,type,last_four,color',
                'bankAccount:id,name,color',
            ]),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'paymentCards' => $this->paymentCardsForForm(),
            'bankAccounts' => $this->bankAccountsForForm(),
            'pendingRecurring' => $this->pendingRecurringForForm(),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        if ($transaction->type === Transaction::TYPE_TRANSFER) {
            return redirect()->route('transactions.index')
                ->with('error', 'Pagamentos de fatura não podem ser editados por este formulário.');
        }

        $data = $request->validated();

        if (! empty($data['is_installment'])) {
            return back()->with('error', 'Não é possível converter um lançamento existente em compra parcelada.');
        }

        unset($data['is_installment'], $data['total_amount'], $data['installments_count'], $data['installment_amount'], $data['recurring_transaction_id']);
        $data['credit_card_invoice_id'] = $this->resolveInvoiceId($data);

        $transaction->update($data);

        return redirect()->route('transactions.index')->with('success', 'Transação atualizada com sucesso.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Transação excluída com sucesso.');
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
            ->get(['id', 'name', 'brand', 'type', 'last_four', 'color', 'user_id', 'closing_day', 'due_day']);
    }

    private function bankAccountsForForm()
    {
        return BankAccount::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);
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
}
