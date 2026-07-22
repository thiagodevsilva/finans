<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::creating(function (Model $model) {
            if (! $model->account_id && auth()->check()) {
                $model->account_id = auth()->user()->account_id;
            }
        });

        static::addGlobalScope('account', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.account_id',
                    auth()->user()->account_id
                );
            }
        });
    }

    public function scopeForAccount(Builder $query, string $accountId): Builder
    {
        return $query->withoutGlobalScope('account')->where('account_id', $accountId);
    }
}
