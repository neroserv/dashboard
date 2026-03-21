<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $service_type
 * @property int $service_id
 */
class TicketService extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'service_type',
        'service_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Human-readable label for the linked product/service (domains, webspace, gaming, TeamSpeak).
     */
    public function resolveLabel(): string
    {
        return match ($this->service_type) {
            'reseller_domain' => ResellerDomain::query()->where('id', $this->service_id)->value('domain') ?? "#{$this->service_id}",
            'webspace_account' => WebspaceAccount::query()->where('id', $this->service_id)->value('domain') ?? "#{$this->service_id}",
            'game_server_account' => GameServerAccount::query()->where('id', $this->service_id)->value('name') ?? "#{$this->service_id}",
            'teamspeak_server_account' => TeamSpeakServerAccount::query()->where('id', $this->service_id)->value('name') ?? "#{$this->service_id}",
            default => "#{$this->service_id}",
        };
    }
}
