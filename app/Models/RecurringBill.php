<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringBill extends Model
{
    use BelongsToAccount, HasFactory, HasUuids;

    public const FREQUENCY_MONTHLY = 'monthly';

    public const KIND_FIXED = 'fixed';

    public const KIND_VARIABLE = 'variable';

    public const KINDS = [
        self::KIND_FIXED,
        self::KIND_VARIABLE,
    ];

    protected $fillable = [
        'account_id',
        'user_id',
        'category_id',
        'description',
        'kind',
        'estimated_amount',
        'day_of_month',
        'frequency',
        'payment_method',
        'payment_card_id',
        'bank_account_id',
        'start_date',
        'end_date',
        'active',
    ];

    protected $casts = [
        'estimated_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
    ];

    public function isVariable(): bool
    {
        return $this->kind === self::KIND_VARIABLE;
    }

    public function isFixed(): bool
    {
        return $this->kind !== self::KIND_VARIABLE;
    }

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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recurring_bill_id');
    }
}
