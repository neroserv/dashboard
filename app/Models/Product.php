<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Product extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'name',
        'key',
        'type',
        'productable_type',
        'productable_id',
        'stripe_product_id',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The concrete offer (HostingPlan) this product represents.
     *
     * @return MorphTo<HostingPlan, Product>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }
}
