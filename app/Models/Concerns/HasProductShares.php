<?php

namespace App\Models\Concerns;

use App\Models\ProductInvitation;
use App\Models\ProductShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasProductShares
{
    /**
     * Scope: only shareables the user can view (owner or shared with 'view').
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeViewableBy($query, \App\Models\User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->where($this->getOwnerKeyName(), $user->id)
                ->orWhereHas('productShares', function ($q2) use ($user) {
                    $q2->where('user_id', $user->id)
                        ->whereJsonContains('permissions', 'view');
                });
        });
    }

    /**
     * Name of the owner foreign key (user_id on all shareables).
     */
    protected function getOwnerKeyName(): string
    {
        return 'user_id';
    }

    /**
     * Users who have been granted access to this shareable (accepted invitations).
     *
     * @return MorphMany<ProductShare>
     */
    public function productShares(): MorphMany
    {
        return $this->morphMany(ProductShare::class, 'shareable');
    }

    /**
     * Pending invitations for this shareable.
     *
     * @return MorphMany<ProductInvitation>
     */
    public function productInvitations(): MorphMany
    {
        return $this->morphMany(ProductInvitation::class, 'shareable');
    }

    /**
     * Whether the given user has shared access with the given permission.
     */
    public function hasSharedAccess(User $user, string $permission): bool
    {
        $share = $this->productShares()
            ->where('user_id', $user->id)
            ->first();

        if (! $share) {
            return false;
        }

        return $share->hasPermission($permission);
    }

    /**
     * Get the product share for the given user, if any.
     */
    public function getShareForUser(User $user): ?ProductShare
    {
        return $this->productShares()
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Whether the given user is the owner of this shareable.
     * Must be implemented by the model (owner relation or user_id).
     */
    abstract public function isOwnedBy(User $user): bool;

    /**
     * Whether the given user can perform the given permission (owner has all, else from share).
     */
    public function userCan(User $user, string $permission): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->hasSharedAccess($user, $permission);
    }
}
