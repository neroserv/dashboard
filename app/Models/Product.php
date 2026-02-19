<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Product extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
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
     * The concrete offer (Template or HostingPlan) this product represents.
     *
     * @return MorphTo<Template|HostingPlan, Product>
     */
    public function productable(): MorphTo
    {
        return $this->morphTo();
    }
}
