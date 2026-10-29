<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByViewContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringBill extends Model
{
    use BelongsToAccount, FiltersByViewContext, HasFactory, HasUuids;

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
        'is_shared',
        'created_by',
        'company_id',
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
        'suspended_from',
    ];

    protected $casts = [
        'estimated_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'suspended_from' => 'date',
        'active' => 'boolean',
        'is_shared' => 'boolean',
    ];

    public function isVariable(): bool
    {
        return $this->kind === self::KIND_VARIABLE;
    }

    public function isFixed(): bool
    {
        return $this->kind !== self::KIND_VARIABLE;
    }

    /**
     * O mês ainda entra na meta (ativa, ou inativa só a partir do mês seguinte).
     */
    public function countsInMonth(?Carbon $month = null): bool
    {
        $month ??= now();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        if ($this->start_date && $this->start_date->copy()->startOfDay()->gt($end)) {
            return false;
        }

        if ($this->end_date && $this->end_date->copy()->endOfDay()->lt($start)) {
            return false;
        }

        if ($this->active) {
            return true;
        }

        return $this->suspended_from !== null
            && $this->suspended_from->copy()->startOfDay()->gt($start);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCountsInMonth(Builder $query, Carbon $month): Builder
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return $query
            ->whereDate('start_date', '<=', $end)
            ->where(function (Builder $q) use ($start) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start);
            })
            ->where(function (Builder $q) use ($start) {
                $q->where('active', true)
                    ->orWhere(function (Builder $inner) use ($start) {
                        $inner->whereNotNull('suspended_from')
                            ->whereDate('suspended_from', '>', $start);
                    });
            });
    }

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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recurring_bill_id');
    }
}
