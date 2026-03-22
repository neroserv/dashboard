<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\TldPricelist;

class TldPricelistSyncService
{
    public function __construct(
        protected BrandExtensionService $brandExtensionService,
    ) {}

    /**
     * Apply pricelist from Skrime (array of rows or object keyed by TLD).
     * Returns number of TLDs processed.
     *
     * @param  array<int|string, array{tld?: string, create?: mixed, renew?: mixed, transfer?: mixed, restore?: mixed}>  $list
     */
    public function applyPricelist(array $list, Brand $brand): int
    {
        $rows = $this->normalizePricelistRows($list);
        $c = $this->brandExtensionService->skrimeConfigForBrand($brand);
        $defaultMarginType = $c['margin_type'];
        $defaultMarginValue = (float) $c['margin_value'];
        $existing = TldPricelist::query()->where('brand_id', $brand->id)->get()->keyBy('tld');

        foreach ($rows as $row) {
            $tld = strtolower(ltrim((string) ($row['tld'] ?? ''), '.'));
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
                    'create_price' => $create,
                    'renew_price' => $renew,
                    'transfer_price' => $transfer,
                    'restore_price' => $restore,
                    'margin_type' => $defaultMarginType,
                    'margin_value' => $defaultMarginValue,
                ]);
                $existing->put($tld, $new);
            }
        }

        return count($rows);
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
