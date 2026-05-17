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

class SprintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiel_user_can_start_sprint_from_next_sprint_features(): void
    {
        $this->seed(RoleSeeder::class);

        [$client, $software, $submitter] = $this->clientWorkspace();
        $manager = $this->kielManager();
        $firstFeature = $this->featureTicket($client, $software, $submitter, Ticket::STATUS_NEXT_SPRINT, 'First feature');
        $secondFeature = $this->featureTicket($client, $software, $submitter, Ticket::STATUS_NEXT_SPRINT, 'Second feature');
        $recommendedFeature = $this->featureTicket($client, $software, $submitter, Ticket::STATUS_RECOMMENDED, 'Future recommendation');

        $this->actingAs($manager)
            ->post(route('sprints.start.store'), ['client_id' => $client->id])
            ->assertRedirect();

        $sprint = Sprint::firstOrFail();

        $this->assertSame($client->id, $sprint->client_id);
        $this->assertSame($software->id, $sprint->software_id);
        $this->assertSame(1, $sprint->sprint_no);
        $this->assertSame(Sprint::STATUS_IN_PROGRESS, $sprint->status);
        $this->assertNotNull($sprint->started_at);
        $this->assertSame($manager->id, $sprint->started_by);
        $this->assertSame(Ticket::STATUS_NEXT_SPRINT, $firstFeature->refresh()->status);
        $this->assertSame(Ticket::STATUS_NEXT_SPRINT, $secondFeature->refresh()->status);
        $this->assertSame(Ticket::STATUS_RECOMMENDED, $recommendedFeature->refresh()->status);
        $generatedTasks = Ticket::where('is_generated_task', true)->where('generated_from_sprint_id', $sprint->id)->get();
        $this->assertCount(2, $generatedTasks);
        $this->assertTrue($generatedTasks->pluck('source_feature_id')->contains($firstFeature->id));
        $this->assertTrue($generatedTasks->pluck('source_feature_id')->contains($secondFeature->id));
        $this->assertDatabaseHas('sprint_activities', ['sprint_id' => $sprint->id, 'user_id' => $manager->id, 'action' => 'started']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $firstFeature->id, 'user_id' => $manager->id, 'action' => 'converted to task for sprint']);
    }

    public function test_kiel_user_can_complete_sprint_and_review_analytics(): void
    {
        $this->seed(RoleSeeder::class);

        [$client, $software, $submitter] = $this->clientWorkspace();
        $manager = $this->kielManager();
        $completedFeature = $this->featureTicket($client, $software, $submitter, Ticket::STATUS_FEATURE_COMPLETED, 'Completed feature');
        $incompleteFeature = $this->featureTicket($client, $software, $submitter, Ticket::STATUS_IN_PROGRESS, 'Incomplete feature');
        $sprint = Sprint::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'sprint_no' => 1,
            'name' => 'Sprint Cycle 1 - May 16, 2026',
            'status' => Sprint::STATUS_IN_PROGRESS,
            'started_at' => now()->subHours(2),
            'started_by' => $manager->id,
        ]);
        $sprint->items()->create(['ticket_id' => $completedFeature->id, 'position' => 1]);
        $sprint->items()->create(['ticket_id' => $incompleteFeature->id, 'position' => 2]);

        $this->actingAs($manager)
            ->post(route('sprints.complete', $sprint))
            ->assertRedirect(route('sprints.show', $sprint));

        $sprint->refresh();

        $this->assertSame(Sprint::STATUS_COMPLETED, $sprint->status);
        $this->assertNotNull($sprint->ended_at);
        $this->assertGreaterThanOrEqual(7200, $sprint->duration_seconds);
        $this->assertSame($manager->id, $sprint->ended_by);
        $this->assertDatabaseHas('sprint_activities', ['sprint_id' => $sprint->id, 'user_id' => $manager->id, 'action' => 'completed']);

        $this->actingAs($manager)
            ->get(route('sprints.show', $sprint))
            ->assertOk()
            ->assertSee('Completed tickets')
            ->assertSee('Completed feature')
            ->assertSee('Incomplete tickets for Kiel review')
            ->assertSee('Incomplete feature')
            ->assertSee('Duration');
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

    private function kielManager(): User
    {
        $manager = User::factory()->create(['client_id' => null]);
        $manager->assignRole('kiel_manager');

        return $manager;
    }

    private function featureTicket(Client $client, Software $software, User $submitter, string $status, string $title): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'TCK-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => $title,
            'description' => 'A valuable feature request.',
            'urgency' => 'medium',
            'type' => Ticket::TYPE_FEATURE,
            'status' => $status,
            'submitted_at' => now(),
            'classified_at' => now(),
            'completed_at' => $status === Ticket::STATUS_FEATURE_COMPLETED ? now() : null,
            'actual_completed_at' => $status === Ticket::STATUS_FEATURE_COMPLETED ? now() : null,
        ]);
    }
}
