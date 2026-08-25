<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByViewContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToAccount, FiltersByViewContext, HasFactory, HasUuids;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_INVESTMENT = 'investment';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_PIX = 'pix';

    public const PAYMENT_TRANSFER = 'transfer';

    public const PAYMENT_CARD = 'card';

    public const PAYMENT_DEBIT = 'debit';

    public const PAYMENT_AUTO_DEBIT = 'auto_debit';

    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_PIX,
        self::PAYMENT_TRANSFER,
        self::PAYMENT_CARD,
        self::PAYMENT_DEBIT,
        self::PAYMENT_AUTO_DEBIT,
    ];

    /** Formas de pagamento permitidas para aporte (sem cartão). */
    public const INVESTMENT_PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_PIX,
        self::PAYMENT_TRANSFER,
        self::PAYMENT_DEBIT,
        self::PAYMENT_AUTO_DEBIT,
    ];

    /** Métodos que tipicamente podem vincular conta bancária. */
    public const BANK_LINKED_PAYMENT_METHODS = [
        self::PAYMENT_PIX,
        self::PAYMENT_TRANSFER,
        self::PAYMENT_DEBIT,
        self::PAYMENT_AUTO_DEBIT,
    ];

    /** Grupos de gasto do dashboard (exclui contas fixas e benefício). */
    public const SPEND_GROUP_CREDIT = 'credit';

    public const SPEND_GROUP_DEBIT = 'debit';

    public const SPEND_GROUPS = [
        self::SPEND_GROUP_CREDIT,
        self::SPEND_GROUP_DEBIT,
    ];

    protected $fillable = [
        'account_id',
        'user_id',
        'is_shared',
        'created_by',
        'company_id',
        'category_id',
        'type',
        'amount',
        'description',
        'date',
        'payment_method',
        'payment_card_id',
        'bank_account_id',
        'credit_card_invoice_id',
        'installment_plan_id',
        'installment_number',
        'recurring_bill_id',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_shared' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function paymentCard(): BelongsTo
    {
        return $this->belongsTo(PaymentCard::class, 'payment_card_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function creditCardInvoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class, 'credit_card_invoice_id');
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function recurringBill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class, 'recurring_bill_id');
    }

    /**
     * Despesas do dia a dia (sem contas fixas).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeVariableExpenses($query)
    {
        return $query
            ->where('type', self::TYPE_EXPENSE)
            ->whereNull('recurring_bill_id');
    }

    /**
     * Mesma regra do dashboard: crédito (cartão crédito) ou débito
     * (PIX/dinheiro/débito/etc., excluindo crédito e benefício).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSpendGroup($query, string $group)
    {
        $query->variableExpenses();

        if ($group === self::SPEND_GROUP_CREDIT) {
            return $query
                ->where('payment_method', self::PAYMENT_CARD)
                ->whereHas(
                    'paymentCard',
                    fn ($card) => $card->where('type', PaymentCard::TYPE_CREDIT)
                );
        }

        if ($group === self::SPEND_GROUP_DEBIT) {
            return $query->whereNot(function ($exclude) {
                $exclude->where('payment_method', self::PAYMENT_CARD)
                    ->whereHas(
                        'paymentCard',
                        fn ($card) => $card->whereIn('type', [
                            PaymentCard::TYPE_CREDIT,
                            PaymentCard::TYPE_BENEFIT,
                        ])
                    );
            });
        }

        return $query;
    }

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    public function isTransfer(): bool
    {
        return $this->type === self::TYPE_TRANSFER;
    }

    public function isInvestment(): bool
    {
        return $this->type === self::TYPE_INVESTMENT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public static function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            self::PAYMENT_CASH => 'Dinheiro',
            self::PAYMENT_PIX => 'PIX',
            self::PAYMENT_TRANSFER => 'Transferência',
            self::PAYMENT_CARD => 'Cartão',
            self::PAYMENT_DEBIT => 'Débito',
            self::PAYMENT_AUTO_DEBIT => 'Débito automático',
            default => $method,
        };
    }
}
