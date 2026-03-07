<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameserverCloudSubscription extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'gameserver_cloud_plan_id',
        'mollie_subscription_id',
        'mollie_payment_id',
        'current_period_ends_at',
        'cancel_at_period_end',
        'ends_at',
        'status',
        'auto_renew_with_balance',
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameserverCloudPlan(): BelongsTo
    {
        return $this->belongsTo(GameserverCloudPlan::class);
    }

    /**
     * @return HasMany<GameServerAccount, $this>
     */
    public function gameServerAccounts(): HasMany
    {
        return $this->hasMany(GameServerAccount::class);
    }

    public function getUsedCpu(): int
    {
        return (int) $this->gameServerAccounts()->get()->sum(function (GameServerAccount $account) {
            $a = $account->allocation;
            if (! is_array($a)) {
                return 0;
            }

            return (int) ($a['cpu'] ?? 0);
        });
    }

    public function getUsedMemoryMb(): int
    {
        return (int) $this->gameServerAccounts()->get()->sum(function (GameServerAccount $account) {
            $a = $account->allocation;
            if (! is_array($a)) {
                return 0;
            }

            return (int) ($a['memory_mb'] ?? 0);
        });
    }

    public function getUsedDiskMb(): int
    {
        return (int) $this->gameServerAccounts()->get()->sum(function (GameServerAccount $account) {
            $a = $account->allocation;
            if (! is_array($a)) {
                return 0;
            }

            return (int) ($a['disk_mb'] ?? 0);
        });
    }

    public function getRemainingCpu(): int
    {
        $config = $this->gameserverCloudPlan->config ?? [];
        $max = (int) ($config['max_cpu'] ?? 0);

        return max(0, $max - $this->getUsedCpu());
    }

    public function getRemainingMemoryMb(): int
    {
        $config = $this->gameserverCloudPlan->config ?? [];
        $max = (int) ($config['max_memory_mb'] ?? 0);

        return max(0, $max - $this->getUsedMemoryMb());
    }

    public function getRemainingDiskMb(): int
    {
        $config = $this->gameserverCloudPlan->config ?? [];
        $maxGb = (int) ($config['max_disk_gb'] ?? 0);
        $maxMb = $maxGb * 1024;

        return max(0, $maxMb - $this->getUsedDiskMb());
    }

    public function isSuspendedOrExpired(): bool
    {
        if ($this->status === 'suspended' || $this->status === 'cancelled') {
            return true;
        }

        return $this->current_period_ends_at !== null && $this->current_period_ends_at->isPast();
    }
}
