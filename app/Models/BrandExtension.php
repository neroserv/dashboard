<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandExtension extends Model
{
    public const EXTENSION_INVOICE_NINJA = 'invoice_ninja';

    public const EXTENSION_PLESK = 'plesk';

    public const EXTENSION_PTERODACTYL = 'pterodactyl';

    public const EXTENSION_SKRIME = 'skrime';

    public const EXTENSION_TEAMSPEAK = 'teamspeak';

    /**
     * @var list<string>
     */
    public static function allExtensionKeys(): array
    {
        return [
            self::EXTENSION_INVOICE_NINJA,
            self::EXTENSION_PLESK,
            self::EXTENSION_PTERODACTYL,
            self::EXTENSION_SKRIME,
            self::EXTENSION_TEAMSPEAK,
        ];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'extension',
        'installed_at',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'settings' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Brand, BrandExtension>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isInstalled(): bool
    {
        return $this->installed_at !== null;
    }
}
