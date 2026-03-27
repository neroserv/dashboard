<?php

namespace App\Models;

use App\Models\Concerns\HasProductShares;
use App\Services\HostingPlanOptionSurchargeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GameServerAccount extends Model
{
    use HasProductShares;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'hosting_plan_id',
        'gameserver_cloud_subscription_id',
        'hosting_server_id',
        'product_id',
        'name',
        'pterodactyl_server_id',
        'pterodactyl_user_id',
        'identifier',
        'credentials_encrypted',
        'status',
        'mollie_subscription_id',
        'mollie_payment_id',
        'current_period_ends_at',
        'cancel_at_period_end',
        'ends_at',
        'renewal_type',
        'auto_renew_with_balance',
        'option_values',
        'custom_monthly_price',
        'allocation',
        'allocation_manually_set',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_period_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'ends_at' => 'datetime',
            'auto_renew_with_balance' => 'boolean',
            'option_values' => 'array',
            'allocation' => 'array',
            'allocation_manually_set' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hostingPlan(): BelongsTo
    {
        return $this->belongsTo(HostingPlan::class);
    }

    public function hostingServer(): BelongsTo
    {
        return $this->belongsTo(HostingServer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function gameserverCloudSubscription(): BelongsTo
    {
        return $this->belongsTo(GameserverCloudSubscription::class);
    }

    /**
     * Whether this account is part of a Gameserver Cloud subscription (not a classic HostingPlan).
     */
    public function isCloudAccount(): bool
    {
        return $this->gameserver_cloud_subscription_id !== null;
    }

    /**
     * Effective monthly renewal amount (custom price, or plan price + option surcharge).
     * Returns 0 for cloud accounts (subscription is billed separately).
     */
    public function getMonthlyRenewalAmount(): float
    {
        if ($this->isCloudAccount()) {
            return 0.0;
        }
        $custom = (float) ($this->custom_monthly_price ?? 0);
        if ($custom > 0) {
            return round($custom, 2);
        }
        $plan = $this->hostingPlan;
        if (! $plan || ! $plan->is_active) {
            return 0.0;
        }
        $base = (float) ($plan->price ?? 0);
        $optionChoices = is_array($this->option_values) ? $this->option_values : [];
        $surcharge = app(HostingPlanOptionSurchargeService::class)->computeSurcharge($plan, $optionChoices);

        return round($base + $surcharge, 2);
    }

    /**
     * True if the account is suspended or period has ended (no control, only renew).
     * For cloud accounts, uses the subscription's status and period.
     */
    public function isSuspendedOrExpired(): bool
    {
        if ($this->status === 'suspended') {
            return true;
        }
        if ($this->isCloudAccount()) {
            $sub = $this->gameserverCloudSubscription;
            if ($sub) {
                return $sub->isSuspendedOrExpired();
            }

            return false;
        }

        return $this->current_period_ends_at !== null && $this->current_period_ends_at->isPast();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeViewableBy(Builder $query, User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->where($this->getOwnerKeyName(), $user->id)
                ->orWhereHas('productShares', function ($q2) use ($user) {
                    $q2->where('user_id', $user->id)
                        ->whereJsonContains('permissions', 'view');
                })
                ->orWhereHas('gameserverCloudSubscription', function ($subQ) use ($user) {
                    $subQ->viewableBy($user);
                });
        });
    }

    /**
     * Whether the given user can perform the given permission (owner has all, else from share).
     * For Gameserver Cloud, permissions are derived from the parent subscription when set.
     */
    public function userCan(User $user, string $permission): bool
    {
        if (! $this->isCloudAccount()) {
            if ($this->isOwnedBy($user)) {
                return true;
            }

            return $this->hasSharedAccess($user, $permission);
        }

        $subscription = $this->gameserverCloudSubscription;
        if ($subscription === null) {
            if ($this->isOwnedBy($user)) {
                return true;
            }

            return $this->hasSharedAccess($user, $permission);
        }

        if ($subscription->isOwnedBy($user)) {
            return true;
        }

        if ($permission === 'view') {
            return $subscription->hasSharedAccess($user, 'view');
        }

        if (in_array($permission, ['panel_login', 'backups', 'schedules', 'databases', 'renew'], true)) {
            return $subscription->hasSharedAccess($user, 'manage_servers');
        }

        if ($permission === 'cancel_subscription') {
            return $subscription->hasSharedAccess($user, 'cancel_subscription');
        }

        if ($permission === 'manage_auto_renew') {
            return $subscription->hasSharedAccess($user, 'manage_auto_renew');
        }

        return $this->hasSharedAccess($user, $permission);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (GameServerAccount $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
