<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use BelongsToAccount, HasFactory, HasUuids;

    protected $fillable = [
        'account_id',
        'user_id',
        'cnpj',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public static function normalizeCnpj(?string $cnpj): ?string
    {
        if ($cnpj === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';

        return $digits !== '' ? $digits : null;
    }

    public function formattedCnpj(): string
    {
        $c = $this->cnpj;
        if (strlen($c) !== 14) {
            return $c;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($c, 0, 2),
            substr($c, 2, 3),
            substr($c, 5, 3),
            substr($c, 8, 4),
            substr($c, 12, 2),
        );
    }
}
