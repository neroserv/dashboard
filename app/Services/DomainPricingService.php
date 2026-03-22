<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\TldPricelist;
use Illuminate\Support\Facades\Cache;

class DomainPricingService
{
    protected ?Brand $brand = null;

    public function __construct(
        protected SkrimeApiService $skrimeApi,
        protected BrandExtensionService $brandExtensionService,
    ) {}

    public function forBrand(Brand $brand): static
    {
        $clone = clone $this;
        $clone->brand = $brand;

        return $clone;
    }

    protected function effectiveBrand(): ?Brand
    {
        return $this->brand ?? Brand::getDefault();
    }

    protected function skrimeClient(): SkrimeApiService
    {
        $brand = $this->effectiveBrand();

        return $brand !== null ? $this->skrimeApi->forBrand($brand) : $this->skrimeApi;
    }

    /**
     * Get pricelist from Skrime, cached for 1 hour per brand.
     *
     * @return array<int, array{tld: string, create: string, renew: string, transfer: string, restore: string, offer: bool, offerTypes: array}>
     */
    public function getPricelist(): array
    {
        $brand = $this->effectiveBrand();
        $keySuffix = $brand?->id ?? 'global';

        return Cache::remember("skrime_pricelist:{$keySuffix}", 3600, function () {
            return $this->skrimeClient()->getPricelist();
        });
    }

    /**
     * Get purchase price for a TLD (create, renew, or transfer). Uses tld_pricelist first, then Skrime cache.
     */
    public function getPurchasePrice(string $tld, string $type = 'create'): float
    {
        $tldKey = strtolower(ltrim($tld, '.'));
        if ($tldKey === '') {
            return 0;
        }

        $row = $this->tldPricelistRow($tldKey);
        if ($row) {
            $price = match ($type) {
                'renew' => (float) $row->renew_price,
                'transfer' => (float) ($row->transfer_price ?? 0),
                default => (float) $row->create_price,
            };

            return $price > 0 ? round($price, 2) : $this->getPurchasePriceFromSkrime($tldKey, $type);
        }

        return $this->getPurchasePriceFromSkrime($tldKey, $type);
    }

    protected function tldPricelistRow(string $tldKey): ?TldPricelist
    {
        $query = TldPricelist::query()->where('tld', $tldKey);
        $brand = $this->effectiveBrand();
        if ($brand !== null) {
            $query->where('brand_id', $brand->id);
        }

        return $query->first();
    }

    /**
     * Get purchase price from Skrime pricelist cache only.
     */
    protected function getPurchasePriceFromSkrime(string $tldKey, string $type): float
    {
        $list = $this->getPricelist();
        $key = match ($type) {
            'renew' => 'renew',
            'transfer' => 'transfer',
            default => 'create',
        };
        foreach ($list as $row) {
            if (strtolower((string) ($row['tld'] ?? '')) === $tldKey) {
                return (float) ($row[$key] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Calculate sale price from purchase price. Uses tld_pricelist margin for the given type (create/renew/transfer).
     */
    public function calculateSalePrice(float $purchasePrice, string $tld = '', string $type = 'create'): float
    {
        $tldKey = strtolower(ltrim($tld, '.'));
        $row = $tldKey !== '' ? $this->tldPricelistRow($tldKey) : null;
        if ($row) {
            $marginType = $row->margin_type ?? 'fixed';
            $marginValue = (float) match ($type) {
                'renew' => $row->margin_renew_value ?? $row->margin_value ?? 0,
                'transfer' => $row->margin_transfer_value ?? $row->margin_value ?? 0,
                default => $row->margin_value ?? 0,
            };
            if ($marginType === 'percent') {
                return round($purchasePrice * (1 + $marginValue / 100), 2);
            }

            return round($purchasePrice + $marginValue, 2);
        }

        $c = $this->brandExtensionService->skrimeConfigForBrand($this->effectiveBrand());
        $tlds = $c['tlds'] ?? [];
        $marginType = $tlds[$tldKey]['margin_type'] ?? $c['margin_type'];
        $marginValue = (float) ($tlds[$tldKey]['margin_value'] ?? $c['margin_value']);
        if ($marginType === 'percent') {
            return round($purchasePrice * (1 + $marginValue / 100), 2);
        }

        return round($purchasePrice + $marginValue, 2);
    }

    /**
     * Get sale price for a TLD and type (create/renew/transfer).
     */
    public function getSalePrice(string $tld, string $type = 'create'): float
    {
        $purchase = $this->getPurchasePrice($tld, $type);

        return $this->calculateSalePrice($purchase, $tld, $type);
    }

    /**
     * Get pricing info for a TLD (purchase, sale, margin) for create/renew/transfer.
     *
     * @return array{purchase_price: float, sale_price: float, margin: float, tld: string}
     */
    public function getPricingForTld(string $tld, string $type = 'create'): array
    {
        $purchase = $this->getPurchasePrice($tld, $type);
        $sale = $this->calculateSalePrice($purchase, $tld, $type);

        return [
            'purchase_price' => round($purchase, 2),
            'sale_price' => $sale,
            'margin' => round($sale - $purchase, 2),
            'tld' => $tld,
        ];
    }
}
