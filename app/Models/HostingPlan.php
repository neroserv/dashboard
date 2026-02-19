<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class HostingPlan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'plesk_package_name',
        'disk_gb',
        'traffic_gb',
        'domains',
        'subdomains',
        'mailboxes',
        'databases',
        'price',
        'stripe_price_id',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Product entry for this plan (Webspace sellable).
     *
     * @return MorphOne<Product>
     */
    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }

    /**
     * @return HasMany<WebspaceAccount>
     */
    public function webspaceAccounts(): HasMany
    {
        return $this->hasMany(WebspaceAccount::class);
    }
}
