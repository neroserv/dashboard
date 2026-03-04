<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

abstract class ApiV1Controller extends Controller
{
    protected function resolveBrand(Request $request): ?Brand
    {
        $brandId = $request->header('X-Brand-Id') ?? $request->query('brand_id');
        if ($brandId !== null && $brandId !== '') {
            $brand = Brand::query()->find((int) $brandId);

            return $brand ?? $this->defaultBrand($request);
        }

        return $request->attributes->get('current_brand') ?? $this->defaultBrand($request);
    }

    protected function defaultBrand(Request $request): ?Brand
    {
        return Brand::getDefault();
    }
}
