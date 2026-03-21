<?php

namespace App\Services\Pwa;

use App\Models\Brand;

final class BrandMediaUrl
{
    public static function absoluteUrlForPwa(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $u = trim($url);
        if (preg_match('#^https?://#i', $u)) {
            return $u;
        }
        if (str_starts_with($u, '/')) {
            return url($u);
        }

        return url('/storage/'.ltrim($u, '/'));
    }

    public static function primaryLogoAbsolute(?Brand $brand): ?string
    {
        if ($brand === null) {
            return null;
        }
        $raw = $brand->logo_url;
        if ($raw === null || $raw === '') {
            $raw = $brand->logo_collapsed_url;
        }

        return self::absoluteUrlForPwa($raw !== null ? (string) $raw : null);
    }

    /**
     * Icon for PWA manifest, apple-touch-icon and push: optional square app icon, else main logo.
     */
    public static function appIconAbsolute(?Brand $brand): ?string
    {
        if ($brand === null) {
            return null;
        }
        $raw = $brand->app_icon_url;
        if ($raw !== null && trim((string) $raw) !== '') {
            return self::absoluteUrlForPwa(trim((string) $raw));
        }

        return self::primaryLogoAbsolute($brand);
    }
}
