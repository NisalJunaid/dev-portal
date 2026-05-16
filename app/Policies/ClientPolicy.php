<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view clients') || $user->isSuperAdmin();
    }

    public function view(User $user, Client $client): bool
    {
        return $user->canAccessClient($client);
    }

    public function manage(User $user, Client $client): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('kiel_manager') || ($user->hasRole('client_admin') && $user->client_id === $client->id);
    }
}
