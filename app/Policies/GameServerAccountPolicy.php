<?php

namespace App\Policies;

use App\Models\GameServerAccount;
use App\Models\User;

class GameServerAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin();
    }

    public function manageCollaborators(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->isOwnedBy($user);
    }

    public function renew(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'renew');
    }

    public function cancelSubscription(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'cancel_subscription');
    }

    public function panelLogin(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'panel_login');
    }

    public function backups(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'backups');
    }

    public function schedules(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'schedules');
    }

    public function databases(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'databases');
    }

    public function manageAutoRenew(User $user, GameServerAccount $gameServerAccount): bool
    {
        return $user->isAdmin()
            || $gameServerAccount->userCan($user, 'manage_auto_renew');
    }
}
