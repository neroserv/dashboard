<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ProductInvitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'email',
        'token',
        'permissions',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Human-readable label for the shareable type (e.g. "Webspace", "Game Server").
     */
    public function getShareableTypeLabel(): string
    {
        $map = [
            \App\Models\WebspaceAccount::class => 'Webspace',
            \App\Models\TeamSpeakServerAccount::class => 'TeamSpeak-Server',
            \App\Models\GameServerAccount::class => 'Game Server',
            \App\Models\GameserverCloudSubscription::class => 'Gameserver Cloud',
            \App\Models\ResellerDomain::class => 'Domain',
        ];

        return $map[$this->shareable_type] ?? 'Produkt';
    }

    /**
     * Display name for the shareable (e.g. domain, server name).
     */
    public function getShareableDisplayName(): string
    {
        $shareable = $this->shareable;
        if (! $shareable) {
            return $this->getShareableTypeLabel();
        }
        if ($shareable instanceof \App\Models\WebspaceAccount) {
            return $shareable->domain ?? 'Webspace';
        }
        if ($shareable instanceof \App\Models\ResellerDomain) {
            return $shareable->domain ?? 'Domain';
        }
        if ($shareable instanceof \App\Models\GameServerAccount) {
            return $shareable->name ?: $shareable->identifier ?: 'Game Server';
        }
        if ($shareable instanceof \App\Models\TeamSpeakServerAccount) {
            return $shareable->name ?: 'TeamSpeak-Server';
        }
        if ($shareable instanceof \App\Models\GameserverCloudSubscription) {
            $plan = $shareable->gameserverCloudPlan;

            return $plan?->name ?: 'Gameserver Cloud';
        }

        return $this->getShareableTypeLabel();
    }

    /**
     * Comma-separated human-readable permission labels (e.g. for emails).
     */
    public function getPermissionLabelsForDisplay(): string
    {
        $labels = config('product-share-permissions.permission_labels', []);
        $perms = $this->permissions ?? [];

        $translated = array_map(fn (string $p) => $labels[$p] ?? $p, $perms);

        return implode(', ', $translated);
    }

    /**
     * URL to the shareable's show page (for redirect after accept).
     */
    public function getShareableShowUrl(): ?string
    {
        $shareable = $this->shareable;
        if (! $shareable) {
            return null;
        }
        if ($shareable instanceof \App\Models\WebspaceAccount) {
            return route('webspace-accounts.show', $shareable);
        }
        if ($shareable instanceof \App\Models\ResellerDomain) {
            return route('domains.manage.show', $shareable);
        }
        if ($shareable instanceof \App\Models\GameServerAccount) {
            return route('gaming-accounts.show', $shareable);
        }
        if ($shareable instanceof \App\Models\TeamSpeakServerAccount) {
            return route('teamspeak-accounts.show', $shareable);
        }
        if ($shareable instanceof \App\Models\GameserverCloudSubscription) {
            return route('gaming.cloud.subscriptions.show', $shareable);
        }

        return null;
    }
}
