<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Software;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_policy_allows_kiel_management_and_client_self_scope_only(): void
    {
        $this->seed(RoleSeeder::class);
        [$ownClient, , $clientAdmin] = $this->clientWorkspace('Own Client', 'own-admin@example.test', 'client_admin');
        [$otherClient] = $this->clientWorkspace('Other Client', 'other-admin@example.test', 'client_admin');
        $developer = $this->kielUser('developer');
        $manager = $this->kielUser('kiel_manager');

        $this->assertTrue(Gate::forUser($developer)->allows('viewAny', Client::class));
        $this->assertTrue(Gate::forUser($manager)->allows('update', $ownClient));
        $this->assertTrue(Gate::forUser($clientAdmin)->allows('view', $ownClient));
        $this->assertFalse(Gate::forUser($clientAdmin)->allows('view', $otherClient));
        $this->assertFalse(Gate::forUser($clientAdmin)->allows('create', Client::class));
        $this->assertFalse(Gate::forUser($clientAdmin)->allows('disable', $ownClient));
    }

    public function test_software_policy_hides_disabled_or_cross_client_software_from_clients(): void
    {
        $this->seed(RoleSeeder::class);
        [$client, $enabledSoftware, $clientUser] = $this->clientWorkspace('Client Co', 'client@example.test');
        $disabledSoftware = Software::create([
            'client_id' => $client->id,
            'name' => 'Disabled App',
            'description' => 'Disabled software',
            'is_enabled' => false,
        ]);
        [, $otherSoftware] = $this->clientWorkspace('Other Client', 'other@example.test');
        $developer = $this->kielUser('developer');

        $this->assertTrue(Gate::forUser($clientUser)->allows('viewAny', Software::class));
        $this->assertTrue(Gate::forUser($clientUser)->allows('view', $enabledSoftware));
        $this->assertFalse(Gate::forUser($clientUser)->allows('view', $disabledSoftware));
        $this->assertFalse(Gate::forUser($clientUser)->allows('view', $otherSoftware));
        $this->assertFalse(Gate::forUser($clientUser)->allows('create', Software::class));
        $this->assertFalse(Gate::forUser($clientUser)->allows('update', $enabledSoftware));
        $this->assertTrue(Gate::forUser($developer)->allows('update', $disabledSoftware));
        $this->assertTrue(Gate::forUser($developer)->allows('toggle', $disabledSoftware));
    }

    public function test_report_access_permissions_require_view_reports_and_kiel_only_exports(): void
    {
        $this->seed(RoleSeeder::class);
        [, , $clientUser] = $this->clientWorkspace();
        $developer = $this->kielUser('developer');
        $noRoleUser = User::factory()->create();

        $this->actingAs($noRoleUser)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($clientUser)->get(route('reports.index'))->assertOk();
        $this->actingAs($clientUser)->get(route('reports.export.tickets'))->assertForbidden();
        $this->actingAs($developer)->get(route('reports.index'))->assertOk();
        $this->actingAs($developer)->get(route('reports.export.tickets'))->assertOk();
    }

    private function clientWorkspace(string $clientName = 'Client Co', string $email = 'client@example.test', string $role = 'client_user'): array
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
        $user->assignRole($role);

        return [$client, $software, $user];
    }

    private function kielUser(string $role): User
    {
        $user = User::factory()->create(['client_id' => null]);
        $user->assignRole($role);

        return $user;
    }
}
