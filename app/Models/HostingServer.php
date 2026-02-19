<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostingServer extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'hostname',
        'port',
        'use_ssl',
        'ip_address',
        'api_token',
        'api_username',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'use_ssl' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function usesRestApi(): bool
    {
        return ! empty($this->api_username);
    }

    /**
     * @return HasMany<WebspaceAccount>
     */
    public function webspaceAccounts(): HasMany
    {
        return $this->hasMany(WebspaceAccount::class);
    }
}
