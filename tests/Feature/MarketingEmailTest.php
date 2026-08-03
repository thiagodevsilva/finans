<?php

namespace Tests\Feature;

use App\Mail\WelcomeMarketingMail;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MarketingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_marketing_mail_is_sent_on_registration(): void
    {
        Mail::fake();
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'account_name' => 'Família Teste',
            'email' => 'welcome@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'welcome@example.com')->firstOrFail();

        Mail::assertSent(WelcomeMarketingMail::class, function (WelcomeMarketingMail $mail) use ($user) {
            return $mail->user->is($user);
        });
    }

    public function test_marketing_mailable_is_not_sent_when_opted_out(): void
    {
        Mail::fake();

        $user = User::factory()->marketingOptedOut()->create();

        WelcomeMarketingMail::sendTo($user);

        Mail::assertNotSent(WelcomeMarketingMail::class);
    }

    public function test_signed_unsubscribe_opts_out_of_marketing(): void
    {
        $user = User::factory()->create([
            'marketing_emails_opted_in' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'email.unsubscribe',
            now()->addDay(),
            ['user' => $user->id]
        );

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Descadastro concluído');
        $response->assertSee($user->email, false);

        $user->refresh();
        $this->assertFalse($user->marketing_emails_opted_in);
        $this->assertNotNull($user->marketing_unsubscribed_at);
    }

    public function test_unsigned_unsubscribe_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->get(route('email.unsubscribe', $user))->assertForbidden();

        $this->assertTrue($user->fresh()->marketing_emails_opted_in);
    }

    public function test_password_reset_still_sends_when_marketing_opted_out(): void
    {
        Notification::fake();

        $user = User::factory()->marketingOptedOut()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_profile_can_update_marketing_preference(): void
    {
        $user = User::factory()->create([
            'marketing_emails_opted_in' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'marketing_emails_opted_in' => false,
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertFalse($user->marketing_emails_opted_in);
        $this->assertNotNull($user->marketing_unsubscribed_at);
    }
}
