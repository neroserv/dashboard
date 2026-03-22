<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandExtension;

class BrandExtensionService
{
    /**
     * @return list<string>
     */
    public function installedKeys(Brand $brand): array
    {
        return BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->whereNotNull('installed_at')
            ->pluck('extension')
            ->values()
            ->all();
    }

    public function isInstalled(Brand $brand, string $extension): bool
    {
        return BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', $extension)
            ->whereNotNull('installed_at')
            ->exists();
    }

    /**
     * Panel types for hosting server / plan forms (with fallback Plesk if none).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function allowedPanelTypes(Brand $brand): array
    {
        $types = [];
        if ($this->isInstalled($brand, BrandExtension::EXTENSION_PLESK)) {
            $types[] = ['value' => 'plesk', 'label' => 'Plesk'];
        }
        if ($this->isInstalled($brand, BrandExtension::EXTENSION_PTERODACTYL)) {
            $types[] = ['value' => 'pterodactyl', 'label' => 'Pterodactyl'];
        }
        if ($this->isInstalled($brand, BrandExtension::EXTENSION_TEAMSPEAK)) {
            $types[] = ['value' => 'teamspeak', 'label' => 'TeamSpeak'];
        }

        if ($types === []) {
            $types[] = ['value' => 'plesk', 'label' => 'Plesk'];
        }

        return $types;
    }

    /**
     * Same as allowedPanelTypes but when editing an existing server, keep its panel type selectable.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function allowedPanelTypesForHostingServerEdit(Brand $brand, string $currentPanelType): array
    {
        $types = $this->allowedPanelTypes($brand);
        $values = array_column($types, 'value');
        if (! in_array($currentPanelType, $values, true)) {
            $label = match ($currentPanelType) {
                'pterodactyl' => 'Pterodactyl',
                'teamspeak' => 'TeamSpeak',
                default => 'Plesk',
            };
            $types[] = ['value' => $currentPanelType, 'label' => $label];
        }

        return $types;
    }

    /**
     * Merged Skrime settings: extension settings override config/skrime.php and .env.
     *
     * @return array{
     *     base_url: string,
     *     api_key: ?string,
     *     timeout: int,
     *     default_nameservers: array<int, string>,
     *     margin_type: string,
     *     margin_value: float,
     *     tlds: array<string, mixed>
     * }
     */
    public function skrimeConfigForBrand(?Brand $brand): array
    {
        $base = [
            'base_url' => (string) config('skrime.base_url'),
            'api_key' => config('skrime.api_key') ? (string) config('skrime.api_key') : null,
            'timeout' => (int) config('skrime.timeout', 30),
            'default_nameservers' => config('skrime.default_nameservers', []),
            'margin_type' => (string) config('skrime.margin_type', 'fixed'),
            'margin_value' => (float) config('skrime.margin_value', 0),
            'tlds' => config('skrime.tlds', []),
        ];

        if ($brand === null) {
            return $base;
        }

        $ext = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_SKRIME)
            ->whereNotNull('installed_at')
            ->first();

        if ($ext === null || ! is_array($ext->settings)) {
            return $base;
        }

        $s = $ext->settings;
        if (! empty($s['api_url'])) {
            $base['base_url'] = (string) $s['api_url'];
        }
        if (! empty($s['api_token'])) {
            $base['api_key'] = (string) $s['api_token'];
        }
        if (isset($s['timeout']) && is_numeric($s['timeout'])) {
            $base['timeout'] = max(1, min(300, (int) $s['timeout']));
        }
        if (! empty($s['margin_type']) && in_array($s['margin_type'], ['fixed', 'percent'], true)) {
            $base['margin_type'] = (string) $s['margin_type'];
        }
        if (isset($s['margin_value']) && is_numeric($s['margin_value'])) {
            $base['margin_value'] = (float) $s['margin_value'];
        }
        if (isset($s['tlds']) && is_array($s['tlds'])) {
            $base['tlds'] = $s['tlds'];
        }

        return $base;
    }

    /**
     * Invoice Ninja settings for a brand when the extension is installed and configured.
     *
     * @return array{base_url: string, api_token: string, timeout: int}|null
     */
    public function invoiceNinjaConfigForBrand(?Brand $brand): ?array
    {
        if ($brand === null) {
            return null;
        }

        $ext = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_INVOICE_NINJA)
            ->whereNotNull('installed_at')
            ->first();

        if ($ext === null || ! is_array($ext->settings)) {
            return null;
        }

        $s = $ext->settings;
        $baseUrl = trim((string) ($s['base_url'] ?? ''));
        $token = trim((string) ($s['api_token'] ?? ''));

        if ($baseUrl === '' || $token === '') {
            return null;
        }

        $timeout = 30;
        if (isset($s['timeout']) && is_numeric($s['timeout'])) {
            $timeout = max(1, min(300, (int) $s['timeout']));
        }

        return [
            'base_url' => $baseUrl,
            'api_token' => $token,
            'timeout' => $timeout,
        ];
    }
}
