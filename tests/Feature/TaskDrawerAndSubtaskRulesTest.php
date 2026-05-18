<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDrawerAndSubtaskRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_drawer_restores_core_fields_and_subtask_actions(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser, $developer] = $this->setupWorkspace();
        $parent = $this->ticket($client, $software, $clientUser, Ticket::TYPE_TASK, Ticket::STATUS_BACKLOG);
        $child = $this->ticket($client, $software, $clientUser, Ticket::TYPE_TASK, Ticket::STATUS_BACKLOG, $parent->id);

        $html = $this->actingAs($developer)->getJson(route('tickets.drawer', $parent))->assertOk()->json('html');
        $this->assertStringContainsString('data-inline-field="status"', $html);
        $this->assertStringContainsString('data-inline-field="urgency"', $html);
        $this->assertStringContainsString('data-inline-field="assigned_to"', $html);
        $this->assertStringContainsString('data-inline-field="start_date"', $html);
        $this->assertStringContainsString('data-inline-field="due_date"', $html);
        $this->assertStringContainsString('data-inline-field="description"', $html);
        $this->assertStringContainsString('Client', $html);
        $this->assertStringContainsString('Software', $html);
        $this->assertStringContainsString('Sprint', $html);
        $this->assertStringContainsString('data-add-subtask', $html);

        $subHtml = $this->actingAs($developer)->getJson(route('tickets.drawer', $child))->assertOk()->json('html');
        $this->assertStringNotContainsString('data-add-subtask', $subHtml);
    }

    public function test_nested_subtasks_rejected_and_parent_status_propagates(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser, $developer] = $this->setupWorkspace();
        $parent = $this->ticket($client, $software, $clientUser, Ticket::TYPE_TASK, Ticket::STATUS_BACKLOG);
        $child = $this->ticket($client, $software, $clientUser, Ticket::TYPE_TASK, Ticket::STATUS_BACKLOG, $parent->id);

        $this->actingAs($developer)->postJson(route('tickets.store'), [
            'title' => 'Nested child',
            'description' => 'No nesting',
            'type' => Ticket::TYPE_TASK,
            'parent_ticket_id' => $child->id,
            'urgency' => 'high',
        ])->assertStatus(422);

        $this->actingAs($developer)->patchJson(route('tickets.inline-update', $parent), [
            'field' => 'status',
            'value' => Ticket::STATUS_IN_PROGRESS,
        ])->assertOk()->assertJsonCount(1, 'updated_child_tickets');

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $child->fresh()->status);

        $this->actingAs($developer)->patchJson(route('tickets.inline-update', $parent), [
            'field' => 'status',
            'value' => Ticket::STATUS_TASK_COMPLETED,
        ])->assertOk();

        $this->assertSame(Ticket::STATUS_TASK_COMPLETED, $child->fresh()->status);

        $this->actingAs($developer)->patchJson(route('tickets.inline-update', $child), [
            'field' => 'status',
            'value' => Ticket::STATUS_BACKLOG,
        ])->assertStatus(422);
    }

    private function setupWorkspace(): array
    {
        $client = Client::create(['name' => 'Client Co', 'description' => 'Test', 'status' => Client::STATUS_ACTIVE]);
        $software = Software::create(['client_id' => $client->id, 'name' => 'Client App', 'description' => 'App', 'is_enabled' => true]);
        $clientUser = User::factory()->create(['client_id' => $client->id]);
        $clientUser->assignRole('client_user');
        $developer = User::factory()->create(['client_id' => null]);
        $developer->assignRole('developer');

        return [$client, $software, $clientUser, $developer];
    }

    private function ticket(Client $client, Software $software, User $submitter, string $type, string $status, ?int $parentId = null): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'TCK-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => 'Task '.Ticket::count(),
            'description' => 'desc',
            'urgency' => 'medium',
            'type' => $type,
            'status' => $status,
            'submitted_at' => now(),
            'parent_ticket_id' => $parentId,
        ]);
    }
}
