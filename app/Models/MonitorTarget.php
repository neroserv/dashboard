<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitorTarget extends Model
{
    public const TYPE_HTTP_URL = 'http_url';

    public const TYPE_TCP_PORT = 'tcp_port';

    protected $table = 'monitoring_targets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'config',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function isHttpUrl(): bool
    {
        return $this->type === self::TYPE_HTTP_URL;
    }

    public function isTcpPort(): bool
    {
        return $this->type === self::TYPE_TCP_PORT;
    }
}
