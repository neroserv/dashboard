<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends ApiV1Controller
{
    /**
     * Public brand/company info for landing page (no sensitive data).
     */
    public function show(Request $request): JsonResponse
    {
        $brand = $this->resolveBrand($request);
        if (! $brand) {
            return response()->json(['data' => null], 404);
        }

        $features = $brand->getFeaturesArray();
        $seo = $brand->seo ?? [];

        return response()->json([
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'key' => $brand->key,
                'logo_url' => $brand->logo_url,
                'logo_collapsed_url' => $brand->logo_collapsed_url,
                'auth_card_bg_url' => $brand->auth_card_bg_url,
                'features' => $features,
                'seo' => [
                    'meta_description' => $seo['meta_description'] ?? null,
                    'og_title' => $seo['og_title'] ?? null,
                    'og_description' => $seo['og_description'] ?? null,
                    'og_image' => $seo['og_image'] ?? null,
                ],
            ],
        ]);
    }

    /**
     * Feature flags only (for landing page to show/hide product areas).
     */
    public function features(Request $request): JsonResponse
    {
        $brand = $this->resolveBrand($request);
        if (! $brand) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $brand->getFeaturesArray()]);
    }

    /**
     * Contact info for landing page (support URL, email from settings/brand).
     */
    public function contact(Request $request): JsonResponse
    {
        $brand = $this->resolveBrand($request);
        $supportUrl = Setting::get('support_url', '');
        $supportEmail = Setting::get('mail_reply_to_address', config('mail.from.address', ''));

        return response()->json([
            'data' => [
                'support_url' => $supportUrl,
                'support_email' => $supportEmail,
            ],
        ]);
    }
}
