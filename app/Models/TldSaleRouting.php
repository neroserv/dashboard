<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TldSaleRouting extends Model
{
    protected $table = 'tld_sale_routing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'tld',
        'sale_registrar',
    ];

    /**
     * @return BelongsTo<Brand, TldSaleRouting>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
