<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'domains',
        'is_default',
        'logo_url',
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
            'is_default' => 'boolean',
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
        $defaults = [
            'sites_editor' => true,
            'webspace' => true,
            'domains_shop' => true,
            'ai_tokens' => true,
            'gaming' => false,
            'prepaid_balance' => false,
            'balance_topup' => false,
            'balance_period_months' => 1,
        ];
        $features = $this->features ?? [];
        $boolKeys = ['sites_editor', 'webspace', 'domains_shop', 'ai_tokens', 'gaming', 'prepaid_balance', 'balance_topup'];
        foreach ($boolKeys as $key) {
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
