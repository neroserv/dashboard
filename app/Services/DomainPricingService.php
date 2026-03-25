<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\TldPricelist;
use App\Support\DomainRegistrar;
use Illuminate\Support\Facades\Cache;

class DomainPricingService
{
    protected ?Brand $brand = null;

    public function __construct(
        protected SkrimeApiService $skrimeApi,
        protected BrandExtensionService $brandExtensionService,
        protected TldSaleRoutingService $saleRouting,
        protected RealtimeRegisterApiService $realtimeRegisterApi,
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

    public function saleRegistrarForTld(string $tld): string
    {
        $brand = $this->effectiveBrand();
        if ($brand === null) {
            return DomainRegistrar::SKRIME;
        }

        return $this->saleRouting->saleRegistrarFor($brand, $tld);
    }

    /**
     * Cached pricelist for the **sale** registrar of this brand (used for API fallbacks).
     *
     * @return array<int, array{tld: string, create: string, renew: string, transfer: string, restore: string, offer: bool, offerTypes: array}>
     */
    public function getPricelist(): array
    {
        $brand = $this->effectiveBrand();
        $keySuffix = $brand?->id ?? 'global';
        $registrar = DomainRegistrar::SKRIME;
        if ($brand !== null) {
            $raw = (string) ($brand->getFeaturesArray()['domain_sales_registrar'] ?? DomainRegistrar::SKRIME);
            $registrar = DomainRegistrar::isValid($raw) ? $raw : DomainRegistrar::SKRIME;
        }

        if ($registrar === DomainRegistrar::REALTIME_REGISTER) {
            return Cache::remember("rr_pricelist:{$keySuffix}", 3600, function () use ($brand) {
                if ($brand === null) {
                    return [];
                }

                return $this->realtimeRegisterApi->forBrand($brand)->getPricelist();
            });
        }

        return Cache::remember("skrime_pricelist:{$keySuffix}", 3600, function () {
            return $this->skrimeClient()->getPricelist();
        });
    }

    /**
     * Get purchase price for a TLD (create, renew, or transfer). Uses tld_pricelist row for the **sale** registrar, then API cache.
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

            return $price > 0 ? round($price, 2) : $this->getPurchasePriceFromRegistrarApi($tldKey, $type);
        }

        return $this->getPurchasePriceFromRegistrarApi($tldKey, $type);
    }

    protected function tldPricelistRow(string $tldKey): ?TldPricelist
    {
        $brand = $this->effectiveBrand();
        if ($brand === null) {
            return TldPricelist::query()
                ->where('tld', $tldKey)
                ->where('price_source', DomainRegistrar::SKRIME)
                ->first();
        }

        $sale = $this->saleRouting->saleRegistrarFor($brand, $tldKey);

        return TldPricelist::query()
            ->where('brand_id', $brand->id)
            ->where('tld', $tldKey)
            ->where('price_source', $sale)
            ->first();
    }

    protected function getPurchasePriceFromRegistrarApi(string $tldKey, string $type): float
    {
        $brand = $this->effectiveBrand();
        if ($brand === null) {
            return 0;
        }

        $sale = $this->saleRouting->saleRegistrarFor($brand, $tldKey);
        $list = $this->cachedPricelistForRegistrar($brand, $sale);
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
     * @return array<int, array<string, mixed>>
     */
    protected function cachedPricelistForRegistrar(Brand $brand, string $registrar): array
    {
        if ($registrar === DomainRegistrar::REALTIME_REGISTER) {
            return Cache::remember('rr_pricelist:'.$brand->id, 3600, function () use ($brand) {
                $rr = $this->realtimeRegisterApi->forBrand($brand);
                if (! $rr->isConfigured()) {
                    return [];
                }

                return $rr->getPricelist();
            });
        }

        return Cache::remember('skrime_pricelist:'.$brand->id, 3600, function () use ($brand) {
            return $this->skrimeApi->forBrand($brand)->getPricelist();
        });
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

        $brand = $this->effectiveBrand();
        $saleRegistrar = $tldKey !== '' && $brand !== null
            ? $this->saleRouting->saleRegistrarFor($brand, $tldKey)
            : DomainRegistrar::SKRIME;

        $c = $saleRegistrar === DomainRegistrar::REALTIME_REGISTER
            ? $this->brandExtensionService->realtimeregisterConfigForBrand($brand)
            : $this->brandExtensionService->skrimeConfigForBrand($brand);

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
