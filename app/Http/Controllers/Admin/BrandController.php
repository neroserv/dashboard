<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.index', ['tab' => 'marken']);
    }

    public function edit(Request $request, Brand $brand): Response
    {
        $user = $request->user();

        return Inertia::render('admin/brands/Edit', [
            'brand' => $brand,
            'maintenancePermissions' => [
                'canView' => $user !== null && (
                    $user->hasPermission('admin.maintenance')
                    || $user->hasPermission('admin.maintenance.view')
                    || $user->hasPermission('admin.maintenance.update')
                ),
                'canUpdate' => $user !== null && (
                    $user->hasPermission('admin.maintenance')
                    || $user->hasPermission('admin.maintenance.update')
                ),
            ],
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand, MaintenanceService $maintenanceService): RedirectResponse
    {
        $validated = $request->validated();

        if (isset($validated['domains'])) {
            $validated['domains'] = array_values(array_filter(array_map('trim', $validated['domains'])));
        }
        if (isset($validated['admin_domains'])) {
            $validated['admin_domains'] = array_values(array_filter(array_map(
                fn (string $d) => Brand::normalizeDomainToHost($d),
                array_map('trim', $validated['admin_domains']),
            )));
        }
        $validated['is_default'] = filter_var($validated['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($validated['is_default']) {
            Brand::query()->where('id', '!=', $brand->id)->update(['is_default' => false]);
        }
        if (isset($validated['features']['balance_period_months'])) {
            $validated['features']['balance_period_months'] = max(1, min(24, (int) $validated['features']['balance_period_months']));
        }

        if (isset($validated['features'])) {
            $validated['features'] = array_merge($brand->features ?? [], $validated['features']);
        }

        $canMaint = $request->user()?->hasPermission('admin.maintenance')
            || $request->user()?->hasPermission('admin.maintenance.update');

        if ($canMaint && isset($validated['maintenance'])) {
            $prev = $maintenanceService->normalizedBrandMaintenance($brand);
            $incomingFlat = [
                'enabled' => (bool) ($validated['maintenance']['enabled'] ?? false),
                'message' => array_key_exists('message', $validated['maintenance'])
                    ? ($validated['maintenance']['message'] !== null ? (string) $validated['maintenance']['message'] : null)
                    : null,
                'until' => $validated['maintenance']['until'] ?? null,
            ];
            $changed = $maintenanceService->brandMaintenanceChanged($prev, $incomingFlat);
            $toggledAt = $changed
                ? now()->toIso8601String()
                : ($prev['toggled_at']?->toIso8601String());
            $untilStored = ($incomingFlat['until'] !== null && $incomingFlat['until'] !== '')
                ? CarbonImmutable::parse((string) $incomingFlat['until'])->toIso8601String()
                : null;
            $msg = $incomingFlat['message'];
            $validated['maintenance'] = [
                'enabled' => $incomingFlat['enabled'],
                'message' => ($msg !== null && trim($msg) === '') ? null : $msg,
                'until' => $untilStored,
                'toggled_at' => $toggledAt,
            ];
        } else {
            unset($validated['maintenance']);
        }

        $brand->update($validated);

        return redirect()->route('admin.settings.index', ['tab' => 'marken'])->with('success', 'Marke gespeichert.');
    }
}
