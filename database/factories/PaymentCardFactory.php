<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PaymentCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentCard>
 */
class PaymentCardFactory extends Factory
{
    protected $model = PaymentCard::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'brand' => fake()->randomElement(PaymentCard::BRANDS),
            'last_four' => (string) fake()->numerify('####'),
            'color' => fake()->hexColor(),
        ];
    }
}
