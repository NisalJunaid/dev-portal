<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Software;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $sampleClient = Client::firstOrCreate(
            ['name' => 'Acme Health'],
            [
                'description' => 'Demo healthcare client workspace for seeded portal users.',
                'status' => Client::STATUS_ACTIVE,
            ]
        );

        Software::firstOrCreate(
            ['client_id' => $sampleClient->id, 'name' => 'Patient Portal'],
            [
                'description' => 'Client-facing product used by Acme Health patients and staff.',
                'is_enabled' => true,
            ]
        );

        Software::firstOrCreate(
            ['client_id' => $sampleClient->id, 'name' => 'Legacy Billing Console'],
            [
                'description' => 'Disabled demo product hidden from client users.',
                'is_enabled' => false,
            ]
        );

        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@kiel.test', 'role' => 'super_admin', 'client_id' => null],
            ['name' => 'Kiel Manager', 'email' => 'manager@kiel.test', 'role' => 'kiel_manager', 'client_id' => null],
            ['name' => 'Kiel Developer', 'email' => 'developer@kiel.test', 'role' => 'developer', 'client_id' => null],
            ['name' => 'Acme Client Admin', 'email' => 'admin@acme.test', 'role' => 'client_admin', 'client_id' => $sampleClient->id],
            ['name' => 'Acme Client User', 'email' => 'user@acme.test', 'role' => 'client_user', 'client_id' => $sampleClient->id],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'client_id' => $userData['client_id'],
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
