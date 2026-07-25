<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_COMPLETED = 'completed';

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([self::STATUS_SKIPPED, self::STATUS_COMPLETED])],
        ]);

        $request->user()->update([
            'onboarding_status' => $validated['status'],
        ]);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->update([
            'onboarding_status' => null,
        ]);

        return redirect()->route('dashboard', ['tour' => 'first-setup']);
    }
}
