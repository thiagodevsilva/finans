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
        return [
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', Rule::in(PaymentCard::BRANDS)],
            'last_four' => ['required', 'digits:4'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'apelido',
            'brand' => 'bandeira',
            'last_four' => 'final do cartão',
            'color' => 'cor',
        ];
    }
}
