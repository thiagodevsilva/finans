<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => fake()->unique()->word(),
            'color' => '#ffc107',
            'icon' => 'box',
        ];
    }
}
