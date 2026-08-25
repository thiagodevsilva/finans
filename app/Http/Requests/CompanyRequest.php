<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;
        $company = $this->route('company');
        $ignoreId = $company instanceof Company ? $company->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => [
                'required',
                'string',
                'max:18',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $digits = Company::normalizeCnpj(is_string($value) ? $value : null);
                    if (! $digits || strlen($digits) !== 14) {
                        $fail('Informe um CNPJ válido com 14 dígitos.');
                    }
                },
                Rule::unique('companies', 'cnpj')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->ignore($ignoreId),
            ],
            'user_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome / razão social.',
            'cnpj.required' => 'Informe o CNPJ.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado na conta.',
        ];
    }
}
