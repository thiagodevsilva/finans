<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
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
            'type' => ['required', Rule::in([Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(Transaction::PAYMENT_METHODS)],
            'payment_card_id' => [
                Rule::requiredIf($isCard),
                'nullable',
                'uuid',
                Rule::exists('payment_cards', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('payment_method') !== Transaction::PAYMENT_CARD && $this->filled('payment_card_id')) {
                $validator->errors()->add('payment_card_id', 'Cartão só pode ser informado quando a forma for cartão.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('payment_method') !== Transaction::PAYMENT_CARD) {
            $this->merge(['payment_card_id' => null]);
        }
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'amount' => 'valor',
            'description' => 'descrição',
            'category_id' => 'categoria',
            'date' => 'data',
            'payment_method' => 'forma de pagamento',
            'payment_card_id' => 'cartão',
        ];
    }
}
