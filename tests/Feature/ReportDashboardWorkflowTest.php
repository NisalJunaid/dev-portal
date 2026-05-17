<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportDashboardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ticket_report_filters_by_client_software_type_status_and_date(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace('Acme', 'Billing');
        [$otherClient, $otherSoftware, $otherUser] = $this->clientWorkspace('Other', 'Portal');
        $manager = $this->kielManager();

        $included = $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 'Included bug', '2026-05-10 10:00:00');
        $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 'Wrong type', '2026-05-10 10:00:00');
        $this->ticket($otherClient, $otherSoftware, $otherUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 'Wrong client', '2026-05-10 10:00:00');
        $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 'Wrong date', '2026-04-01 10:00:00');

        $this->actingAs($manager)
            ->get(route('reports.tickets', [
                'client_id' => $client->id,
                'software_id' => $software->id,
                'ticket_type' => Ticket::TYPE_BUG,
                'status' => Ticket::STATUS_BUG_PENDING,
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
            ]))
            ->assertOk()
            ->assertSee($included->ticket_no)
            ->assertSee('Included bug')
            ->assertDontSee('Wrong type')
            ->assertDontSee('Wrong client')
            ->assertDontSee('Wrong date');
    }

    public function test_kiel_user_can_export_csv_report(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace('Acme', 'Billing');
        $manager = $this->kielManager();
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 'Export me', '2026-05-10 10:00:00');

        $this->actingAs($manager)
            ->get(route('reports.export.tickets', ['ticket_type' => Ticket::TYPE_FEATURE]))
            ->assertOk()
            ->assertDownload('tickets-report-'.now()->format('Y-m-d').'.csv')
            ->assertSee('Ticket #')
            ->assertSee($ticket->ticket_no)
            ->assertSee('Export me');
    }

    public function test_client_reports_are_limited_to_own_organization_and_cannot_export(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace('Acme', 'Billing');
        [$otherClient, $otherSoftware, $otherUser] = $this->clientWorkspace('Other', 'Portal');
        $ownTicket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 'Own visible ticket', '2026-05-10 10:00:00');
        $otherTicket = $this->ticket($otherClient, $otherSoftware, $otherUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 'Hidden other ticket', '2026-05-10 10:00:00');

        $this->actingAs($clientUser)
            ->get(route('reports.tickets'))
            ->assertOk()
            ->assertSee($ownTicket->ticket_no)
            ->assertSee('Own visible ticket')
            ->assertDontSee($otherTicket->ticket_no)
            ->assertDontSee('Hidden other ticket');

        $this->actingAs($clientUser)
            ->get(route('reports.tickets', ['client_id' => $otherClient->id]))
            ->assertOk()
            ->assertDontSee($otherTicket->ticket_no)
            ->assertDontSee('Hidden other ticket');

        $this->actingAs($clientUser)
            ->get(route('reports.export.tickets'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_kiel_and_client_metrics(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace('Acme', 'Billing');
        $manager = $this->kielManager();
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_RECOMMENDED, 'Recommended item', '2026-05-10 10:00:00');
        Sprint::create(['client_id' => $client->id, 'software_id' => $software->id, 'sprint_no' => 1, 'name' => 'Active Sprint', 'status' => Sprint::STATUS_IN_PROGRESS, 'started_at' => now(), 'started_by' => $manager->id]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total tickets')
            ->assertSee('Tickets by client')
            ->assertSee('Recent activity');

        $this->actingAs($clientUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My submitted tickets')
            ->assertSee('Recommended features')
            ->assertSee('Current sprint progress');
    }

    private function clientWorkspace(string $clientName, string $softwareName): array
    {
        $client = Client::create(['name' => $clientName, 'description' => 'Test client', 'status' => Client::STATUS_ACTIVE]);
        $software = Software::create(['client_id' => $client->id, 'name' => $softwareName, 'description' => 'Test software', 'is_enabled' => true]);
        $user = User::factory()->create(['client_id' => $client->id]);
        $user->assignRole('client_user');

        return [$client, $software, $user];
    }

    private function kielManager(): User
    {
        $user = User::factory()->create(['client_id' => null]);
        $user->assignRole('kiel_manager');

        return $user;
    }

    private function ticket(Client $client, Software $software, User $submitter, string $type, string $status, string $title, string $submittedAt): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'TCK-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => $title,
            'description' => 'Report test ticket.',
            'urgency' => 'medium',
            'type' => $type,
            'status' => $status,
            'submitted_at' => Carbon::parse($submittedAt),
            'classified_at' => Carbon::parse($submittedAt),
        ]);
    }
}
