<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'user_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'company_id' => [
                'nullable',
                'uuid',
                Rule::exists('companies', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'color' => 'cor',
            'user_id' => 'membro',
            'company_id' => 'CNPJ',
        ];
    }
}
