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

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
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
