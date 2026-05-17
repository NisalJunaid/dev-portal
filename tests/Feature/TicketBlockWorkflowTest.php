<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketBlock;
use App\Models\TimeLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketBlockWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_blocking_running_timer_requires_reason_and_pauses_timer(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $submitter] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $submitter, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING);

        $this->actingAs($developer)
            ->postJson(route('tickets.block', $ticket), ['reason' => ''])
            ->assertStatus(422);

        Carbon::setTestNow('2026-05-17 12:00:00');
        $this->actingAs($developer)->postJson(route('tickets.timer.start', $ticket))->assertOk();

        Carbon::setTestNow('2026-05-17 12:02:30');
        $this->actingAs($developer)
            ->postJson(route('tickets.block', $ticket), ['reason' => 'Waiting on client credentials.'])
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_BUG_BLOCKED)
            ->assertJsonPath('active_block.reason', 'Waiting on client credentials.');

        $this->assertDatabaseHas('ticket_blocks', [
            'ticket_id' => $ticket->id,
            'blocked_by' => $developer->id,
            'reason' => 'Waiting on client credentials.',
        ]);
        $this->assertSame(Ticket::STATUS_BUG_BLOCKED, $ticket->refresh()->status);
        $this->assertSame(TimeLog::STATUS_PAUSED, TimeLog::firstOrFail()->status);
        $this->assertSame(150, TimeLog::firstOrFail()->duration_seconds);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'blocked']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'timer paused']);
    }

    public function test_client_sees_blocked_status_latest_reason_and_total_duration_without_timer_data(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED);

        Carbon::setTestNow('2026-05-17 13:00:00');
        $this->actingAs($developer)->postJson(route('tickets.block', $ticket), ['reason' => 'Vendor API outage.'])->assertOk();

        Carbon::setTestNow('2026-05-17 13:10:00');
        $this->actingAs($clientUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Feature Blocked')
            ->assertSee('Vendor API outage.')
            ->assertSee('Blocked duration')
            ->assertDontSee('Timer')
            ->assertDontSee('Block history');
    }

    public function test_unblock_flow_sets_duration_and_returns_to_expected_status_without_resuming_timer(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $submitter] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $submitter, Ticket::TYPE_FEATURE, Ticket::STATUS_IN_PROGRESS);
        $sprint = Sprint::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'sprint_no' => 1,
            'name' => 'Current sprint',
            'status' => Sprint::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'started_by' => $developer->id,
        ]);
        $sprint->tickets()->attach($ticket, ['position' => 1]);

        Carbon::setTestNow('2026-05-17 14:00:00');
        $this->actingAs($developer)->postJson(route('tickets.timer.start', $ticket))->assertOk();
        $this->actingAs($developer)->postJson(route('tickets.block', $ticket), ['reason' => 'Waiting on product decision.'])->assertOk();

        Carbon::setTestNow('2026-05-17 14:15:00');
        $this->actingAs($developer)
            ->postJson(route('tickets.unblock', $ticket), ['unblock_note' => 'Product approved the path forward.'])
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_IN_PROGRESS)
            ->assertJsonPath('active_block', null)
            ->assertJsonPath('block.duration_seconds', 900);

        $block = TicketBlock::firstOrFail();
        $this->assertSame('Product approved the path forward.', $block->unblock_note);
        $this->assertSame(900, $block->duration_seconds);
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->refresh()->status);
        $this->assertSame(TimeLog::STATUS_PAUSED, TimeLog::firstOrFail()->status);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'unblocked']);
    }

    private function clientWorkspace(): array
    {
        $client = Client::create([
            'name' => 'Client Co',
            'description' => 'Test client',
            'status' => Client::STATUS_ACTIVE,
        ]);
        $software = Software::create([
            'client_id' => $client->id,
            'name' => 'Client App',
            'description' => 'Test software',
            'is_enabled' => true,
        ]);
        $user = User::factory()->create(['client_id' => $client->id]);
        $user->assignRole('client_user');

        return [$client, $software, $user];
    }

    private function kielDeveloper(): User
    {
        $developer = User::factory()->create(['client_id' => null]);
        $developer->assignRole('developer');

        return $developer;
    }

    private function ticket(Client $client, Software $software, User $submitter, string $type, string $status): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'TCK-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => 'Blocked work',
            'description' => 'A ticket that can be blocked.',
            'urgency' => 'medium',
            'type' => $type,
            'status' => $status,
            'submitted_at' => now(),
            'classified_at' => now(),
        ]);
    }
}
