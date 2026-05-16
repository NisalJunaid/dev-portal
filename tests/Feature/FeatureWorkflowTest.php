<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_recommend_own_approved_feature(): void
    {
        $this->seed(RoleSeeder::class);

        [$client, $software, $user] = $this->clientWorkspace();
        $feature = $this->featureTicket($client, $software, $user, Ticket::STATUS_FEATURE_APPROVED);

        $this->actingAs($user)
            ->post(route('features.recommend', $feature))
            ->assertRedirect();

        $this->assertSame(Ticket::STATUS_RECOMMENDED, $feature->refresh()->status);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $feature->id,
            'user_id' => $user->id,
            'action' => 'recommended',
            'old_value' => Ticket::STATUS_FEATURE_APPROVED,
            'new_value' => Ticket::STATUS_RECOMMENDED,
        ]);
    }

    public function test_client_cannot_recommend_another_clients_feature(): void
    {
        $this->seed(RoleSeeder::class);

        [$client, $software, $owner] = $this->clientWorkspace('Owner Client', 'owner@example.test');
        [, , $otherUser] = $this->clientWorkspace('Other Client', 'other@example.test');
        $feature = $this->featureTicket($client, $software, $owner, Ticket::STATUS_FEATURE_APPROVED);

        $this->actingAs($otherUser)
            ->post(route('features.recommend', $feature))
            ->assertForbidden();

        $this->assertSame(Ticket::STATUS_FEATURE_APPROVED, $feature->refresh()->status);
    }

    public function test_kiel_user_can_move_recommended_feature_to_next_sprint(): void
    {
        $this->seed(RoleSeeder::class);

        [$client, $software, $submitter] = $this->clientWorkspace();
        $manager = User::factory()->create(['client_id' => null]);
        $manager->assignRole('kiel_manager');
        $feature = $this->featureTicket($client, $software, $submitter, Ticket::STATUS_RECOMMENDED);

        $this->actingAs($manager)
            ->post(route('features.approve-next-sprint', $feature))
            ->assertRedirect();

        $this->assertSame(Ticket::STATUS_NEXT_SPRINT, $feature->refresh()->status);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $feature->id,
            'user_id' => $manager->id,
            'action' => 'moved to next sprint',
            'old_value' => Ticket::STATUS_RECOMMENDED,
            'new_value' => Ticket::STATUS_NEXT_SPRINT,
        ]);
    }

    private function clientWorkspace(string $clientName = 'Client Co', string $email = 'client@example.test'): array
    {
        $client = Client::create([
            'name' => $clientName,
            'description' => 'Test client',
            'status' => Client::STATUS_ACTIVE,
        ]);
        $software = Software::create([
            'client_id' => $client->id,
            'name' => $clientName.' App',
            'description' => 'Test software',
            'is_enabled' => true,
        ]);
        $user = User::factory()->create([
            'client_id' => $client->id,
            'email' => $email,
        ]);
        $user->assignRole('client_user');

        return [$client, $software, $user];
    }

    private function featureTicket(Client $client, Software $software, User $submitter, string $status): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'TCK-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => 'Feature request',
            'description' => 'A valuable feature request.',
            'urgency' => 'medium',
            'type' => Ticket::TYPE_FEATURE,
            'status' => $status,
            'submitted_at' => now(),
            'classified_at' => now(),
        ]);
    }
}
