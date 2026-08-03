<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'balance_stale_at',
        'balance_stale_adjustment',
        'balance_stale_dismissed_at',
    ];

    protected $casts = [
        'balance_stale_at' => 'datetime',
        'balance_stale_adjustment' => 'decimal:2',
        'balance_stale_dismissed_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
