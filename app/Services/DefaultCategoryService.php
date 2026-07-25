<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;

class DefaultCategoryService
{
    /**
     * @return array<int, array{name: string, color: string, icon: string}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => 'Alimentação', 'color' => '#ef4444', 'icon' => 'utensils'],
            ['name' => 'Transporte', 'color' => '#3b82f6', 'icon' => 'car'],
            ['name' => 'Saúde', 'color' => '#10b981', 'icon' => 'heart'],
            ['name' => 'Lazer', 'color' => '#a855f7', 'icon' => 'party'],
            ['name' => 'Moradia', 'color' => '#f59e0b', 'icon' => 'home'],
            ['name' => 'Educação', 'color' => '#6366f1', 'icon' => 'book'],
            ['name' => 'Salário', 'color' => '#22c55e', 'icon' => 'briefcase'],
            ['name' => 'Fatura cartão', 'color' => '#475569', 'icon' => 'credit-card'],
            ['name' => 'Outros', 'color' => '#64748b', 'icon' => 'box'],
        ];
    }

    public const INVOICE_PAYMENT_NAME = 'Fatura cartão';

    public function ensureInvoicePaymentCategory(Account $account): Category
    {
        return Category::withoutGlobalScopes()->firstOrCreate(
            [
                'account_id' => $account->id,
                'name' => self::INVOICE_PAYMENT_NAME,
            ],
            [
                'color' => '#475569',
                'icon' => 'credit-card',
            ]
        );
    }

    public function seedFor(Account $account): void
    {
        foreach (self::defaults() as $category) {
            Category::withoutGlobalScopes()->create([
                'account_id' => $account->id,
                'name' => $category['name'],
                'color' => $category['color'],
                'icon' => $category['icon'],
            ]);
        }
    }
}
