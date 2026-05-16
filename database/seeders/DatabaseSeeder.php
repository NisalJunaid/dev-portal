<?php

namespace Database\Seeders;

use App\Models\Client;
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
            ['slug' => 'acme-health'],
            ['name' => 'Acme Health', 'status' => 'active']
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
