<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'ticket_category_id',
        'ticket_priority_id',
        'subject',
        'status',
        'assigned_to',
        'due_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticketCategory(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    public function ticketPriority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class);
    }

    /**
     * Admin user assigned to this ticket.
     *
     * @return BelongsTo<User>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<TicketMessage>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * Newest message by creation time (ties broken by id).
     *
     * @return HasOne<TicketMessage>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany(['created_at', 'id']);
    }

    /**
     * @return BelongsToMany<Tag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ticket_tag');
    }

    /**
     * @return HasMany<TicketTimeLog>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TicketTimeLog::class);
    }

    /**
     * @return HasMany<TicketTodo>
     */
    public function todos(): HasMany
    {
        return $this->hasMany(TicketTodo::class);
    }

    /**
     * Affected services (domains, webspaces, gameserver, teamspeak, sites) linked to this ticket.
     *
     * @return HasMany<TicketService>
     */
    public function ticketServices(): HasMany
    {
        return $this->hasMany(TicketService::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if ($model->status === 'closed' && $model->getAttribute('closed_at') === null) {
                $model->closed_at = now();
            }
        });

        static::updating(function (Ticket $ticket): void {
            if (! $ticket->isDirty('status')) {
                return;
            }
            if ($ticket->status === 'closed') {
                $ticket->closed_at = now();
            } else {
                $ticket->closed_at = null;
            }
        });
    }
}
