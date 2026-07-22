<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;

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
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'amount' => 'valor',
            'description' => 'descrição',
            'category_id' => 'categoria',
            'date' => 'data',
        ];
    }
}
