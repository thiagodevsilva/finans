<?php

namespace App\Http\Requests;

use App\Models\PaymentCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', Rule::in(PaymentCard::BRANDS)],
            'type' => ['required', Rule::in(PaymentCard::TYPES)],
            'last_four' => ['nullable', 'digits:4'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bank_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('last_four') === '' || $this->input('last_four') === null) {
            $this->merge(['last_four' => null]);
        }

        if ($this->input('bank_account_id') === '' || $this->input('bank_account_id') === null) {
            $this->merge(['bank_account_id' => null]);
        }
    }

    public function attributes(): array
    {
        return [
            'name' => 'apelido',
            'brand' => 'bandeira',
            'type' => 'tipo',
            'last_four' => 'final do cartão',
            'color' => 'cor',
            'bank_account_id' => 'conta',
        ];
    }
}
