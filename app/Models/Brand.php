<?php

namespace App\Models;

use App\Services\BrandExtensionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BrandExtension> $brandExtensions
 */
class Brand extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'domains',
        'admin_domains',
        'is_default',
        'logo_url',
        'logo_collapsed_url',
        'app_icon_url',
        'auth_card_bg_url',
        'seo',
        'theme_colors',
        'features',
        'salutation',
        'mail_header',
        'mail_footer',
        'invoice_company',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'admin_domains' => 'array',
            'is_default' => 'boolean',
            'seo' => 'array',
            'theme_colors' => 'array',
            'features' => 'array',
            'invoice_company' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    /**
     * @return HasMany<BrandExtension>
     */
    public function brandExtensions(): HasMany
    {
        return $this->hasMany(BrandExtension::class);
    }

    /**
     * @return list<string>
     */
    public function installedExtensionKeys(): array
    {
        return BrandExtension::query()
            ->where('brand_id', $this->id)
            ->whereNotNull('installed_at')
            ->pluck('extension')
            ->values()
            ->all();
    }

    public function hasInstalledExtension(string $extension): bool
    {
        return BrandExtension::query()
            ->where('brand_id', $this->id)
            ->where('extension', $extension)
            ->whereNotNull('installed_at')
            ->exists();
    }

    /**
     * Normalize a domain entry (URL or hostname) to hostname only for comparison.
     */
    public static function normalizeDomainToHost(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('#^https?://#i', $value)) {
            $parsed = parse_url($value);

            return $parsed['host'] ?? $value;
        }

        return $value;
    }

    public static function resolveByAdminHost(string $host): ?self
    {
        $host = strtolower(trim($host));

        $brand = static::query()
            ->whereNotNull('admin_domains')
            ->whereJsonContains('admin_domains', $host)
            ->first();

        if ($brand !== null) {
            return $brand;
        }

        foreach (static::query()->whereNotNull('admin_domains')->get() as $b) {
            $adminDomains = $b->admin_domains ?? [];
            foreach ($adminDomains as $entry) {
                if (static::normalizeDomainToHost((string) $entry) === $host) {
                    return $b;
                }
            }
        }

        return null;
    }

    public static function resolveByHost(string $host): ?self
    {
        $host = strtolower(trim($host));

        return static::query()
            ->whereNotNull('domains')
            ->whereJsonContains('domains', $host)
            ->first();
    }

    public static function getDefault(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Default email greeting line based on salutation (e.g. "Guten Tag," for formal, "Hallo," for informal).
     */
    public function getDefaultGreeting(): string
    {
        return $this->salutation === 'informal' ? 'Hallo,' : 'Guten Tag,';
    }

    /**
     * @return array<string, bool|int>
     */
    public function getFeaturesArray(): array
    {
        /** @var BrandExtensionService $brandExtensions */
        $brandExtensions = app(BrandExtensionService::class);

        $features = $this->features ?? [];

        $defaults = [
            'webspace' => $this->hasInstalledExtension(BrandExtension::EXTENSION_PLESK),
            'domains_shop' => $this->hasInstalledExtension(BrandExtension::EXTENSION_SKRIME),
            'ai_tokens' => $brandExtensions->openAiApiKeyForBrand($this) !== null,
            'gaming' => false,
            'gameserver_cloud' => false,
            'teamspeak' => $this->hasInstalledExtension(BrandExtension::EXTENSION_TEAMSPEAK),
            'discord_notifications' => $brandExtensions->discordPortalConfigForBrand($this) !== null,
            'prepaid_balance' => false,
            'balance_topup' => false,
            'balance_period_months' => 1,
        ];

        if ($this->hasInstalledExtension(BrandExtension::EXTENSION_PTERODACTYL)) {
            $defaults['gaming'] = (bool) ($features['gaming'] ?? true);
            $defaults['gameserver_cloud'] = (bool) ($features['gameserver_cloud'] ?? false);
        }

        foreach (['prepaid_balance', 'balance_topup'] as $key) {
            if (array_key_exists($key, $features)) {
                $defaults[$key] = (bool) $features[$key];
            }
        }
        if (isset($features['balance_period_months']) && is_numeric($features['balance_period_months'])) {
            $defaults['balance_period_months'] = max(1, min(24, (int) $features['balance_period_months']));
        }

        return $defaults;
    }
}
