<?php

namespace App\Http\Controllers;

use App\Services\ViewContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ViewContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'scope' => ['required', Rule::in([
                ViewContext::SCOPE_FAMILY,
                ViewContext::SCOPE_MINE,
                ViewContext::SCOPE_MEMBER,
            ])],
            'member_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('account_id', $user->account_id)),
            ],
            'company' => ['nullable', 'string', 'max:36'],
        ]);

        $scope = $data['scope'];
        $memberId = $data['member_id'] ?? null;
        $company = $data['company'] ?? ViewContext::COMPANY_ALL;

        if ($scope === ViewContext::SCOPE_MEMBER && ! $user->isOwner()) {
            $scope = ViewContext::SCOPE_MINE;
            $memberId = null;
        }

        if ($company !== ViewContext::COMPANY_ALL && $company !== ViewContext::COMPANY_NONE) {
            $companyOwnerId = $scope === ViewContext::SCOPE_MEMBER
                ? $memberId
                : ($scope === ViewContext::SCOPE_MINE ? $user->id : null);

            if (! $companyOwnerId || ! \App\Models\Company::query()
                ->whereKey($company)
                ->where('user_id', $companyOwnerId)
                ->exists()) {
                $company = ViewContext::COMPANY_ALL;
            }
        }

        ViewContext::store($request, $scope, $memberId, $company);

        return back();
    }
}
