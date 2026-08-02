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
        $type = fake()->randomElement(PaymentCard::TYPES);
        $isCredit = $type === PaymentCard::TYPE_CREDIT;

        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'brand' => fake()->randomElement(PaymentCard::BRANDS),
            'type' => $type,
            'last_four' => (string) fake()->numerify('####'),
            'color' => fake()->hexColor(),
            'closing_day' => $isCredit ? 10 : null,
            'due_day' => $isCredit ? 17 : null,
        ];
    }

    public function debit(): static
    {
        return $this->state(fn () => [
            'type' => PaymentCard::TYPE_DEBIT,
            'closing_day' => null,
            'due_day' => null,
        ]);
    }

    public function credit(): static
    {
        return $this->state(fn () => [
            'type' => PaymentCard::TYPE_CREDIT,
            'closing_day' => 10,
            'due_day' => 17,
        ]);
    }

    public function benefit(): static
    {
        return $this->state(fn () => [
            'type' => PaymentCard::TYPE_BENEFIT,
            'closing_day' => null,
            'due_day' => null,
        ]);
    }
}
