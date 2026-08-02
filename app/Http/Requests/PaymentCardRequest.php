<?php

namespace App\Http\Requests;

use App\Models\PaymentCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;
        $isCredit = $this->input('type') === PaymentCard::TYPE_CREDIT;

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
            'closing_day' => [
                Rule::requiredIf($isCredit),
                'nullable',
                'integer',
                'min:1',
                'max:31',
            ],
            'due_day' => [
                Rule::requiredIf($isCredit),
                'nullable',
                'integer',
                'min:1',
                'max:31',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== PaymentCard::TYPE_CREDIT) {
                return;
            }

            if ((int) $this->input('closing_day') === (int) $this->input('due_day')) {
                $validator->errors()->add('due_day', 'O vencimento deve ser diferente do fechamento.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('last_four') === '' || $this->input('last_four') === null) {
            $this->merge(['last_four' => null]);
        }

        if ($this->input('bank_account_id') === '' || $this->input('bank_account_id') === null) {
            $this->merge(['bank_account_id' => null]);
        }

        if ($this->input('type') !== PaymentCard::TYPE_CREDIT) {
            $this->merge([
                'closing_day' => null,
                'due_day' => null,
            ]);
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
            'closing_day' => 'dia de fechamento',
            'due_day' => 'dia de vencimento',
        ];
    }
}
