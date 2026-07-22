<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $members = User::query()
            ->where('account_id', auth()->user()->account_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        return Inertia::render('Members/Index', [
            'members' => $members,
            'canManage' => auth()->user()->isOwner(),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        User::create([
            'account_id' => $request->user()->account_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => User::ROLE_DEPENDENT,
        ]);

        return back()->with('success', 'Dependente adicionado com sucesso.');
    }

    public function destroy(User $member): RedirectResponse
    {
        $this->authorize('delete', $member);

        $member->delete();

        return back()->with('success', 'Dependente removido com sucesso.');
    }
}
