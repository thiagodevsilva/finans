<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'valor',
            'date' => 'data',
        ];
    }
}
