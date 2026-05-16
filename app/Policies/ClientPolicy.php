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

    public function create(User $user): bool
    {
        return $user->can('manage clients') || $user->isSuperAdmin();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('manage clients') || $user->isSuperAdmin();
    }

    public function disable(User $user, Client $client): bool
    {
        return ($user->can('manage clients') || $user->isSuperAdmin()) && $client->isActive();
    }

    public function manage(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
