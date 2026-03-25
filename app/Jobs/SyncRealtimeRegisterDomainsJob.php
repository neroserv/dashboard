<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\ResellerDomain;
use App\Services\BrandExtensionService;
use App\Services\DomainPricingService;
use App\Services\RealtimeRegisterApiService;
use App\Support\DomainRegistrar;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class SyncRealtimeRegisterDomainsJob implements ShouldQueue
{
    use IsMonitored;
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public int $brandId,
    ) {}

    public function handle(
        RealtimeRegisterApiService $realtimeRegister,
        DomainPricingService $pricing,
        BrandExtensionService $brandExtensionService,
    ): void {
        $brand = Brand::query()->findOrFail($this->brandId);
        $rr = $realtimeRegister->forBrand($brand);
        if (! $rr->isConfigured()) {
            Log::warning('SyncRealtimeRegisterDomainsJob: not configured', ['brand_id' => $this->brandId]);

            return;
        }

        $pricing = $pricing->forBrand($brand);
        $isSandbox = (bool) ($brandExtensionService->realtimeregisterConfigForBrand($brand)['sandbox'] ?? false);

        $entities = $rr->exportDomainsList();

        $created = 0;
        $updated = 0;

        foreach ($entities as $json) {
            if (! is_array($json)) {
                continue;
            }
            $domainName = strtolower(trim((string) ($json['domainName'] ?? $json['domain'] ?? '')));
            if ($domainName === '') {
                continue;
            }
            $tld = substr(strrchr($domainName, '.'), 1) ?: null;
            $statuses = $json['status'] ?? [];
            $status = is_array($statuses) && $statuses !== [] ? (string) $statuses[0] : 'active';
            $statusLower = strtolower($status);
            if (str_contains($statusLower, 'delete') || str_contains($statusLower, 'cancel')) {
                $status = 'cancelled';
            }
            $expiresAt = isset($json['expiryDate']) ? Carbon::parse((string) $json['expiryDate']) : null;
            $createdAt = isset($json['createdDate']) ? Carbon::parse((string) $json['createdDate']) : null;
            $autoRenew = (bool) ($json['autoRenew'] ?? false);

            $existing = ResellerDomain::query()
                ->where('brand_id', $brand->id)
                ->where(function ($q) use ($domainName) {
                    $q->where('domain', $domainName)
                        ->orWhere('realtimeregister_domain_name', $domainName);
                })
                ->first();

            if ($existing) {
                $existing->update([
                    'registrar' => DomainRegistrar::REALTIME_REGISTER,
                    'realtimeregister_domain_name' => $domainName,
                    'status' => $status,
                    'expires_at' => $expiresAt,
                    'auto_renew' => $autoRenew,
                    'registered_at' => $existing->registered_at ?? $createdAt,
                    'tld' => $tld ?? $existing->tld,
                ]);
                $updated++;
            } else {
                $purchasePrice = null;
                $salePrice = null;
                if ($tld) {
                    $info = $pricing->getPricingForTld($tld, 'create');
                    if (($info['purchase_price'] ?? 0) > 0) {
                        $purchasePrice = $info['purchase_price'];
                        $salePrice = $info['sale_price'];
                    }
                }
                ResellerDomain::create([
                    'brand_id' => $brand->id,
                    'registrar' => DomainRegistrar::REALTIME_REGISTER,
                    'is_sandbox' => $isSandbox,
                    'domain' => $domainName,
                    'user_id' => null,
                    'skrime_id' => null,
                    'realtimeregister_domain_name' => $domainName,
                    'status' => $status,
                    'registered_at' => $createdAt,
                    'expires_at' => $expiresAt,
                    'auto_renew' => $autoRenew,
                    'tld' => $tld,
                    'purchase_price' => $purchasePrice,
                    'sale_price' => $salePrice,
                ]);
                $created++;
            }
        }

        Log::info('SyncRealtimeRegisterDomainsJob: completed', [
            'brand_id' => $this->brandId,
            'created' => $created,
            'updated' => $updated,
        ]);
    }
}
