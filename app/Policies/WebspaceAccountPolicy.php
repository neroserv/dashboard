<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebspaceAccount;

class WebspaceAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin() || $user->id === $webspaceAccount->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin();
    }
}
