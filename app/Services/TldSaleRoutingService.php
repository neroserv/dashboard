<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\TldSaleRouting;
use App\Support\DomainRegistrar;

class TldSaleRoutingService
{
    public function saleRegistrarFor(Brand $brand, string $tld): string
    {
        $tldKey = strtolower(ltrim(trim($tld), '.'));
        if ($tldKey === '') {
            $features = $brand->getFeaturesArray();
            $default = (string) ($features['domain_sales_registrar'] ?? DomainRegistrar::SKRIME);

            return DomainRegistrar::isValid($default) ? $default : DomainRegistrar::SKRIME;
        }

        $row = TldSaleRouting::query()
            ->where('brand_id', $brand->id)
            ->where('tld', $tldKey)
            ->first();

        if ($row !== null && DomainRegistrar::isValid($row->sale_registrar)) {
            return $row->sale_registrar;
        }

        $features = $brand->getFeaturesArray();
        $default = (string) ($features['domain_sales_registrar'] ?? DomainRegistrar::SKRIME);

        return DomainRegistrar::isValid($default) ? $default : DomainRegistrar::SKRIME;
    }

    public function setSaleRegistrar(Brand $brand, string $tld, string $registrar): void
    {
        if (! DomainRegistrar::isValid($registrar)) {
            throw new \InvalidArgumentException('Invalid registrar.');
        }

        $tldKey = strtolower(ltrim(trim($tld), '.'));
        TldSaleRouting::query()->updateOrCreate(
            ['brand_id' => $brand->id, 'tld' => $tldKey],
            ['sale_registrar' => $registrar]
        );
    }

    /**
     * When a new TLD price row appears, ensure routing exists (default from brand features).
     */
    public function ensureRoutingForTld(Brand $brand, string $tld, ?string $preferRegistrar = null): void
    {
        $tldKey = strtolower(ltrim(trim($tld), '.'));
        if ($tldKey === '') {
            return;
        }

        $exists = TldSaleRouting::query()
            ->where('brand_id', $brand->id)
            ->where('tld', $tldKey)
            ->exists();

        if ($exists) {
            return;
        }

        $features = $brand->getFeaturesArray();
        $default = (string) ($features['domain_sales_registrar'] ?? DomainRegistrar::SKRIME);
        if (! DomainRegistrar::isValid($default)) {
            $default = DomainRegistrar::SKRIME;
        }

        $sale = ($preferRegistrar !== null && DomainRegistrar::isValid($preferRegistrar))
            ? $preferRegistrar
            : $default;

        TldSaleRouting::query()->create([
            'brand_id' => $brand->id,
            'tld' => $tldKey,
            'sale_registrar' => $sale,
        ]);
    }

    /**
     * When a brand's default domain_sales_registrar (Brand::getFeaturesArray()) changes, update
     * per-TLD routing rows that still matched the previous default so checkout follows the new default.
     *
     * Rows with a different registrar (explicit per-TLD choice) are left unchanged.
     */
    public function migrateRoutingRowsForBrandRegistrarChange(Brand $brand, string $previousEffectiveRegistrar, string $newEffectiveRegistrar): void
    {
        if ($previousEffectiveRegistrar === $newEffectiveRegistrar) {
            return;
        }

        if (! DomainRegistrar::isValid($newEffectiveRegistrar)) {
            return;
        }

        TldSaleRouting::query()
            ->where('brand_id', $brand->id)
            ->where('sale_registrar', $previousEffectiveRegistrar)
            ->update(['sale_registrar' => $newEffectiveRegistrar]);
    }

    /**
     * Set every stored TLD routing row for this brand to the current domain_sales_registrar default.
     *
     * @return int Number of rows updated (0 if none exist)
     */
    public function applyBrandDefaultToAllTldRouting(Brand $brand): int
    {
        $default = (string) ($brand->getFeaturesArray()['domain_sales_registrar'] ?? DomainRegistrar::SKRIME);
        if (! DomainRegistrar::isValid($default)) {
            $default = DomainRegistrar::SKRIME;
        }

        return TldSaleRouting::query()
            ->where('brand_id', $brand->id)
            ->update(['sale_registrar' => $default]);
    }

    /**
     * Count of per-TLD routing rows whose registrar differs from the brand's domain_sales_registrar default.
     */
    public function countRoutingRowsMismatchedWithBrandDefault(Brand $brand): int
    {
        $default = (string) ($brand->getFeaturesArray()['domain_sales_registrar'] ?? DomainRegistrar::SKRIME);
        if (! DomainRegistrar::isValid($default)) {
            $default = DomainRegistrar::SKRIME;
        }

        return TldSaleRouting::query()
            ->where('brand_id', $brand->id)
            ->where('sale_registrar', '!=', $default)
            ->count();
    }
}
