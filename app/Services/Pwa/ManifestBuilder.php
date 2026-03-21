<?php

namespace App\Services\Pwa;

use App\Models\Brand;
use Illuminate\Http\Request;

final class ManifestBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, ?Brand $brand): array
    {
        $name = $brand?->name ?? config('app.name', 'PVA');
        $shortName = $this->shortName($brand, $name);
        $iconUrl = BrandMediaUrl::primaryLogoAbsolute($brand) ?? url('/favicon.svg');

        $themeColor = '#2563eb';
        $backgroundColor = '#ffffff';
        $colors = $brand?->theme_colors;
        if (is_array($colors)) {
            if (! empty($colors['primary']) && is_string($colors['primary'])) {
                $themeColor = $colors['primary'];
            }
        }

        $icons = [
            [
                'src' => $iconUrl,
                'sizes' => '192x192',
                'type' => $this->guessMime($iconUrl),
                'purpose' => 'any',
            ],
            [
                'src' => $iconUrl,
                'sizes' => '512x512',
                'type' => $this->guessMime($iconUrl),
                'purpose' => 'any',
            ],
        ];

        $startUrl = url('/');

        return [
            'id' => $startUrl,
            'name' => $name,
            'short_name' => $shortName,
            'description' => is_array($brand?->seo) ? (string) ($brand->seo['meta_description'] ?? '') : '',
            'start_url' => $startUrl,
            'scope' => $startUrl,
            'display' => 'standalone',
            'background_color' => $backgroundColor,
            'theme_color' => $themeColor,
            'icons' => $icons,
            'lang' => str_replace('_', '-', app()->getLocale()),
        ];
    }

    private function shortName(?Brand $brand, string $name): string
    {
        if ($brand !== null && is_array($brand->seo) && ! empty($brand->seo['og_title']) && is_string($brand->seo['og_title'])) {
            $candidate = trim($brand->seo['og_title']);
            if ($candidate !== '') {
                return mb_substr($candidate, 0, 12);
            }
        }

        return mb_substr($name, 0, 12);
    }

    private function guessMime(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return match (true) {
            str_ends_with(strtolower($path), '.svg') => 'image/svg+xml',
            str_ends_with(strtolower($path), '.png') => 'image/png',
            str_ends_with(strtolower($path), '.webp') => 'image/webp',
            str_ends_with(strtolower($path), '.jpg'),
            str_ends_with(strtolower($path), '.jpeg') => 'image/jpeg',
            default => 'image/png',
        };
    }
}
