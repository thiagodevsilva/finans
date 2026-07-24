<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    use BelongsToAccount, HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'account_id',
        'user_id',
        'category_id',
        'payment_card_id',
        'description',
        'total_amount',
        'installments_count',
        'purchase_date',
        'first_installment_date',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'purchase_date' => 'date',
        'first_installment_date' => 'date',
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

    public function installments(): HasMany
    {
        return $this->hasMany(Transaction::class, 'installment_plan_id')
            ->orderBy('installment_number');
    }
}
