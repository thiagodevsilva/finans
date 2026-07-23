<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => fake()->randomFloat(2, 10, 500),
            'description' => fake()->sentence(3),
            'date' => now()->toDateString(),
            'payment_method' => Transaction::PAYMENT_CASH,
            'payment_card_id' => null,
        ];
    }
}
