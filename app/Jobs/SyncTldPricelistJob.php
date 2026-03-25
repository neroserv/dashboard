<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\RealtimeRegisterApiService;
use App\Services\SkrimeApiService;
use App\Services\TldPricelistSyncService;
use App\Support\DomainRegistrar;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class SyncTldPricelistJob implements ShouldQueue
{
    use IsMonitored;
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        public int $brandId,
        public string $priceSource = DomainRegistrar::SKRIME,
    ) {}

    public function handle(SkrimeApiService $skrime, RealtimeRegisterApiService $realtimeRegister, TldPricelistSyncService $sync): void
    {
        $brand = Brand::query()->findOrFail($this->brandId);

        try {
            if ($this->priceSource === DomainRegistrar::REALTIME_REGISTER) {
                $rr = $realtimeRegister->forBrand($brand);
                if (! $rr->isConfigured()) {
                    throw new \RuntimeException('Realtime Register ist für diese Marke nicht konfiguriert.');
                }
                $list = $rr->getPricelist();
            } else {
                $list = $skrime->forBrand($brand)->getPricelist();
            }
        } catch (\Throwable $e) {
            Log::error('SyncTldPricelistJob: getPricelist failed', [
                'brand_id' => $this->brandId,
                'price_source' => $this->priceSource,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $count = $sync->applyPricelist($list, $brand, $this->priceSource);
        Cache::forget('skrime_pricelist:'.$this->brandId);
        Cache::forget('rr_pricelist:'.$this->brandId);
        Log::info('SyncTldPricelistJob: completed', [
            'brand_id' => $this->brandId,
            'price_source' => $this->priceSource,
            'count' => $count,
        ]);
    }
}
