<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTypeTaskCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiel_user_can_create_task_ticket_type(): void
    {
        $user = User::factory()->create();
        $user->assignRole('developer');
        $client = Client::factory()->create();
        $software = Software::factory()->create(['client_id' => $client->id, 'is_enabled' => true]);

        $this->actingAs($user)
            ->postJson(route('tickets.store'), [
                'title' => 'Task type insert',
                'description' => 'Check enum support',
                'software_id' => $software->id,
                'urgency' => 'medium',
                'type' => 'task',
            ])
            ->assertCreated()
            ->assertJsonPath('ticket.type', Ticket::TYPE_TASK);
    }
}
