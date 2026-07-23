<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentCard extends Model
{
    use BelongsToAccount, HasFactory, HasUuids;

    public const BRAND_VISA = 'visa';

    public const BRAND_MASTERCARD = 'mastercard';

    public const BRAND_ELO = 'elo';

    public const BRAND_AMEX = 'amex';

    public const BRAND_OTHER = 'other';

    public const BRANDS = [
        self::BRAND_VISA,
        self::BRAND_MASTERCARD,
        self::BRAND_ELO,
        self::BRAND_AMEX,
        self::BRAND_OTHER,
    ];

    protected $fillable = [
        'account_id',
        'user_id',
        'name',
        'brand',
        'last_four',
        'color',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_card_id');
    }

    public static function brandLabel(string $brand): string
    {
        return match ($brand) {
            self::BRAND_VISA => 'Visa',
            self::BRAND_MASTERCARD => 'Mastercard',
            self::BRAND_ELO => 'Elo',
            self::BRAND_AMEX => 'Amex',
            default => 'Outro',
        };
    }
}
