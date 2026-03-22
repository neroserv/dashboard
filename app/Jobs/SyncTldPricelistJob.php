<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\SkrimeApiService;
use App\Services\TldPricelistSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class SyncTldPricelistJob implements ShouldQueue
{
    use IsMonitored;
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        public int $brandId,
    ) {}

    public function handle(SkrimeApiService $skrime, TldPricelistSyncService $sync): void
    {
        $brand = Brand::query()->findOrFail($this->brandId);
        $skrime = $skrime->forBrand($brand);

        try {
            $list = $skrime->getPricelist();
        } catch (\Throwable $e) {
            Log::error('SyncTldPricelistJob: getPricelist failed', [
                'brand_id' => $this->brandId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $count = $sync->applyPricelist($list, $brand);
        Log::info('SyncTldPricelistJob: completed', ['brand_id' => $this->brandId, 'count' => $count]);
    }
}
