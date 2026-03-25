<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ResellerDomain;
use App\Models\User;
use App\Support\DomainRegistrar;
use Carbon\Carbon;

class DomainOrderFulfillmentService
{
    public function __construct(
        protected TldSaleRoutingService $routing,
        protected SkrimeApiService $skrimeApi,
        protected RealtimeRegisterApiService $realtimeRegisterApi,
        protected BrandExtensionService $brandExtensionService,
    ) {}

    /**
     * @param  array{firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string, company?: string}  $contact
     */
    public function fulfill(
        Brand $brand,
        User $user,
        string $domain,
        ?string $tld,
        array $contact,
        bool $isTransfer,
        ?string $authCode,
        float $purchasePrice,
        float $salePrice,
    ): ResellerDomain {
        $tldKey = $tld ? strtolower(ltrim($tld, '.')) : '';
        if ($tldKey === '' && str_contains($domain, '.')) {
            $tldKey = strtolower((string) substr($domain, strrpos($domain, '.') + 1));
        }

        $registrar = $this->routing->saleRegistrarFor($brand, $tldKey);

        if ($registrar === DomainRegistrar::REALTIME_REGISTER) {
            return $this->fulfillRealtimeRegister(
                $brand,
                $user,
                $domain,
                $tldKey !== '' ? $tldKey : null,
                $contact,
                $isTransfer,
                $authCode,
                $purchasePrice,
                $salePrice
            );
        }

        return $this->fulfillSkrime(
            $brand,
            $user,
            $domain,
            $tldKey !== '' ? $tldKey : null,
            $contact,
            $isTransfer,
            $authCode,
            $purchasePrice,
            $salePrice
        );
    }

    /**
     * @param  array{firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string, company?: string}  $contact
     */
    protected function fulfillSkrime(
        Brand $brand,
        User $user,
        string $domain,
        ?string $tldKey,
        array $contact,
        bool $isTransfer,
        ?string $authCode,
        float $purchasePrice,
        float $salePrice,
    ): ResellerDomain {
        $skrime = $this->skrimeApi->forBrand($brand);
        if (! $skrime->isConfigured()) {
            throw new \RuntimeException('Skrime API ist nicht konfiguriert.');
        }

        $orderData = $skrime->orderDomain(
            $domain,
            $contact,
            [],
            $isTransfer ? $authCode : null
        );

        $expiresAt = isset($orderData['expireAt']) ? Carbon::parse($orderData['expireAt']) : null;
        $domainStatus = $orderData['status'] ?? $orderData['state'] ?? 'active';
        $skrimeId = $orderData['id'] ?? $orderData['processId'] ?? null;

        return ResellerDomain::create([
            'brand_id' => $brand->id,
            'registrar' => DomainRegistrar::SKRIME,
            'is_sandbox' => false,
            'domain' => $domain,
            'user_id' => $user->id,
            'skrime_id' => $skrimeId,
            'realtimeregister_domain_name' => null,
            'status' => $domainStatus,
            'registered_at' => now(),
            'expires_at' => $expiresAt,
            'auto_renew' => (bool) ($orderData['autoRenew'] ?? false),
            'purchase_price' => $purchasePrice,
            'sale_price' => $salePrice,
            'tld' => $tldKey,
        ]);
    }

    /**
     * @param  array{firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string, company?: string}  $contact
     */
    protected function fulfillRealtimeRegister(
        Brand $brand,
        User $user,
        string $domain,
        ?string $tldKey,
        array $contact,
        bool $isTransfer,
        ?string $authCode,
        float $purchasePrice,
        float $salePrice,
    ): ResellerDomain {
        $rr = $this->realtimeRegisterApi->forBrand($brand);
        if (! $rr->isConfigured()) {
            throw new \RuntimeException('Realtime Register API ist nicht konfiguriert (API-Key und Kunden-Handle).');
        }

        $c = $this->brandExtensionService->realtimeregisterConfigForBrand($brand);
        $orderData = $rr->orderDomain(
            $domain,
            $contact,
            [],
            $isTransfer ? $authCode : null
        );

        $expiresAt = isset($orderData['expireAt']) ? Carbon::parse($orderData['expireAt']) : null;
        $domainStatus = $orderData['status'] ?? $orderData['state'] ?? 'active';

        return ResellerDomain::create([
            'brand_id' => $brand->id,
            'registrar' => DomainRegistrar::REALTIME_REGISTER,
            'is_sandbox' => (bool) ($c['sandbox'] ?? false),
            'domain' => $domain,
            'user_id' => $user->id,
            'skrime_id' => null,
            'realtimeregister_domain_name' => strtolower($domain),
            'status' => $domainStatus,
            'registered_at' => now(),
            'expires_at' => $expiresAt,
            'auto_renew' => (bool) ($orderData['autoRenew'] ?? false),
            'purchase_price' => $purchasePrice,
            'sale_price' => $salePrice,
            'tld' => $tldKey,
        ]);
    }
}
