<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\Pwa\ManifestBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PwaManifestController extends Controller
{
    public function __invoke(Request $request, ManifestBuilder $manifestBuilder): JsonResponse
    {
        $brand = $request->attributes->get('current_brand') ?? Brand::getDefault();

        return response()->json($manifestBuilder->build($request, $brand))
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
