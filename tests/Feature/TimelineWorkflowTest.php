<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_page_renders_gantt_filters_loading_empty_state_and_drawer(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_IN_PROGRESS, now()->toDateString(), now()->addDays(4)->toDateString());

        $this->actingAs($developer)
            ->get(route('timeline.index'))
            ->assertOk()
            ->assertSee('data-timeline-view', false)
            ->assertSee('data-timeline-filters', false)
            ->assertSee('data-timeline-chart', false)
            ->assertSee('data-timeline-loading', false)
            ->assertSee('data-timeline-empty', false)
            ->assertSee('data-timeline-drawer', false)
            ->assertSee('frappe-gantt', false)
            ->assertSee('Save dependency', false);
    }

    public function test_timeline_data_returns_frappe_gantt_tasks_with_filters_and_badges(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $visible = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_BLOCKED, now()->subDays(5)->toDateString(), now()->subDay()->toDateString(), 'critical');
        $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, now()->toDateString(), now()->addDays(2)->toDateString(), 'low');

        $this->actingAs($developer)
            ->getJson(route('timeline.data', ['client_id' => $client->id, 'software_id' => $software->id, 'urgency' => 'critical', 'status' => Ticket::STATUS_FEATURE_BLOCKED]))
            ->assertOk()
            ->assertJsonPath('library', 'frappe-gantt')
            ->assertJsonPath('can_edit', true)
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.id', (string) $visible->id)
            ->assertJsonPath('tasks.0.ticket.assignee', 'Unassigned')
            ->assertJsonPath('tasks.0.ticket.status_label', 'Feature Blocked')
            ->assertJsonPath('tasks.0.ticket.urgency_label', 'Critical')
            ->assertJsonPath('tasks.0.ticket.blocked', true)
            ->assertJsonPath('tasks.0.ticket.overdue', true);
    }

    public function test_kiel_user_can_drag_or_resize_timeline_dates(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_IN_PROGRESS, '2026-05-18', '2026-05-20');

        $this->actingAs($developer)
            ->patchJson(route('timeline.tasks.dates', $ticket), [
                'start_date' => '2026-05-19',
                'due_date' => '2026-05-24',
            ])
            ->assertOk()
            ->assertJsonPath('task.start', '2026-05-19')
            ->assertJsonPath('task.end', '2026-05-24');

        $this->assertSame('2026-05-19', $ticket->refresh()->start_date->toDateString());
        $this->assertSame('2026-05-24', $ticket->due_date->toDateString());
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'timeline dates changed']);
    }

    public function test_timeline_date_update_validates_permissions_and_date_order(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_IN_PROGRESS, '2026-05-18', '2026-05-20');

        $this->actingAs($developer)
            ->patchJson(route('timeline.tasks.dates', $ticket), [
                'start_date' => '2026-05-24',
                'due_date' => '2026-05-20',
            ])
            ->assertStatus(422);

        $this->actingAs($clientUser)
            ->patchJson(route('timeline.tasks.dates', $ticket), [
                'start_date' => '2026-05-19',
                'due_date' => '2026-05-24',
            ])
            ->assertForbidden();
    }

    public function test_kiel_user_can_create_dependency_and_circular_dependencies_are_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $first = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_IN_PROGRESS, '2026-05-18', '2026-05-20');
        $second = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_NEXT_SPRINT, '2026-05-21', '2026-05-24');

        $this->actingAs($developer)
            ->patchJson(route('timeline.tasks.dependency', $second), [
                'depends_on_ticket_id' => $first->id,
            ])
            ->assertOk()
            ->assertJsonPath('task.dependencies', (string) $first->id)
            ->assertJsonPath('task.ticket.dependency_label', $first->ticket_no);

        $this->assertSame($first->id, $second->refresh()->depends_on_ticket_id);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $second->id, 'action' => 'timeline dependency changed']);

        $this->actingAs($developer)
            ->patchJson(route('timeline.tasks.dependency', $first), [
                'depends_on_ticket_id' => $second->id,
            ])
            ->assertStatus(422);

        $this->assertNull($first->refresh()->depends_on_ticket_id);
    }

    public function test_timeline_filters_by_sprint_and_assignee(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $assignee = User::factory()->create();
        $assignee->assignRole('developer');
        $sprint = Sprint::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'sprint_no' => 1,
            'name' => 'Timeline Sprint',
            'status' => Sprint::STATUS_PLANNED,
        ]);
        $included = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_NEXT_SPRINT, '2026-05-18', '2026-05-20', 'medium', $assignee->id);
        $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_NEXT_SPRINT, '2026-05-21', '2026-05-24');
        $sprint->tickets()->attach($included->id, ['position' => 1000]);

        $this->actingAs($developer)
            ->getJson(route('timeline.data', ['sprint_id' => $sprint->id, 'assigned_to' => $assignee->id]))
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.id', (string) $included->id);
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

    private function ticket(Client $client, Software $software, User $submitter, string $type, string $status, string $startDate, string $dueDate, string $urgency = 'medium', ?int $assignedTo = null): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'assigned_to' => $assignedTo,
            'ticket_no' => 'TLN-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => 'Timeline work item',
            'description' => 'A ticket used for Timeline testing.',
            'urgency' => $urgency,
            'type' => $type,
            'status' => $status,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'priority_order' => 1000,
            'submitted_at' => now(),
            'classified_at' => now(),
        ]);
    }
}
