<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentCardRequest;
use App\Models\PaymentCard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentCardController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', PaymentCard::class);

        $user = auth()->user();

        $cards = PaymentCard::query()
            ->with('user:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentCard $card) => [
                'id' => $card->id,
                'name' => $card->name,
                'brand' => $card->brand,
                'brand_label' => PaymentCard::brandLabel($card->brand),
                'last_four' => $card->last_four,
                'color' => $card->color,
                'user_id' => $card->user_id,
                'user' => $card->user,
                'can_edit' => $user->isOwner() || $card->user_id === $user->id,
            ]);

        return Inertia::render('PaymentCards/Index', [
            'cards' => $cards,
            'brands' => collect(PaymentCard::BRANDS)->map(fn ($brand) => [
                'value' => $brand,
                'label' => PaymentCard::brandLabel($brand),
            ]),
        ]);
    }

    public function store(PaymentCardRequest $request): RedirectResponse
    {
        $this->authorize('create', PaymentCard::class);

        PaymentCard::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'account_id' => $request->user()->account_id,
        ]);

        return back()->with('success', 'Cartão cadastrado com sucesso.');
    }

    public function update(PaymentCardRequest $request, PaymentCard $paymentCard): RedirectResponse
    {
        $this->authorize('update', $paymentCard);

        $paymentCard->update($request->validated());

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
}
