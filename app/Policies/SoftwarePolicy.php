<?php

namespace App\Policies;

use App\Models\Software;
use App\Models\User;

class SoftwarePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view software') || $user->isSuperAdmin();
    }

    public function view(User $user, Software $software): bool
    {
        if ($user->isSuperAdmin() || $user->can('manage software')) {
            return true;
        }

        return $software->is_enabled && $user->canAccessClient($software->client);
    }

    public function create(User $user): bool
    {
        return $user->can('manage software') || $user->isSuperAdmin();
    }

    public function update(User $user, Software $software): bool
    {
        return $user->can('manage software') || $user->isSuperAdmin();
    }

    public function toggle(User $user, Software $software): bool
    {
        return $this->update($user, $software);
    }
}
