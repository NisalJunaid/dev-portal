<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanWorkflowTest extends TestCase
{
    use RefreshDatabase;


    public function test_kanban_page_renders_sortable_columns_and_ticket_drawer(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 1000);

        $this->actingAs($developer)
            ->get(route('kanban.index', ['view' => 'features']))
            ->assertOk()
            ->assertSee('data-kanban-board', false)
            ->assertSee('data-kanban-column', false)
            ->assertSee('data-ticket-drawer', false)
            ->assertSee('Sortable.min.js', false);
    }

    public function test_kiel_user_can_move_and_reorder_kanban_tickets(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $first = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 1000);
        $second = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_RECOMMENDED, 2000);

        $this->actingAs($developer)
            ->patchJson(route('kanban.tickets.move', $first), [
                'column' => Ticket::STATUS_RECOMMENDED,
                'position' => 0,
                'view' => 'features',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_RECOMMENDED)
            ->assertJsonPath('ticket.column', Ticket::STATUS_RECOMMENDED);

        $this->assertSame(Ticket::STATUS_RECOMMENDED, $first->refresh()->status);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $first->id, 'action' => 'kanban status changed']);

        $this->actingAs($developer)
            ->patchJson(route('kanban.tickets.reorder'), [
                'tickets' => [$second->id, $first->id],
                'column' => Ticket::STATUS_RECOMMENDED,
                'view' => 'features',
            ])
            ->assertOk();

        $this->assertLessThan($first->refresh()->priority_order, $second->refresh()->priority_order);
    }

    public function test_invalid_kanban_transition_is_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielDeveloper();
        $bug = $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 1000);

        $this->actingAs($developer)
            ->patchJson(route('kanban.tickets.move', $bug), [
                'column' => Ticket::STATUS_NEXT_SPRINT,
                'position' => 0,
                'view' => 'all',
            ])
            ->assertStatus(422);

        $this->assertSame(Ticket::STATUS_BUG_PENDING, $bug->refresh()->status);
    }

    public function test_client_user_can_only_recommend_allowed_feature_ticket(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $feature = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 1000);
        $bug = $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 1000);

        $this->actingAs($clientUser)
            ->patchJson(route('kanban.tickets.move', $feature), [
                'column' => Ticket::STATUS_RECOMMENDED,
                'position' => 0,
                'view' => 'features',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_RECOMMENDED);

        $this->actingAs($clientUser)
            ->patchJson(route('kanban.tickets.move', $bug), [
                'column' => 'completed',
                'position' => 0,
                'view' => 'all',
            ])
            ->assertForbidden();

        $this->assertSame(Ticket::STATUS_BUG_PENDING, $bug->refresh()->status);
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

    private function ticket(Client $client, Software $software, User $submitter, string $type, string $status, int $priorityOrder): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'TCK-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => 'Kanban work item',
            'description' => 'A ticket used for Kanban testing.',
            'urgency' => 'medium',
            'type' => $type,
            'status' => $status,
            'priority_order' => $priorityOrder,
            'submitted_at' => now(),
            'classified_at' => now(),
        ]);
    }
}
