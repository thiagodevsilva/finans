<?php

namespace App\Http\Requests;

use App\Models\PaymentCard;
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
        $isIncome = $this->input('type') === Transaction::TYPE_INCOME;
        $isExpense = $this->input('type') === Transaction::TYPE_EXPENSE;
        $isCard = $isExpense && $this->input('payment_method') === Transaction::PAYMENT_CARD;
        $isInstallment = $isExpense && $this->boolean('is_installment');

        return [
            'type' => ['required', Rule::in([Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE])],
            'amount' => [
                Rule::requiredIf(! $isInstallment),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'date' => ['required', 'date'],
            'payment_method' => [
                Rule::requiredIf($isExpense),
                'nullable',
                Rule::in(Transaction::PAYMENT_METHODS),
            ],
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
            'is_installment' => ['sometimes', 'boolean'],
            'total_amount' => [
                Rule::requiredIf($isInstallment),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'installments_count' => [
                Rule::requiredIf($isInstallment),
                'nullable',
                'integer',
                'min:2',
                'max:48',
            ],
            'installment_amount' => ['nullable', 'numeric', 'min:0.01'],
            'recurring_transaction_id' => [
                'nullable',
                'uuid',
                Rule::exists('transactions', 'id')->where(
                    fn ($q) => $q
                        ->where('account_id', $accountId)
                        ->where('status', Transaction::STATUS_PLANNED)
                        ->whereNotNull('recurring_bill_id')
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== Transaction::TYPE_EXPENSE) {
                return;
            }

            if (
                $this->input('payment_method') !== Transaction::PAYMENT_CARD
                && $this->filled('payment_card_id')
            ) {
                $validator->errors()->add('payment_card_id', 'Cartão só pode ser informado quando a forma for cartão.');
            }

            if ($this->boolean('is_installment')) {
                $card = PaymentCard::query()->find($this->input('payment_card_id'));
                if (! $card || $card->type !== PaymentCard::TYPE_CREDIT) {
                    $validator->errors()->add('is_installment', 'Compra parcelada exige um cartão de crédito.');
                }

                if ($this->filled('recurring_transaction_id')) {
                    $validator->errors()->add('recurring_transaction_id', 'Não é possível parcelar o pagamento de uma conta fixa.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_installment' => $this->boolean('is_installment'),
        ]);

        if ($this->input('type') === Transaction::TYPE_INCOME) {
            $this->merge([
                'payment_method' => null,
                'payment_card_id' => null,
                'bank_account_id' => $this->filled('bank_account_id') ? $this->input('bank_account_id') : null,
                'is_installment' => false,
                'total_amount' => null,
                'installments_count' => null,
                'installment_amount' => null,
                'recurring_transaction_id' => null,
            ]);

            return;
        }

        $this->merge([
            'bank_account_id' => null,
            'payment_card_id' => $this->input('payment_method') === Transaction::PAYMENT_CARD
                ? $this->input('payment_card_id')
                : null,
            'recurring_transaction_id' => $this->filled('recurring_transaction_id')
                ? $this->input('recurring_transaction_id')
                : null,
        ]);

        if (! $this->boolean('is_installment')) {
            $this->merge([
                'total_amount' => null,
                'installments_count' => null,
                'installment_amount' => null,
            ]);
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
            'bank_account_id' => 'conta',
            'is_installment' => 'compra parcelada',
            'total_amount' => 'valor total',
            'installments_count' => 'quantidade de parcelas',
            'installment_amount' => 'valor da parcela',
            'recurring_transaction_id' => 'conta fixa',
        ];
    }
}
