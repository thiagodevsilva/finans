<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(SupportTicket::STATUSES)],
            'closed_reason' => [
                Rule::requiredIf(fn () => $this->input('status') === SupportTicket::STATUS_CLOSED),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'status',
            'closed_reason' => 'justificativa',
        ];
    }
}
