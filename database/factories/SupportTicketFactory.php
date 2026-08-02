<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportSlaService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        $createdAt = now();

        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => SupportTicket::STATUS_OPEN,
            'closed_reason' => null,
            'closed_by' => null,
            'closed_at' => null,
            'first_responded_at' => null,
            'sla_due_at' => app(SupportSlaService::class)->dueAt($createdAt),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'account_id' => $user->account_id,
            'user_id' => $user->id,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_reason' => 'Resolvido',
            'closed_at' => now(),
        ]);
    }
}
