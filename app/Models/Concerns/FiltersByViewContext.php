<?php

namespace App\Models\Concerns;

use App\Services\ViewContext;
use Illuminate\Database\Eloquent\Builder;

trait FiltersByViewContext
{
    public function scopeForViewContext(Builder $query, ?ViewContext $context = null): Builder
    {
        $context ??= app(ViewContext::class);

        return $context->applyToQuery(
            $query,
            $this->viewContextHasShared(),
            $this->viewContextHasCompany(),
        );
    }

    protected function viewContextHasShared(): bool
    {
        return true;
    }

    protected function viewContextHasCompany(): bool
    {
        return true;
    }
}
