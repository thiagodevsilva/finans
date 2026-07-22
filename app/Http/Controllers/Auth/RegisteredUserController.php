<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\DefaultCategoryService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, DefaultCategoryService $categories): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], [
            'name' => 'nome',
            'account_name' => 'nome da conta',
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $user = DB::transaction(function () use ($request, $categories) {
            $account = Account::create([
                'name' => $request->account_name,
            ]);

            $categories->seedFor($account);

            return User::create([
                'account_id' => $account->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'role' => User::ROLE_OWNER,
            ]);
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
