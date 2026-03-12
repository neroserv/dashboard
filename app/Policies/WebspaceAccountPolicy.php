<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebspaceAccount;

class WebspaceAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin()
            || $webspaceAccount->userCan($user, 'view');
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

    public function manageCollaborators(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin()
            || $webspaceAccount->isOwnedBy($user);
    }

    public function renew(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin()
            || $webspaceAccount->userCan($user, 'renew');
    }

    public function cancelSubscription(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin()
            || $webspaceAccount->userCan($user, 'cancel_subscription');
    }

    public function pleskLogin(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin()
            || $webspaceAccount->userCan($user, 'plesk_login');
    }

    public function manageAutoRenew(User $user, WebspaceAccount $webspaceAccount): bool
    {
        return $user->isAdmin()
            || $webspaceAccount->userCan($user, 'manage_auto_renew');
    }
}
