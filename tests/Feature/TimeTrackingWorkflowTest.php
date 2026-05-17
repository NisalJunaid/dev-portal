<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\TimeTrackingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeTrackingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_kiel_user_can_run_complete_timer_lifecycle(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $submitter] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $submitter);

        Carbon::setTestNow('2026-05-17 10:00:00');
        $this->actingAs($developer)
            ->postJson(route('tickets.timer.start', $ticket))
            ->assertOk()
            ->assertJsonPath('timer.status', TimeLog::STATUS_RUNNING);

        Carbon::setTestNow('2026-05-17 10:05:00');
        $this->actingAs($developer)
            ->postJson(route('tickets.timer.pause', $ticket))
            ->assertOk()
            ->assertJsonPath('timer.status', TimeLog::STATUS_PAUSED)
            ->assertJsonPath('timer.duration_seconds', 300);

        Carbon::setTestNow('2026-05-17 10:10:00');
        $this->actingAs($developer)
            ->postJson(route('tickets.timer.resume', $ticket))
            ->assertOk()
            ->assertJsonPath('timer.status', TimeLog::STATUS_RUNNING);

        Carbon::setTestNow('2026-05-17 10:12:30');
        $this->actingAs($developer)
            ->postJson(route('tickets.timer.stop', $ticket))
            ->assertOk()
            ->assertJsonPath('timer.status', TimeLog::STATUS_COMPLETED)
            ->assertJsonPath('timer.duration_seconds', 450)
            ->assertJsonPath('ticket.cumulative_duration_seconds', 450);

        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'user_id' => $developer->id, 'action' => 'timer started']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'user_id' => $developer->id, 'action' => 'timer paused']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'user_id' => $developer->id, 'action' => 'timer resumed']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'user_id' => $developer->id, 'action' => 'timer stopped']);
    }

    public function test_timer_prevents_duplicate_active_session_for_same_user_and_ticket(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $submitter] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $submitter);

        $this->actingAs($developer)
            ->postJson(route('tickets.timer.start', $ticket))
            ->assertOk();

        $this->actingAs($developer)
            ->postJson(route('tickets.timer.start', $ticket))
            ->assertStatus(422)
            ->assertJsonPath('message', 'You already have an active timer for this ticket.');

        $this->assertSame(1, TimeLog::count());
    }

    public function test_each_team_member_can_track_separate_sessions_and_cumulative_time_is_reportable(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $submitter] = $this->clientWorkspace();
        $firstDeveloper = $this->kielDeveloper();
        $secondDeveloper = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $submitter);

        TimeLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $firstDeveloper->id,
            'client_id' => $client->id,
            'software_id' => $software->id,
            'started_at' => now()->subDays(2),
            'ended_at' => now()->subDays(2)->addMinutes(10),
            'duration_seconds' => 600,
            'status' => TimeLog::STATUS_COMPLETED,
        ]);
        TimeLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $secondDeveloper->id,
            'client_id' => $client->id,
            'software_id' => $software->id,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addMinutes(15),
            'duration_seconds' => 900,
            'status' => TimeLog::STATUS_COMPLETED,
        ]);

        $service = app(TimeTrackingService::class);

        $this->assertSame(1500, $service->cumulativeDurationForTicket($ticket));
        $this->assertSame(1500, (int) $service->reportQuery(['client_id' => $client->id])->sum('duration_seconds'));
        $this->assertSame(1500, (int) $service->reportQuery(['software_id' => $software->id])->sum('duration_seconds'));
        $this->assertSame(600, (int) $service->reportQuery(['user_id' => $firstDeveloper->id])->sum('duration_seconds'));
        $this->assertSame(1500, (int) $service->reportQuery(['from' => now()->subDays(3)->toDateString(), 'to' => now()->toDateString()])->sum('duration_seconds'));
    }

    public function test_client_user_cannot_access_timers_or_see_timer_panel(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $ticket = $this->ticket($client, $software, $clientUser);

        $this->actingAs($clientUser)
            ->postJson(route('tickets.timer.start', $ticket))
            ->assertForbidden();

        $this->actingAs($clientUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Kiel internal')
            ->assertDontSee('Timer');
    }

    public function test_blocking_ticket_automatically_pauses_running_timers(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $submitter] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $submitter, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING);

        Carbon::setTestNow('2026-05-17 11:00:00');
        $this->actingAs($developer)->postJson(route('tickets.timer.start', $ticket))->assertOk();

        Carbon::setTestNow('2026-05-17 11:03:20');
        $this->actingAs($developer)->post(route('bugs.block', $ticket), ['reason' => 'External dependency.'])->assertRedirect();

        $timeLog = TimeLog::firstOrFail();

        $this->assertSame(TimeLog::STATUS_PAUSED, $timeLog->status);
        $this->assertSame(200, $timeLog->duration_seconds);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'user_id' => $developer->id,
            'action' => 'timer paused',
            'description' => 'Timer paused automatically because the ticket was blocked.',
        ]);
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

    private function ticket(Client $client, Software $software, User $submitter, string $type = Ticket::TYPE_FEATURE, string $status = Ticket::STATUS_IN_PROGRESS): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'TCK-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => 'Trackable work',
            'description' => 'A ticket that can be timed.',
            'urgency' => 'medium',
            'type' => $type,
            'status' => $status,
            'submitted_at' => now(),
            'classified_at' => now(),
        ]);
    }
}
