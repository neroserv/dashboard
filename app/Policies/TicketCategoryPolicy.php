<?php

namespace App\Policies;

use App\Models\TicketCategory;
use App\Models\User;

class TicketCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('*') || $user->hasPermission('admin.tickets');
    }

    public function view(User $user, TicketCategory $ticketCategory): bool
    {
        return $user->isAdmin() || $user->hasPermission('*') || $user->hasPermission('admin.tickets');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('*') || $user->hasPermission('admin.tickets');
    }

    public function update(User $user, TicketCategory $ticketCategory): bool
    {
        return $user->isAdmin() || $user->hasPermission('*') || $user->hasPermission('admin.tickets');
    }

    public function delete(User $user, TicketCategory $ticketCategory): bool
    {
        return $user->isAdmin() || $user->hasPermission('*') || $user->hasPermission('admin.tickets');
    }

    public function restore(User $user, TicketCategory $ticketCategory): bool
    {
        return $user->isAdmin() || $user->hasPermission('*') || $user->hasPermission('admin.tickets');
    }

    public function forceDelete(User $user, TicketCategory $ticketCategory): bool
    {
        return $user->isAdmin() || $user->hasPermission('*') || $user->hasPermission('admin.tickets');
    }
}
