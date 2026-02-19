<?php

namespace App\Policies;

use App\Models\HostingServer;
use App\Models\User;

class HostingServerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, HostingServer $hostingServer): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, HostingServer $hostingServer): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, HostingServer $hostingServer): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, HostingServer $hostingServer): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, HostingServer $hostingServer): bool
    {
        return $user->isAdmin();
    }
}
