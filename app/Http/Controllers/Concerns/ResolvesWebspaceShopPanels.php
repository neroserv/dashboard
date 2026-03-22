<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Brand;
use App\Services\BrandExtensionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ResolvesWebspaceShopPanels
{
    /**
     * @return array{brand: ?Brand, available_panels: array<int, array{value: string, label: string}>, panel: string}
     */
    protected function webspaceShopContext(Request $request): array
    {
        $brand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $service = app(BrandExtensionService::class);
        $available = $brand !== null ? $service->availableWebspacePanelTypes($brand) : [];
        if ($available === []) {
            $available = [['value' => 'plesk', 'label' => 'Plesk']];
        }
        $allowed = array_column($available, 'value');
        $requested = $request->query('panel');
        if (count($available) === 1) {
            $panel = $allowed[0];
        } else {
            if (is_string($requested) && in_array($requested, $allowed, true)) {
                $panel = $requested;
            } else {
                $panel = $brand !== null
                    ? ($service->defaultWebspacePanelQuery($brand) ?? $allowed[0])
                    : $allowed[0];
            }
        }

        return [
            'brand' => $brand,
            'available_panels' => $available,
            'panel' => $panel,
        ];
    }

    protected function scopeActiveWebspacePlansForPanel(Builder $query, string $panel): Builder
    {
        if ($panel === 'plesk') {
            return $query->where(function ($q) {
                $q->where('panel_type', 'plesk')->orWhereNull('panel_type');
            });
        }

        return $query->where('panel_type', $panel);
    }

    protected function effectiveWebspacePanelForPlan(?string $panelType): string
    {
        return $panelType === null || $panelType === '' ? 'plesk' : $panelType;
    }
}
