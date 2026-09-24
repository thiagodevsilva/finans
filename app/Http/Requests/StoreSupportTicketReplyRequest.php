<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:6144'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'mensagem',
            'attachments' => 'anexos',
            'attachments.*' => 'anexo',
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.max' => 'Você pode enviar no máximo 5 imagens.',
            'attachments.*.max' => 'Cada imagem deve ter no máximo 6 MB.',
            'attachments.*.image' => 'O anexo deve ser uma imagem.',
            'attachments.*.mimes' => 'Use JPG, PNG, WEBP ou GIF.',
        ];
    }
}
