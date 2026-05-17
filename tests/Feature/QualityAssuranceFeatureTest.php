<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QualityAssuranceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_login_logout_and_protected_routes(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create([
            'email' => 'qa-user@example.test',
            'password' => Hash::make('correct-password'),
        ]);
        $user->assignRole('developer');

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => 'qa-user@example.test',
            'password' => 'wrong-password',
        ])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), [
            'email' => 'qa-user@example.test',
            'password' => 'correct-password',
        ])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_role_access_separates_kiel_operational_actions_from_client_users(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace('Client Co', 'client@example.test');
        $developer = $this->kielUser('developer');
        $feature = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_RECOMMENDED);

        $this->actingAs($clientUser)
            ->get(route('tickets.backlog'))
            ->assertForbidden();

        $this->actingAs($clientUser)
            ->post(route('features.approve-next-sprint', $feature))
            ->assertForbidden();
        $this->assertSame(Ticket::STATUS_RECOMMENDED, $feature->refresh()->status);

        $this->actingAs($developer)
            ->get(route('tickets.backlog'))
            ->assertOk();

        $this->actingAs($developer)
            ->post(route('features.approve-next-sprint', $feature))
            ->assertRedirect();
        $this->assertSame(Ticket::STATUS_NEXT_SPRINT, $feature->refresh()->status);
    }

    public function test_client_isolation_for_ticket_lists_detail_drawer_and_reports(): void
    {
        $this->seed(RoleSeeder::class);
        [$ownClient, $ownSoftware, $ownUser] = $this->clientWorkspace('Own Client', 'own@example.test');
        [$otherClient, $otherSoftware, $otherUser] = $this->clientWorkspace('Other Client', 'other@example.test');
        $ownTicket = $this->ticket($ownClient, $ownSoftware, $ownUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 'Own Visible Ticket');
        $otherTicket = $this->ticket($otherClient, $otherSoftware, $otherUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 'Other Hidden Ticket');

        $this->actingAs($ownUser)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee($ownTicket->title)
            ->assertDontSee($otherTicket->title);

        $this->actingAs($ownUser)
            ->get(route('tickets.show', $otherTicket))
            ->assertForbidden();

        $this->actingAs($ownUser)
            ->getJson(route('tickets.drawer', $otherTicket))
            ->assertForbidden();

        $this->actingAs($ownUser)
            ->get(route('reports.tickets'))
            ->assertOk()
            ->assertSee($ownTicket->ticket_no)
            ->assertDontSee($otherTicket->ticket_no);
    }

    public function test_client_ticket_submission_uses_only_enabled_software_for_their_client(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $enabledSoftware, $clientUser] = $this->clientWorkspace('Client Co', 'client@example.test');
        $disabledSoftware = Software::create([
            'client_id' => $client->id,
            'name' => 'Disabled App',
            'description' => 'Unavailable software',
            'is_enabled' => false,
        ]);
        [$otherClient, $otherSoftware] = $this->clientWorkspace('Other Client', 'other@example.test');

        $this->actingAs($clientUser)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee($enabledSoftware->name)
            ->assertDontSee($disabledSoftware->name)
            ->assertDontSee($otherSoftware->name);

        $this->actingAs($clientUser)
            ->post(route('tickets.store'), [
                'title' => 'Client submitted bug',
                'description' => 'The client can submit tickets for enabled software.',
                'software_id' => $enabledSoftware->id,
                'urgency' => 'high',
            ])
            ->assertRedirect();

        $ticket = Ticket::where('title', 'Client submitted bug')->firstOrFail();
        $this->assertSame($client->id, $ticket->client_id);
        $this->assertSame($clientUser->id, $ticket->submitted_by);
        $this->assertSame(Ticket::STATUS_BACKLOG, $ticket->status);
        $this->assertNull($ticket->type);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'created']);

        $this->actingAs($clientUser)
            ->post(route('tickets.store'), [
                'title' => 'Rejected disabled software',
                'description' => 'Disabled software should fail validation.',
                'software_id' => $disabledSoftware->id,
                'urgency' => 'medium',
            ])
            ->assertSessionHasErrors('software_id');

        $this->actingAs($clientUser)
            ->post(route('tickets.store'), [
                'title' => 'Rejected other client software',
                'description' => 'Other client software should be forbidden.',
                'software_id' => $otherSoftware->id,
                'urgency' => 'medium',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tickets', ['title' => 'Rejected disabled software']);
        $this->assertDatabaseMissing('tickets', ['title' => 'Rejected other client software']);
    }

    public function test_kiel_ticket_classification_and_rejection_with_reason_are_audited(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielUser('developer');
        $backlogBug = $this->ticket($client, $software, $clientUser, null, Ticket::STATUS_BACKLOG, 'Unclassified bug');
        $backlogFeature = $this->ticket($client, $software, $clientUser, null, Ticket::STATUS_BACKLOG, 'Unclassified feature');

        $this->actingAs($developer)
            ->patch(route('tickets.classify', $backlogBug), ['type' => Ticket::TYPE_BUG])
            ->assertRedirect();

        $this->assertSame(Ticket::TYPE_BUG, $backlogBug->refresh()->type);
        $this->assertSame(Ticket::STATUS_BUG_PENDING, $backlogBug->status);
        $this->assertNotNull($backlogBug->classified_at);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $backlogBug->id, 'action' => 'classified']);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $backlogBug->id, 'action' => 'status changed']);

        $this->actingAs($developer)
            ->patch(route('tickets.reject', $backlogFeature), ['rejection_reason' => 'Out of current product scope.'])
            ->assertRedirect();

        $this->assertSame(Ticket::STATUS_REJECTED, $backlogFeature->refresh()->status);
        $this->assertSame('Out of current product scope.', $backlogFeature->rejection_reason);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $backlogFeature->id, 'action' => 'rejected']);
    }

    public function test_bug_workflow_completion_and_blocked_bug_guardrails(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielUser('developer');
        $bug = $this->ticket($client, $software, $clientUser, Ticket::TYPE_BUG, Ticket::STATUS_BUG_PENDING, 'Bug workflow');

        $this->actingAs($developer)
            ->postJson(route('bugs.complete', $bug))
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_BUG_COMPLETED);

        $this->assertSame(Ticket::STATUS_BUG_COMPLETED, $bug->refresh()->status);
        $this->assertNotNull($bug->completed_at);
        $this->assertNotNull($bug->actual_completed_at);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $bug->id, 'action' => 'bug status changed']);

        $this->actingAs($developer)
            ->postJson(route('bugs.pending', $bug))
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_BUG_PENDING);
        $this->assertNull($bug->refresh()->completed_at);

        $this->actingAs($developer)
            ->postJson(route('bugs.block', $bug), ['reason' => 'Waiting on production logs.'])
            ->assertOk()
            ->assertJsonPath('ticket.status', Ticket::STATUS_BUG_BLOCKED);

        $this->actingAs($developer)
            ->postJson(route('bugs.complete', $bug))
            ->assertStatus(422);
        $this->assertSame(Ticket::STATUS_BUG_BLOCKED, $bug->refresh()->status);
    }

    public function test_comments_threading_internal_visibility_and_activity_timeline_logging(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielUser('developer');
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED);

        $this->actingAs($clientUser)
            ->postJson(route('tickets.comments.store', $ticket), [
                'comment' => 'Client-facing question',
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Comment added.']);

        $publicComment = TicketComment::where('comment', 'Client-facing question')->firstOrFail();

        $this->actingAs($developer)
            ->postJson(route('tickets.comments.store', $ticket), [
                'parent_id' => $publicComment->id,
                'comment' => 'Internal engineering note',
                'is_internal' => true,
            ])
            ->assertOk()
            ->assertSee('Internal engineering note', false);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'comment' => 'Internal engineering note',
            'is_internal' => true,
        ]);
        $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'comment added']);

        $this->actingAs($clientUser)
            ->getJson(route('tickets.drawer', $ticket))
            ->assertOk()
            ->assertSee('Client-facing question', false)
            ->assertDontSee('Internal engineering note', false);

        $this->actingAs($developer)
            ->getJson(route('tickets.drawer', $ticket))
            ->assertOk()
            ->assertSee('Internal engineering note', false)
            ->assertSee('Comment Added', false);
    }

    public function test_manual_qa_pages_render_list_kanban_timeline_drawer_reports_and_dashboards(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $software, $clientUser] = $this->clientWorkspace();
        $developer = $this->kielUser('developer');
        $ticket = $this->ticket($client, $software, $clientUser, Ticket::TYPE_FEATURE, Ticket::STATUS_FEATURE_APPROVED, 'Manual QA ticket');
        $ticket->update(['start_date' => '2026-05-18', 'due_date' => '2026-05-20']);

        $this->actingAs($developer)->get(route('tasks.index'))->assertOk()->assertSee('Manual QA ticket');
        $this->actingAs($developer)->get(route('tasks.index', ['view' => 'list']))->assertOk();
        $this->actingAs($developer)->get(route('tasks.index', ['view' => 'board']))->assertOk();
        $this->actingAs($developer)->get(route('tasks.index', ['view' => 'timeline']))->assertOk();
        $this->actingAs($developer)->get(route('tickets.index'))->assertRedirectContains('/tasks');
        $this->actingAs($developer)->get(route('kanban.index'))->assertRedirectContains('/tasks');
        $this->actingAs($developer)->get(route('timeline.index'))->assertRedirectContains('/tasks');
        $this->actingAs($developer)->getJson(route('tickets.drawer', $ticket))->assertOk()->assertSee('Manual QA ticket', false);
        $this->actingAs($developer)->get(route('reports.index'))->assertOk()->assertSee('Reports');
        $this->actingAs($developer)->get(route('dashboard'))->assertOk()->assertSee('Kiel global workspace');
        $this->actingAs($clientUser)->get(route('dashboard'))->assertOk()->assertSee($client->name);
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

    private function kielUser(string $role): User
    {
        $user = User::factory()->create(['client_id' => null]);
        $user->assignRole($role);

        return $user;
    }

    private function ticket(Client $client, Software $software, User $submitter, ?string $type, string $status, string $title = 'QA workflow ticket'): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'software_id' => $software->id,
            'submitted_by' => $submitter->id,
            'ticket_no' => 'QA-'.str_pad((string) (Ticket::count() + 1), 5, '0', STR_PAD_LEFT),
            'title' => $title,
            'description' => 'A ticket used for full QA feature testing.',
            'urgency' => 'medium',
            'type' => $type,
            'status' => $status,
            'priority_order' => 1000,
            'submitted_at' => now(),
            'classified_at' => $type ? now() : null,
        ]);
    }
}
