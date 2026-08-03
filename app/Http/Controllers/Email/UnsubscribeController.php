<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

class UnsubscribeController extends Controller
{
    public function __invoke(User $user): View
    {
        if ($user->wantsMarketingEmails()) {
            $user->unsubscribeFromMarketing();
        }

        return view('emails.unsubscribed', [
            'email' => $user->email,
        ]);
    }
}
