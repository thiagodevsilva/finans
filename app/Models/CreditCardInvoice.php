<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardInvoice extends Model
{
    use BelongsToAccount, HasFactory, HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'account_id',
        'payment_card_id',
        'closing_date',
        'due_date',
        'status',
        'paid_amount',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'due_date' => 'date',
        'paid_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paymentCard(): BelongsTo
    {
        return $this->belongsTo(PaymentCard::class, 'payment_card_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Transaction::class, 'credit_card_invoice_id')
            ->where('type', Transaction::TYPE_EXPENSE);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Transaction::class, 'credit_card_invoice_id')
            ->where('type', Transaction::TYPE_TRANSFER);
    }

    public function totalCharges(): float
    {
        return (float) $this->charges()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0, round($this->totalCharges() - (float) $this->paid_amount, 2));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_CLOSED => 'Fechada',
            self::STATUS_PARTIAL => 'Parcial',
            self::STATUS_PAID => 'Paga',
            default => 'Aberta',
        };
    }
}
