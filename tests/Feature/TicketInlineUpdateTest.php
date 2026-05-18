<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketInlineUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiel_user_can_inline_update_ticket_fields_and_activity_is_logged(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper('Developer One');
        $assignee = $this->kielDeveloper('Developer Two');
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED);

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'title',
                'value' => 'Inline edited title',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.title', 'Inline edited title');

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'urgency',
                'value' => 'critical',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.urgency', 'critical')
            ->assertJsonPath('ticket.urgency_label', 'Critical');

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'assigned_to',
                'value' => (string) $assignee->id,
            ])
            ->assertOk()
            ->assertJsonPath('ticket.assignee_name', 'Developer Two');

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'start_date',
                'value' => '2026-05-18',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.start_date', '2026-05-18');

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'due_date',
                'value' => '2026-05-21',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.due_date', '2026-05-21');

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'status',
                'value' => Ticket::STATUS_NEXT_SPRINT,
            ])
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_NEXT_SPRINT)
            ->assertJsonPath('ticket.status_label', 'Next Sprint');

        $ticket->refresh();

        $this->assertSame('Inline edited title', $ticket->title);
        $this->assertSame('critical', $ticket->urgency);
        $this->assertSame($assignee->id, $ticket->assigned_to);
        $this->assertSame('2026-05-18', $ticket->start_date->toDateString());
        $this->assertSame('2026-05-21', $ticket->due_date->toDateString());
        $this->assertSame(Ticket::STATUS_NEXT_SPRINT, $ticket->status);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'title changed']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'urgency changed']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'assigned']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'dates changed']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'status changed']);
    }

    public function test_inline_update_validates_field_and_value(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING);

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'description',
                'value' => 'Not allowed inline.',
            ])
            ->assertStatus(422);

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'urgency',
                'value' => 'eventually',
            ])
            ->assertStatus(422);

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'status',
                'value' => Ticket::STATUS_BUG_BLOCKED,
            ])
            ->assertStatus(422);
    }

    public function test_client_user_cannot_inline_update_operational_fields(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED);

        $this->actingAs($clientUser)
            ->patchJson(route('tickets.inline-update', $ticket), [
                'field' => 'title',
                'value' => 'Client edit attempt',
            ])
            ->assertForbidden();

        $this->assertNotSame('Client edit attempt', $ticket->refresh()->title);
    }


    public function test_moving_generated_task_to_next_sprint_archives_task_and_returns_source_feature(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();

        $feature = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_IN_PROGRESS);
        $task = $this->ticket($client, $software, $clientUser, Ticket::TYPE_TASK, Ticket::STATUS_IN_PROGRESS);
        $task->update(['is_generated_task' => true, 'source_feature_id' => $feature->id, 'generated_from_sprint_id' => 1]);

        $response = $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $task), ['field' => 'status', 'value' => Ticket::STATUS_NEXT_SPRINT])
            ->assertOk()
            ->assertJsonPath('removed_from_tasks', true)
            ->assertJsonPath('feature.id', $feature->id)
            ->assertJsonPath('feature.status', Ticket::STATUS_NEXT_SPRINT);

        $task->refresh();
        $feature->refresh();

        $this->assertNotNull($task->archived_at);
        $this->assertSame(Ticket::STATUS_BACKLOG, $task->status);
        $this->assertSame(Ticket::STATUS_NEXT_SPRINT, $feature->status);
        $this->assertEmpty($task->sprints()->pluck('sprints.id')->all());
        $this->assertFalse(Ticket::query()->notArchived()->whereKey($task->id)->exists());
        $response->assertJsonPath('feature_drawer_url', route('tickets.drawer', $feature));
    }

    public function test_moving_non_generated_task_to_next_sprint_converts_to_feature(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();

        $task = $this->ticket($client, $software, $clientUser, Ticket::TYPE_TASK, Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $task), ['field' => 'status', 'value' => Ticket::STATUS_NEXT_SPRINT])
            ->assertOk()
            ->assertJsonPath('removed_from_tasks', true)
            ->assertJsonPath('feature.id', $task->id);

        $task->refresh();
        $this->assertSame(Ticket::TYPE_FEATURE, $task->type);
        $this->assertSame(Ticket::STATUS_NEXT_SPRINT, $task->status);
        $this->assertFalse(Ticket::query()->whereIn('type', [Ticket::TYPE_TASK, Ticket::TYPE_BUG])->whereKey($task->id)->exists());
    }

    public function test_bug_ticket_cannot_move_to_next_sprint_via_inline_status_update(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $bug = $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING);

        $this->actingAs($developer)
            ->patchJson(route('tickets.inline-update', $bug), ['field' => 'status', 'value' => Ticket::STATUS_NEXT_SPRINT])
            ->assertStatus(422);
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

    private function kielDeveloper(string $name = 'Developer'): User
    {
        $developer = User::factory()->create(['client_id' => null, 'name' => $name]);
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
            'title' => 'Inline editable work',
            'description' => 'A ticket that can be updated inline.',
            'urgency' => 'medium',
            'type' => $type,
            'status' => $status,
            'submitted_at' => now(),
            'classified_at' => now(),
        ]);
    }
}
