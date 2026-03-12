<?php

namespace App\Policies;

use App\Models\TeamSpeakServerAccount;
use App\Models\User;

class TeamSpeakServerAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin()
            || $teamSpeakServerAccount->userCan($user, 'view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function manageCollaborators(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin()
            || $teamSpeakServerAccount->isOwnedBy($user);
    }

    public function renew(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin()
            || $teamSpeakServerAccount->userCan($user, 'renew');
    }

    public function cancelSubscription(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin()
            || $teamSpeakServerAccount->userCan($user, 'cancel_subscription');
    }

    public function manageTokens(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin()
            || $teamSpeakServerAccount->userCan($user, 'manage_tokens');
    }

    public function manageAutoRenew(User $user, TeamSpeakServerAccount $teamSpeakServerAccount): bool
    {
        return $user->isAdmin()
            || $teamSpeakServerAccount->userCan($user, 'manage_auto_renew');
    }
}
