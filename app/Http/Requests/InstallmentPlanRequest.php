<?php

namespace App\Http\Requests;

use App\Models\PaymentCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->user()->account_id;

        return [
            'description' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'payment_card_id' => [
                'required',
                'uuid',
                Rule::exists('payment_cards', 'id')->where(
                    fn ($q) => $q->where('account_id', $accountId)->where('type', PaymentCard::TYPE_CREDIT)
                ),
            ],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'installments_count' => ['required', 'integer', 'min:2', 'max:48'],
            'purchase_date' => ['required', 'date'],
            'first_installment_date' => ['required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'descrição',
            'category_id' => 'categoria',
            'payment_card_id' => 'cartão',
            'total_amount' => 'valor total',
            'installments_count' => 'número de parcelas',
            'purchase_date' => 'data da compra',
            'first_installment_date' => 'data da 1ª parcela',
        ];
    }
}
