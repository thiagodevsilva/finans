<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditCardPaymentRequest;
use App\Http\Requests\PaymentCardRequest;
use App\Models\BankAccount;
use App\Models\CreditCardInvoice;
use App\Models\PaymentCard;
use App\Services\CreditCardInvoiceService;
use App\Services\CreditCardPaymentService;
use Illuminate\Http\RedirectResponse;
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

                $openInvoices = CreditCardInvoice::query()
                    ->where('payment_card_id', $card->id)
                    ->whereIn('status', [
                        CreditCardInvoice::STATUS_OPEN,
                        CreditCardInvoice::STATUS_CLOSED,
                        CreditCardInvoice::STATUS_PARTIAL,
                    ])
                    ->orderBy('due_date')
                    ->get()
                    ->map(fn (CreditCardInvoice $invoice) => [
                        'id' => $invoice->id,
                        'closing_date' => $invoice->closing_date->toDateString(),
                        'due_date' => $invoice->due_date->toDateString(),
                        'status' => $invoice->status,
                        'status_label' => CreditCardInvoice::statusLabel($invoice->status),
                        'total' => $invoice->totalCharges(),
                        'paid_amount' => (float) $invoice->paid_amount,
                        'remaining' => $invoice->remainingAmount(),
                    ]);

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
                    'invoices' => $openInvoices,
                ];
            });

        return Inertia::render('PaymentCards/Index', [
            'cards' => $cards,
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

        $bankAccount = BankAccount::query()->findOrFail($request->validated('bank_account_id'));

        $this->paymentService->pay(
            $request->user(),
            $creditCardInvoice,
            $bankAccount,
            (float) $request->validated('amount'),
            $request->validated('date'),
            $request->validated('description')
        );

        return back()->with('success', 'Pagamento de fatura registrado.');
    }
}
