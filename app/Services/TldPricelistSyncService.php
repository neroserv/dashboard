<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\TldPricelist;
use App\Support\DomainRegistrar;

class TldPricelistSyncService
{
    public function __construct(
        protected BrandExtensionService $brandExtensionService,
        protected TldSaleRoutingService $saleRouting,
    ) {}

    /**
     * Apply pricelist from Skrime or Realtime Register.
     * Returns number of TLDs processed.
     *
     * @param  array<int|string, array{tld?: string, create?: mixed, renew?: mixed, transfer?: mixed, restore?: mixed}>  $list
     */
    public function applyPricelist(array $list, Brand $brand, string $priceSource): int
    {
        if (! DomainRegistrar::isValid($priceSource)) {
            throw new \InvalidArgumentException('Invalid price source registrar.');
        }

        $rows = $this->normalizePricelistRows($list);
        $c = $priceSource === DomainRegistrar::REALTIME_REGISTER
            ? $this->brandExtensionService->realtimeregisterConfigForBrand($brand)
            : $this->brandExtensionService->skrimeConfigForBrand($brand);
        $defaultMarginType = $c['margin_type'];
        $defaultMarginValue = (float) $c['margin_value'];
        $existingRows = TldPricelist::query()
            ->where('brand_id', $brand->id)
            ->where('price_source', $priceSource)
            ->get();

        // Ensure: per (brand_id, price_source) only one physical row per normalized TLD.
        $primaryByNormalizedTld = [];
        $duplicateIds = [];
        foreach ($existingRows as $row) {
            $normalized = $this->normalizeTld((string) $row->tld);
            if ($normalized === '') {
                $duplicateIds[] = (int) $row->id;

                continue;
            }

            if (! isset($primaryByNormalizedTld[$normalized])) {
                $primaryByNormalizedTld[$normalized] = $row;

                continue;
            }

            $duplicateIds[] = (int) $row->id;
        }

        if ($duplicateIds !== []) {
            TldPricelist::query()
                ->where('brand_id', $brand->id)
                ->where('price_source', $priceSource)
                ->whereIn('id', $duplicateIds)
                ->delete();
        }

        foreach ($primaryByNormalizedTld as $normalized => $row) {
            if ((string) $row->tld !== $normalized) {
                $row->update(['tld' => $normalized]);
            }
        }

        $existing = TldPricelist::query()
            ->where('brand_id', $brand->id)
            ->where('price_source', $priceSource)
            ->get()
            ->keyBy(fn (TldPricelist $row) => $this->normalizeTld((string) $row->tld));

        foreach ($rows as $row) {
            $tld = $this->normalizeTld((string) ($row['tld'] ?? ''));
            if ($tld === '') {
                continue;
            }
            $create = (float) ($row['create'] ?? 0);
            $renew = (float) ($row['renew'] ?? 0);
            $transfer = isset($row['transfer']) ? (float) $row['transfer'] : null;
            $restore = isset($row['restore']) ? (float) $row['restore'] : null;

            $model = $existing->get($tld);
            if ($model) {
                $model->update([
                    'create_price' => $create,
                    'renew_price' => $renew,
                    'transfer_price' => $transfer,
                    'restore_price' => $restore,
                ]);
            } else {
                $new = TldPricelist::create([
                    'brand_id' => $brand->id,
                    'tld' => $tld,
                    'price_source' => $priceSource,
                    'create_price' => $create,
                    'renew_price' => $renew,
                    'transfer_price' => $transfer,
                    'restore_price' => $restore,
                    'margin_type' => $defaultMarginType,
                    'margin_value' => $defaultMarginValue,
                ]);
                $existing->put($tld, $new);
            }

            $this->saleRouting->ensureRoutingForTld($brand, $tld, $priceSource);
        }

        return count($rows);
    }

    protected function normalizeTld(string $tld): string
    {
        $tld = strtolower(trim($tld));
        $tld = ltrim($tld, '.');

        return $tld;
    }

    /**
     * Normalize API response: accept array of rows with 'tld' or object keyed by TLD.
     *
     * @param  array<int|string, array<string, mixed>>  $list
     * @return array<int, array{tld: string, create: mixed, renew: mixed, transfer?: mixed, restore?: mixed}>
     */
    protected function normalizePricelistRows(array $list): array
    {
        $rows = [];
        foreach ($list as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['tld'])) {
                $rows[] = $row;
            } else {
                $rows[] = array_merge($row, ['tld' => (string) $key]);
            }
        }

        return $rows;
    }
}
