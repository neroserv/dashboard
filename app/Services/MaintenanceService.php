<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array{enabled: bool, message: string|null, until: CarbonInterface|null, toggled_at: CarbonInterface|null}
     */
    public function normalizedBrandMaintenance(?Brand $brand): array
    {
        $raw = $brand?->maintenance;
        if (! is_array($raw)) {
            $raw = [];
        }

        return [
            'enabled' => filter_var($raw['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'message' => isset($raw['message']) && is_string($raw['message']) ? $raw['message'] : null,
            'until' => $this->parseOptionalDateTime($raw['until'] ?? null),
            'toggled_at' => $this->parseOptionalDateTime($raw['toggled_at'] ?? null),
        ];
    }

    public function cooldownMinutes(): int
    {
        $v = Setting::get('maintenance_toggle_cooldown_minutes', '0');

        return max(0, (int) $v);
    }

    public function globalToggledAt(): ?CarbonInterface
    {
        return $this->parseOptionalDateTime(Setting::get('maintenance_global_toggled_at'));
    }

    public function isGlobalScheduleActive(): bool
    {
        if (! filter_var(Setting::get('maintenance_global_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $until = $this->parseOptionalDateTime(Setting::get('maintenance_global_until'));

        if ($until !== null && $until->isPast()) {
            return false;
        }

        return true;
    }

    public function isBrandScheduleActive(Brand $brand): bool
    {
        $m = $this->normalizedBrandMaintenance($brand);
        if (! $m['enabled']) {
            return false;
        }
        if ($m['until'] !== null && $m['until']->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Whether the storefront / API for this request should be blocked (503).
     */
    public function shouldBlockRequest(Request $request): bool
    {
        if ($this->isWhitelistedRoute($request)) {
            return false;
        }

        $route = $request->route();
        if ($request->user() === null && ! $request->expectsJson() && $route !== null && $this->routeUsesAuthenticationMiddleware($route)) {
            return false;
        }

        if ($this->isGloballyActive()) {
            return true;
        }

        $brand = $request->attributes->get('current_brand');
        if ($brand instanceof Brand && $this->isBrandScheduleActive($brand)) {
            return true;
        }

        return false;
    }

    public function isGloballyActive(): bool
    {
        return Cache::remember('maintenance.global_active', self::CACHE_TTL_SECONDS, fn () => $this->isGlobalScheduleActive());
    }

    public function forgetGlobalActiveCache(): void
    {
        Cache::forget('maintenance.global_active');
    }

    /**
     * @return array{message: string|null, until: CarbonInterface|null, brand_name: string|null}
     */
    public function payloadForBlockedRequest(Request $request): array
    {
        if ($this->isGloballyActive()) {
            return [
                'message' => $this->nullableString(Setting::get('maintenance_global_message')),
                'until' => $this->parseOptionalDateTime(Setting::get('maintenance_global_until')),
                'brand_name' => $request->attributes->get('current_brand') instanceof Brand
                    ? $request->attributes->get('current_brand')->name
                    : null,
            ];
        }

        $brand = $request->attributes->get('current_brand');
        if ($brand instanceof Brand) {
            $m = $this->normalizedBrandMaintenance($brand);

            return [
                'message' => $m['message'],
                'until' => $m['until'],
                'brand_name' => $brand->name,
            ];
        }

        return [
            'message' => null,
            'until' => null,
            'brand_name' => null,
        ];
    }

    public function assertGlobalCooldownAllowsToggle(): void
    {
        $this->assertCooldownAllows($this->globalToggledAt());
    }

    public function assertBrandCooldownAllowsToggle(Brand $brand): void
    {
        $m = $this->normalizedBrandMaintenance($brand);
        $this->assertCooldownAllows($m['toggled_at']);
    }

    /**
     * @param  array{enabled?: mixed, message?: mixed, until?: mixed}  $previous
     * @param  array{enabled?: mixed, message?: mixed, until?: mixed}  $incoming
     */
    public function globalMaintenanceChanged(array $previous, array $incoming): bool
    {
        $pEn = filter_var($previous['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $iEn = filter_var($incoming['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pMsg = trim((string) ($previous['message'] ?? ''));
        $iMsg = trim((string) ($incoming['message'] ?? ''));
        $pUntil = $this->normalizeUntilForCompare($previous['until'] ?? '');
        $iUntil = $this->normalizeUntilForCompare($incoming['until'] ?? '');

        return $pEn !== $iEn || $pMsg !== $iMsg || $pUntil !== $iUntil;
    }

    protected function routeUsesAuthenticationMiddleware(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if ($middleware === 'auth' || (is_string($middleware) && str_starts_with($middleware, 'auth:'))) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeUntilForCompare(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $str = trim((string) $value);
        if ($str === '') {
            return '';
        }
        try {
            return CarbonImmutable::parse($str)->toIso8601String();
        } catch (\Throwable) {
            return $str;
        }
    }

    /**
     * @param  array{enabled: bool, message: string|null, until: CarbonInterface|null, toggled_at: CarbonInterface|null}  $previousNormalized
     * @param  array{enabled?: bool, message?: string|null, until?: string|null}  $incoming
     */
    public function brandMaintenanceChanged(array $previousNormalized, array $incoming): bool
    {
        $newUntilRaw = $incoming['until'] ?? null;
        $newUntil = ($newUntilRaw !== null && $newUntilRaw !== '')
            ? CarbonImmutable::parse((string) $newUntilRaw)->toIso8601String()
            : null;
        $prevUntil = $previousNormalized['until']?->toIso8601String();

        return $previousNormalized['enabled'] !== (bool) ($incoming['enabled'] ?? false)
            || (string) ($previousNormalized['message'] ?? '') !== (string) ($incoming['message'] ?? '')
            || $prevUntil !== $newUntil;
    }

    public function isWhitelistedRoute(Request $request): bool
    {
        $route = $request->route();
        $name = $route?->getName();

        if ($name !== null && (
            str_starts_with($name, 'webhooks.')
            || $name === 'sanctum.csrf-cookie'
            || $name === 'api.verify-domain'
            || $name === 'pwa.manifest'
            || $name === 'favicon'
        )) {
            return true;
        }

        if ($name !== null && preg_match('/^(login|register|logout|password\.|verification\.|two-factor\.)/', $name)) {
            return true;
        }

        $path = trim($request->path(), '/');

        if ($path === 'up') {
            return true;
        }

        if (str_starts_with($path, 'webhooks/')) {
            return true;
        }

        if ($path === 'api/verify-domain') {
            return true;
        }

        if (str_starts_with($path, 'auth/')) {
            return true;
        }

        if ($path === 'phpmyadmin-signon-credentials') {
            return true;
        }

        if (preg_match('#^admin($|/)#', $path)) {
            return true;
        }

        return false;
    }

    protected function assertCooldownAllows(?CarbonInterface $lastToggle): void
    {
        $minutes = $this->cooldownMinutes();
        if ($minutes === 0 || $lastToggle === null) {
            return;
        }

        $until = $lastToggle->addMinutes($minutes);
        if ($until->isFuture()) {
            throw ValidationException::withMessages([
                'maintenance' => ["Bitte warten Sie noch {$minutes} Minute(n), bevor Sie die Wartung erneut ändern."],
            ]);
        }
    }

    protected function parseOptionalDateTime(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : null;
    }
}
