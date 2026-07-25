<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToAccount, HasFactory, HasUuids;

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

    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_PIX,
        self::PAYMENT_TRANSFER,
        self::PAYMENT_CARD,
    ];

    /** Formas de pagamento permitidas para aporte (sem cartão). */
    public const INVESTMENT_PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_PIX,
        self::PAYMENT_TRANSFER,
    ];

    protected $fillable = [
        'account_id',
        'user_id',
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
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            default => $method,
        };
    }
}
