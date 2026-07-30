<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceAnchor extends Model
{
    use BelongsToAccount, HasFactory, HasUuids;

    public const SOURCE_INITIAL = 'initial';

    public const SOURCE_MONTHLY_KEEP = 'monthly_keep';

    public const SOURCE_MONTHLY_UPDATE = 'monthly_update';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCES = [
        self::SOURCE_INITIAL,
        self::SOURCE_MONTHLY_KEEP,
        self::SOURCE_MONTHLY_UPDATE,
        self::SOURCE_MANUAL,
    ];

    protected $fillable = [
        'account_id',
        'user_id',
        'amount',
        'as_of_date',
        'source',
        'checkin_month',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'as_of_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
