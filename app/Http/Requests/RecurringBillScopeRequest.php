<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringBillScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['current', 'future'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'scope' => 'escopo',
        ];
    }

    public function messages(): array
    {
        return [
            'scope.required' => 'Escolha se vale para o mês atual ou só para os próximos.',
            'scope.in' => 'Escolha se vale para o mês atual ou só para os próximos.',
        ];
    }
}
