<?php

namespace App\Policies;

use App\Models\GameserverCloudPlan;
use App\Models\User;

class GameserverCloudPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, GameserverCloudPlan $gameserverCloudPlan): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, GameserverCloudPlan $gameserverCloudPlan): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GameserverCloudPlan $gameserverCloudPlan): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, GameserverCloudPlan $gameserverCloudPlan): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, GameserverCloudPlan $gameserverCloudPlan): bool
    {
        return $user->isAdmin();
    }
}
