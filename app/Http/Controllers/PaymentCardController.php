<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditCardPaymentRequest;
use App\Http\Requests\PaymentCardRequest;
use App\Models\BankAccount;
use App\Models\CreditCardInvoice;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Services\CreditCardInvoiceService;
use App\Services\CreditCardPaymentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentCardController extends Controller
{
    public function __construct(
        private readonly CreditCardInvoiceService $invoiceService,
        private readonly CreditCardPaymentService $paymentService
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', PaymentCard::class);

        $user = auth()->user();

        $cards = PaymentCard::query()
            ->with(['user:id,name', 'bankAccount:id,name,color'])
            ->orderBy('name')
            ->get()
            ->map(function (PaymentCard $card) use ($user) {
                if ($card->type === PaymentCard::TYPE_CREDIT) {
                    $this->invoiceService->ensureUpcomingInvoices($card);
                }

                return [
                    'id' => $card->id,
                    'name' => $card->name,
                    'brand' => $card->brand,
                    'brand_label' => PaymentCard::brandLabel($card->brand),
                    'type' => $card->type,
                    'type_label' => PaymentCard::typeLabel($card->type),
                    'last_four' => $card->last_four,
                    'color' => $card->color,
                    'bank_account_id' => $card->bank_account_id,
                    'bank_account' => $card->bankAccount,
                    'closing_day' => $card->closing_day,
                    'due_day' => $card->due_day,
                    'user_id' => $card->user_id,
                    'user' => $card->user,
                    'can_edit' => $user->isOwner() || $card->user_id === $user->id,
                ];
            });

        $recentPayments = Transaction::query()
            ->with([
                'paymentCard:id,name,brand,type,last_four,color',
                'bankAccount:id,name',
            ])
            ->where('type', Transaction::TYPE_TRANSFER)
            ->whereNotNull('credit_card_invoice_id')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'description' => $tx->description,
                'amount' => (float) $tx->amount,
                'date' => $tx->date->toDateString(),
                'payment_card' => $tx->paymentCard,
                'bank_account' => $tx->bankAccount,
            ]);

        return Inertia::render('PaymentCards/Index', [
            'cards' => $cards,
            'recentPayments' => $recentPayments,
            'bankAccounts' => BankAccount::query()->orderBy('name')->get(['id', 'name', 'color']),
            'brands' => collect(PaymentCard::BRANDS)->map(fn ($brand) => [
                'value' => $brand,
                'label' => PaymentCard::brandLabel($brand),
            ]),
            'types' => collect(PaymentCard::TYPES)->map(fn ($type) => [
                'value' => $type,
                'label' => PaymentCard::typeLabel($type),
            ]),
        ]);
    }

    public function payments(Request $request): Response
    {
        $this->authorize('viewAny', PaymentCard::class);

        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $cardId = $request->input('payment_card_id');

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $payments = Transaction::query()
            ->with([
                'paymentCard:id,name,brand,type,last_four,color',
                'bankAccount:id,name',
            ])
            ->where('type', Transaction::TYPE_TRANSFER)
            ->whereNotNull('credit_card_invoice_id')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($cardId, fn ($q) => $q->where('payment_card_id', $cardId))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Transaction $tx) => [
                'id' => $tx->id,
                'description' => $tx->description,
                'amount' => (float) $tx->amount,
                'date' => $tx->date->toDateString(),
                'payment_card' => $tx->paymentCard,
                'bank_account' => $tx->bankAccount,
            ]);

        return Inertia::render('PaymentCards/Payments', [
            'payments' => $payments,
            'cards' => PaymentCard::query()
                ->where('type', PaymentCard::TYPE_CREDIT)
                ->orderBy('name')
                ->get(['id', 'name', 'brand', 'type', 'last_four', 'color']),
            'filters' => [
                'month' => $month,
                'year' => $year,
                'payment_card_id' => $cardId,
            ],
        ]);
    }

    public function store(PaymentCardRequest $request): RedirectResponse
    {
        $this->authorize('create', PaymentCard::class);

        $card = PaymentCard::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'account_id' => $request->user()->account_id,
        ]);

        if ($card->type === PaymentCard::TYPE_CREDIT) {
            $this->invoiceService->ensureUpcomingInvoices($card);
        }

        return back()->with('success', 'Cartão cadastrado com sucesso.');
    }

    public function update(PaymentCardRequest $request, PaymentCard $paymentCard): RedirectResponse
    {
        $this->authorize('update', $paymentCard);

        $paymentCard->update($request->validated());

        if ($paymentCard->type === PaymentCard::TYPE_CREDIT) {
            $this->invoiceService->ensureUpcomingInvoices($paymentCard->fresh());
        }

        return back()->with('success', 'Cartão atualizado com sucesso.');
    }

    public function destroy(PaymentCard $paymentCard): RedirectResponse
    {
        $this->authorize('delete', $paymentCard);

        if ($paymentCard->transactions()->exists()) {
            return back()->with('error', 'Não é possível excluir um cartão com lançamentos.');
        }

        $paymentCard->delete();

        return back()->with('success', 'Cartão excluído com sucesso.');
    }

    public function payInvoice(
        CreditCardPaymentRequest $request,
        CreditCardInvoice $creditCardInvoice
    ): RedirectResponse {
        $this->authorize('pay', $creditCardInvoice);

        $bankAccount = $request->validated('bank_account_id')
            ? BankAccount::query()->findOrFail($request->validated('bank_account_id'))
            : null;

        $this->paymentService->pay(
            $request->user(),
            $creditCardInvoice,
            (float) $request->validated('amount'),
            $request->validated('date'),
            $request->validated('payment_method'),
            $bankAccount,
            $request->validated('description')
        );

        return back()->with('success', 'Pagamento de fatura registrado.');
    }
}
