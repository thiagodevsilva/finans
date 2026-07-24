<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditCardPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;

        return [
            'bank_account_id' => [
                'required',
                'uuid',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bank_account_id' => 'conta bancária',
            'amount' => 'valor',
            'date' => 'data',
            'description' => 'descrição',
        ];
    }
}
