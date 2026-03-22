<?php

namespace App\Services\Pwa;

use App\Models\Brand;
use Illuminate\Http\Request;

final class BrandMediaUrl
{
    /**
     * Absolute URL for a stored or site path. When a request is given, the current host is used (multi-domain);
     * otherwise {@see url()} / {@see config('app.url')} is used (CLI, queues, mail).
     */
    public static function absoluteUrlForPwa(?string $url, ?Request $request = null): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $u = trim($url);
        if (preg_match('#^https?://#i', $u)) {
            return $u;
        }

        $origin = self::requestOrigin($request);

        if (str_starts_with($u, '/')) {
            return $origin !== null ? $origin.$u : url($u);
        }

        $path = '/storage/'.ltrim($u, '/');

        return $origin !== null ? $origin.$path : url($path);
    }

    private static function requestOrigin(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    public static function primaryLogoAbsolute(?Brand $brand, ?Request $request = null): ?string
    {
        if ($brand === null) {
            return null;
        }
        $raw = $brand->logo_url;
        if ($raw === null || $raw === '') {
            $raw = $brand->logo_collapsed_url;
        }

        return self::absoluteUrlForPwa($raw !== null ? (string) $raw : null, $request);
    }

    /**
     * Icon for PWA manifest, apple-touch-icon and push: optional square app icon, else main logo.
     */
    public static function appIconAbsolute(?Brand $brand, ?Request $request = null): ?string
    {
        if ($brand === null) {
            return null;
        }
        $raw = $brand->app_icon_url;
        if ($raw !== null && trim((string) $raw) !== '') {
            return self::absoluteUrlForPwa(trim((string) $raw), $request);
        }

        return self::primaryLogoAbsolute($brand, $request);
    }
}
