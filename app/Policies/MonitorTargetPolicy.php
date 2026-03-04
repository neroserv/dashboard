<?php

namespace App\Policies;

use App\Models\MonitorTarget;
use App\Models\User;

class MonitorTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, MonitorTarget $monitorTarget): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MonitorTarget $monitorTarget): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, MonitorTarget $monitorTarget): bool
    {
        return $user->isAdmin();
    }
}
