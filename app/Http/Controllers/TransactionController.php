<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Category;
use App\Models\PaymentCard;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
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
                'paymentCard:id,name,brand,last_four,color',
            ])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
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
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        Transaction::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'account_id' => $request->user()->account_id,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transação criada com sucesso.');
    }

    public function edit(Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        return Inertia::render('Transactions/Form', [
            'transaction' => $transaction->load('paymentCard:id,name,brand,last_four,color'),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'color']),
            'paymentCards' => $this->paymentCardsForForm(),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $transaction->update($request->validated());

        return redirect()->route('transactions.index')->with('success', 'Transação atualizada com sucesso.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Transação excluída com sucesso.');
    }

    private function paymentCardsForForm()
    {
        return PaymentCard::query()
            ->with('user:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'brand', 'last_four', 'color', 'user_id']);
    }
}
