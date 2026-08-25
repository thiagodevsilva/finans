<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $user = $request->user();

        $companies = Company::query()
            ->with('user:id,name')
            ->when(
                ! $user->isOwner(),
                fn ($q) => $q->where('user_id', $user->id),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'cnpj' => $company->cnpj,
                'cnpj_formatted' => $company->formattedCnpj(),
                'user_id' => $company->user_id,
                'user' => $company->user,
                'can_edit' => $user->isOwner() || $company->user_id === $user->id,
            ]);

        $members = $user->isOwner()
            ? User::query()
                ->where('account_id', $user->account_id)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect([$user->only(['id', 'name'])]);

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'members' => $members,
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $data = $request->validated();
        $ownerId = $data['user_id'] ?? $request->user()->id;

        if (! $request->user()->isOwner()) {
            $ownerId = $request->user()->id;
        }

        Company::create([
            'account_id' => $request->user()->account_id,
            'user_id' => $ownerId,
            'cnpj' => Company::normalizeCnpj($data['cnpj']),
            'name' => $data['name'],
        ]);

        return back()->with('success', 'CNPJ cadastrado.');
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $data = $request->validated();
        $company->update([
            'name' => $data['name'],
            'cnpj' => Company::normalizeCnpj($data['cnpj']),
        ]);

        return back()->with('success', 'CNPJ atualizado.');
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        return back()->with('success', 'CNPJ removido.');
    }
}
