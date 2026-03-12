<?php

namespace App\Policies;

use App\Models\GameserverCloudSubscription;
use App\Models\User;

class GameserverCloudSubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin()
            || $subscription->userCan($user, 'view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function manageCollaborators(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin()
            || $subscription->isOwnedBy($user);
    }

    public function cancelSubscription(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin()
            || $subscription->userCan($user, 'cancel_subscription');
    }

    public function createServer(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin()
            || $subscription->userCan($user, 'create_server');
    }

    public function manageServers(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin()
            || $subscription->userCan($user, 'manage_servers');
    }

    public function manageAutoRenew(User $user, GameserverCloudSubscription $subscription): bool
    {
        return $user->isAdmin()
            || $subscription->userCan($user, 'manage_auto_renew');
    }
}
