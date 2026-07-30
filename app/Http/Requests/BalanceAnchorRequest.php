<?php

namespace App\Http\Requests;

use App\Models\BalanceAnchor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BalanceAnchorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
            'as_of_date' => ['required', 'date'],
            'source' => [
                'required',
                Rule::in([
                    BalanceAnchor::SOURCE_INITIAL,
                    BalanceAnchor::SOURCE_MONTHLY_UPDATE,
                    BalanceAnchor::SOURCE_MANUAL,
                ]),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'saldo',
            'as_of_date' => 'data',
            'source' => 'origem',
        ];
    }
}
