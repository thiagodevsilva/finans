<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportSlaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_dependent_can_create_ticket(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);

        foreach ([$owner, $dependent] as $user) {
            $this->actingAs($user)
                ->post(route('support-tickets.store'), [
                    'title' => 'Ajuda com cartão',
                    'description' => 'Não consigo pagar a fatura.',
                ])
                ->assertRedirect();
        }

        $this->assertSame(2, SupportTicket::withoutGlobalScopes()->where('account_id', $account->id)->count());
    }

    public function test_account_isolation_on_index_and_show(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $userA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $userB = User::factory()->owner()->create(['account_id' => $accountB->id]);

        $ticketA = SupportTicket::factory()->forUser($userA)->create(['title' => 'Ticket A']);
        $ticketB = SupportTicket::factory()->forUser($userB)->create(['title' => 'Ticket B']);

        $this->actingAs($userA)
            ->get(route('support-tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SupportTickets/Index')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.title', 'Ticket A'));

        $this->actingAs($userA)
            ->get(route('support-tickets.show', $ticketB))
            ->assertNotFound();

        $this->actingAs($userA)
            ->get(route('support-tickets.show', $ticketA))
            ->assertOk();
    }

    public function test_close_requires_reason_and_blocks_replies(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $ticket = SupportTicket::factory()->forUser($owner)->create();

        $this->actingAs($owner)
            ->post(route('support-tickets.close', $ticket), [])
            ->assertSessionHasErrors('closed_reason');

        $this->actingAs($owner)
            ->post(route('support-tickets.close', $ticket), [
                'closed_reason' => 'Já resolvi sozinho',
            ])
            ->assertRedirect(route('support-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(SupportTicket::STATUS_CLOSED, $ticket->status);
        $this->assertSame('Já resolvi sozinho', $ticket->closed_reason);

        $this->actingAs($owner)
            ->post(route('support-tickets.replies.store', $ticket), [
                'body' => 'Mais uma dúvida',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_close_dependent_ticket(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);
        $ticket = SupportTicket::factory()->forUser($dependent)->create();

        $this->actingAs($owner)
            ->post(route('support-tickets.close', $ticket), [
                'closed_reason' => 'Duplicado',
            ])
            ->assertRedirect();

        $this->assertSame(SupportTicket::STATUS_CLOSED, $ticket->fresh()->status);
    }

    public function test_dependent_cannot_close_others_ticket(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $dependent = User::factory()->dependent()->create(['account_id' => $account->id]);
        $ticket = SupportTicket::factory()->forUser($owner)->create();

        $this->actingAs($dependent)
            ->post(route('support-tickets.close', $ticket), [
                'closed_reason' => 'Não deveria',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_list_and_reply_setting_first_responded_at(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);
        $admin = User::factory()->admin()->create();
        $ticket = SupportTicket::factory()->forUser($owner)->create();

        $this->actingAs($owner)
            ->get(route('admin.support-tickets.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.support-tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/SupportTickets/Index')
                ->has('tickets.data', 1));

        $this->actingAs($admin)
            ->post(route('admin.support-tickets.replies.store', $ticket), [
                'body' => 'Olá! Vamos te ajudar.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertNotNull($ticket->first_responded_at);
        $this->assertSame(SupportTicket::STATUS_ANSWERED, $ticket->status);
        $this->assertTrue($ticket->replies()->first()->is_staff);
    }

    public function test_admin_cannot_use_family_create_flow(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('support-tickets.create'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_attachments_validation_and_download_isolation(): void
    {
        Storage::fake('local');

        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $userA = User::factory()->owner()->create(['account_id' => $accountA->id]);
        $userB = User::factory()->owner()->create(['account_id' => $accountB->id]);

        $ok = UploadedFile::fake()->image('print.png', 100, 100)->size(100);
        $tooBig = UploadedFile::fake()->image('huge.jpg')->size(6145);

        $this->actingAs($userA)
            ->post(route('support-tickets.store'), [
                'title' => 'Com anexo inválido',
                'description' => 'Teste',
                'attachments' => [$tooBig],
            ])
            ->assertSessionHasErrors('attachments.0');

        $sixFiles = collect(range(1, 6))
            ->map(fn ($i) => UploadedFile::fake()->image("p{$i}.png")->size(100))
            ->all();

        $this->actingAs($userA)
            ->post(route('support-tickets.store'), [
                'title' => 'Muitos anexos',
                'description' => 'Teste',
                'attachments' => $sixFiles,
            ])
            ->assertSessionHasErrors('attachments');

        $this->actingAs($userA)
            ->post(route('support-tickets.store'), [
                'title' => 'Com print',
                'description' => 'Veja o print',
                'attachments' => [$ok],
            ])
            ->assertRedirect();

        $ticket = SupportTicket::withoutGlobalScopes()->where('title', 'Com print')->first();
        $this->assertNotNull($ticket);
        $attachment = $ticket->attachments()->first();
        $this->assertNotNull($attachment);

        $this->actingAs($userA)
            ->get(route('support-tickets.attachments.show', $attachment))
            ->assertOk();

        $this->actingAs($userB)
            ->get(route('support-tickets.attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_filter_by_status(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        SupportTicket::factory()->forUser($owner)->create(['title' => 'Aberto', 'status' => SupportTicket::STATUS_OPEN]);
        SupportTicket::factory()->forUser($owner)->closed()->create([
            'title' => 'Fechado',
            'closed_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('support-tickets.index', ['status' => SupportTicket::STATUS_CLOSED]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tickets.data', 1)
                ->where('tickets.data.0.title', 'Fechado'));
    }

    public function test_sla_service_skips_weekends(): void
    {
        $service = app(SupportSlaService::class);

        // Sexta 10h + 72h úteis deve cair na quarta seguinte 10h (pula sáb/dom)
        $friday = Carbon::parse('2026-07-31 10:00:00'); // sexta
        $due = $service->dueAt($friday, 72);

        $this->assertFalse($due->isWeekend());
        $this->assertSame('2026-08-05 10:00:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_sla_breach_detection(): void
    {
        $service = app(SupportSlaService::class);
        $account = Account::factory()->create();
        $owner = User::factory()->owner()->create(['account_id' => $account->id]);

        $ticket = SupportTicket::factory()->forUser($owner)->create([
            'sla_due_at' => now()->subHour(),
            'first_responded_at' => null,
        ]);

        $this->assertTrue($service->isBreached($ticket));
        $this->assertSame('breached', $service->statusLabel($ticket));

        $ticket->update(['first_responded_at' => now()->subMinutes(30)]);
        $this->assertSame('missed', $service->statusLabel($ticket->fresh()));
    }
}
