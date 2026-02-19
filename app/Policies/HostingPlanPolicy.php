<?php

namespace App\Policies;

use App\Models\HostingPlan;
use App\Models\User;

class HostingPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, HostingPlan $hostingPlan): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, HostingPlan $hostingPlan): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, HostingPlan $hostingPlan): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, HostingPlan $hostingPlan): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, HostingPlan $hostingPlan): bool
    {
        return $user->isAdmin();
    }
}
