<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameserverCloudPlan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'hosting_server_id',
        'name',
        'price',
        'config',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function hostingServer(): BelongsTo
    {
        return $this->belongsTo(HostingServer::class);
    }

    /**
     * @return HasMany<GameserverCloudSubscription, $this>
     */
    public function gameserverCloudSubscriptions(): HasMany
    {
        return $this->hasMany(GameserverCloudSubscription::class);
    }
}
