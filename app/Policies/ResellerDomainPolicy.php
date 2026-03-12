<?php

namespace App\Policies;

use App\Models\ResellerDomain;
use App\Models\User;

class ResellerDomainPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->isOwnedBy($user)
            || $resellerDomain->hasSharedAccess($user, 'contact')
            || $resellerDomain->hasSharedAccess($user, 'whois')
            || $resellerDomain->hasSharedAccess($user, 'nameserver')
            || $resellerDomain->hasSharedAccess($user, 'dns')
            || $resellerDomain->hasSharedAccess($user, 'dnssec')
            || $resellerDomain->hasSharedAccess($user, 'renew')
            || $resellerDomain->hasSharedAccess($user, 'autorenew');
    }

    public function delete(User $user, ResellerDomain $resellerDomain): bool
    {
        return false;
    }

    public function restore(User $user, ResellerDomain $resellerDomain): bool
    {
        return false;
    }

    public function forceDelete(User $user, ResellerDomain $resellerDomain): bool
    {
        return false;
    }

    public function manageCollaborators(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->isOwnedBy($user);
    }

    public function authcode(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'authcode');
    }

    public function contact(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'contact');
    }

    public function whois(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'whois');
    }

    public function nameserver(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'nameserver');
    }

    public function dns(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'dns');
    }

    public function dnssec(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'dnssec');
    }

    public function renew(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'renew');
    }

    public function autorenew(User $user, ResellerDomain $resellerDomain): bool
    {
        return $resellerDomain->userCan($user, 'autorenew');
    }
}
