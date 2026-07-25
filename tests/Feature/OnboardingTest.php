<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_has_null_onboarding_status_shared_via_inertia(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->onboarding_status);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.onboarding_status', null)
            );
    }

    public function test_user_can_skip_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'status' => OnboardingController::STATUS_SKIPPED,
            ])
            ->assertRedirect();

        $this->assertSame(OnboardingController::STATUS_SKIPPED, $user->fresh()->onboarding_status);
    }

    public function test_user_can_complete_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'status' => OnboardingController::STATUS_COMPLETED,
            ])
            ->assertRedirect();

        $this->assertSame(OnboardingController::STATUS_COMPLETED, $user->fresh()->onboarding_status);
    }

    public function test_user_can_restart_onboarding(): void
    {
        $user = User::factory()->create([
            'onboarding_status' => OnboardingController::STATUS_SKIPPED,
        ]);

        $this->actingAs($user)
            ->delete(route('onboarding.destroy'))
            ->assertRedirect(route('dashboard', ['tour' => 'first-setup']));

        $this->assertNull($user->fresh()->onboarding_status);
    }

    public function test_completed_user_can_restart_onboarding(): void
    {
        $user = User::factory()->create([
            'onboarding_status' => OnboardingController::STATUS_COMPLETED,
        ]);

        $this->actingAs($user)
            ->delete(route('onboarding.destroy'))
            ->assertRedirect(route('dashboard', ['tour' => 'first-setup']));

        $this->assertNull($user->fresh()->onboarding_status);
    }

    public function test_invalid_onboarding_status_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'status' => 'invalid',
            ])
            ->assertSessionHasErrors('status');
    }
}
