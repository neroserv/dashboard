<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'subject',
        'body_html',
        'snippet',
        'notification_type',
        'sent_at',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
