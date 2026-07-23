<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAccountRequest;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        $user = auth()->user();

        $bankAccounts = BankAccount::query()
            ->with('user:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (BankAccount $bankAccount) => [
                'id' => $bankAccount->id,
                'name' => $bankAccount->name,
                'color' => $bankAccount->color,
                'user_id' => $bankAccount->user_id,
                'user' => $bankAccount->user,
                'can_edit' => $user->isOwner() || $bankAccount->user_id === $user->id,
            ]);

        return Inertia::render('BankAccounts/Index', [
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(BankAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', BankAccount::class);

        BankAccount::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'account_id' => $request->user()->account_id,
        ]);

        return back()->with('success', 'Conta cadastrada com sucesso.');
    }

    public function update(BankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        $bankAccount->update($request->validated());

        return back()->with('success', 'Conta atualizada com sucesso.');
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('delete', $bankAccount);

        if ($bankAccount->transactions()->exists()) {
            return back()->with('error', 'Não é possível excluir uma conta com lançamentos.');
        }

        if ($bankAccount->paymentCards()->exists()) {
            return back()->with('error', 'Desvincule os cartões desta conta antes de excluir.');
        }

        $bankAccount->delete();

        return back()->with('success', 'Conta excluída com sucesso.');
    }
}
