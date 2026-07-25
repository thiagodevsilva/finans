<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecurringBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;
        $isCard = $this->input('payment_method') === Transaction::PAYMENT_CARD;

        return [
            'description' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'estimated_amount' => ['required', 'numeric', 'min:0.01'],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'payment_method' => ['nullable', Rule::in(Transaction::PAYMENT_METHODS)],
            'payment_card_id' => [
                Rule::requiredIf($isCard),
                'nullable',
                'uuid',
                Rule::exists('payment_cards', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'bank_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'active' => ['sometimes', 'boolean'],
            'propagate' => ['sometimes', Rule::in(['none', 'open', 'from_date'])],
            'propagate_from' => [
                Rule::requiredIf($this->input('propagate') === 'from_date'),
                'nullable',
                'date',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (
                $this->input('payment_method') !== Transaction::PAYMENT_CARD
                && $this->filled('payment_card_id')
            ) {
                $validator->errors()->add('payment_card_id', 'Cartão só pode ser informado quando a forma for cartão.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_card_id' => $this->input('payment_method') === Transaction::PAYMENT_CARD
                ? ($this->filled('payment_card_id') ? $this->input('payment_card_id') : null)
                : null,
            'bank_account_id' => $this->filled('bank_account_id') ? $this->input('bank_account_id') : null,
            'end_date' => $this->filled('end_date') ? $this->input('end_date') : null,
            'propagate' => $this->input('propagate', 'none'),
            'propagate_from' => $this->filled('propagate_from') ? $this->input('propagate_from') : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'description' => 'descrição',
            'category_id' => 'categoria',
            'estimated_amount' => 'valor estimado',
            'day_of_month' => 'dia do vencimento',
            'payment_method' => 'forma de pagamento',
            'payment_card_id' => 'cartão',
            'bank_account_id' => 'conta',
            'start_date' => 'início',
            'end_date' => 'fim',
            'propagate' => 'atualizar lançamentos',
            'propagate_from' => 'a partir da data',
        ];
    }
}
