<?php

namespace App\Http\Requests;

use App\Models\Transaction;
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
        $needsBank = in_array($this->input('payment_method'), Transaction::BANK_LINKED_PAYMENT_METHODS, true);

        return [
            'payment_method' => [
                'required',
                Rule::in(Transaction::PAYMENT_METHODS),
            ],
            'bank_account_id' => [
                Rule::requiredIf($needsBank),
                'nullable',
                'uuid',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $needsBank = in_array($this->input('payment_method'), Transaction::BANK_LINKED_PAYMENT_METHODS, true);

        $this->merge([
            'bank_account_id' => $needsBank && $this->filled('bank_account_id')
                ? $this->input('bank_account_id')
                : null,
            'description' => $this->filled('description') ? $this->input('description') : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'payment_method' => 'forma de pagamento',
            'bank_account_id' => 'conta bancária',
            'amount' => 'valor',
            'date' => 'data',
            'description' => 'descrição',
        ];
    }
}
