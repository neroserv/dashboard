<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SkrimeApiService
{
    protected ?Brand $brand = null;

    public function __construct(
        protected BrandExtensionService $brandExtensionService,
    ) {}

    public function forBrand(Brand $brand): static
    {
        $clone = clone $this;
        $clone->brand = $brand;

        return $clone;
    }

    /**
     * Whether API URL and token are set (after brand extension / .env merge).
     */
    public function isConfigured(): bool
    {
        $c = $this->brandExtensionService->skrimeConfigForBrand($this->brand);

        $url = trim((string) ($c['base_url'] ?? ''));
        $key = $c['api_key'] ?? null;

        return $url !== '' && $key !== null && trim((string) $key) !== '';
    }

    protected function client(): PendingRequest
    {
        $c = $this->brandExtensionService->skrimeConfigForBrand($this->brand);
        $token = (string) ($c['api_key'] ?? '');

        return Http::withToken($token)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout((int) ($c['timeout'] ?? 30))
            ->baseUrl(rtrim((string) ($c['base_url'] ?? ''), '/'));
    }

    /**
     * GET request with JSON body (Skrime uses GET + body for some endpoints).
     */
    protected function getWithBody(string $url, array $body): Response
    {
        return $this->client()
            ->withBody(json_encode($body), 'application/json')
            ->get($url);
    }

    /**
     * Check domain availability.
     *
     * @return array{available: bool, premium: bool, domain: string}
     */
    public function checkAvailability(string $domain): array
    {
        $response = $this->client()->post('/domain/check', ['domain' => $domain]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime domain check failed: '.$response->body());
        }

        $data = $response->json('data', []);

        return [
            'available' => (bool) ($data['available'] ?? false),
            'premium' => (bool) ($data['premium'] ?? false),
            'domain' => (string) ($data['domain'] ?? $domain),
        ];
    }

    /**
     * Get TLD pricelist (create, renew, transfer, restore in EUR).
     *
     * @return array<int, array{tld: string, create: string, renew: string, transfer: string, restore: string, offer: bool, offerTypes: array}>
     */
    public function getPricelist(): array
    {
        $response = $this->client()->get('/domain/pricelist');

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime pricelist failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Order a domain (register or transfer).
     *
     * @param  array{company?: string, firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string}  $contact
     * @param  array<int, string>  $nameservers
     */
    public function orderDomain(
        string $domain,
        array $contact,
        array $nameservers = [],
        ?string $authCode = null
    ): array {
        $c = $this->brandExtensionService->skrimeConfigForBrand($this->brand);
        $payload = [
            'domain' => $domain,
            'contact' => $contact,
            'nameserver' => $nameservers ?: ($c['default_nameservers'] ?? []),
            'tos' => true,
            'cancellation' => true,
        ];
        if ($authCode !== null) {
            $payload['authCode'] = $authCode;
        }

        $response = $this->client()->post('/domain/order', $payload);

        if ($response->successful()) {
            return $response->json('data', []);
        }

        $body = $response->json();
        $data = $body['data'] ?? [];
        $status = $data['status'] ?? null;
        $pendingStatuses = ['pendingfoa', 'pending'];
        if ($status && in_array(strtolower((string) $status), $pendingStatuses, true)) {
            $orderData = $data;
            if (isset($orderData['processId']) && empty($orderData['id'])) {
                $orderData['id'] = $orderData['processId'];
            }
            $orderData['state'] = $orderData['status'] ?? $status;

            return $orderData;
        }

        throw new \RuntimeException('Skrime domain order failed: '.$response->body());
    }

    /**
     * Get DNS records for a domain.
     *
     * @return array<int, array{name: string, type: string, data: string}>
     */
    public function getDns(string $domain): array
    {
        $response = $this->getWithBody('/domain/dns', ['domain' => $domain]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime get DNS failed: '.$response->body());
        }

        return $response->json('data.records', []);
    }

    /**
     * Update DNS records. All records must be sent (full replace).
     *
     * @param  array<int, array{name: string, type: string, data: string}>  $records
     * @return array<int, array{name: string, type: string, data: string}>
     */
    public function setDns(string $domain, array $records): array
    {
        $response = $this->client()->post('/domain/dns', [
            'domain' => $domain,
            'records' => $records,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime set DNS failed: '.$response->body());
        }

        return $response->json('data.records', []);
    }

    /**
     * Get contact for a domain.
     *
     * @return array{domain: string, contact: array, privacy?: array}
     */
    public function getContact(string $domain): array
    {
        $response = $this->getWithBody('/domain/contact', ['domain' => $domain]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime get contact failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Update contact for a domain.
     *
     * @param  array{company?: string, firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string}  $contact
     */
    public function setContact(string $domain, array $contact): array
    {
        $response = $this->client()->post('/domain/contact', [
            'domain' => $domain,
            'contact' => $contact,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime set contact failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Get nameservers for a domain.
     *
     * @return array{domain: string, nameserver: array<int, string>}
     */
    public function getNameserver(string $domain): array
    {
        $response = $this->getWithBody('/domain/nameserver', ['domain' => $domain]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime get nameserver failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Set nameservers (2–6 required).
     *
     * @param  array<int, string>  $nameservers
     * @return array{domain: string, nameserver: array<int, string>}
     */
    public function setNameserver(string $domain, array $nameservers): array
    {
        $response = $this->client()->post('/domain/nameserver', [
            'domain' => $domain,
            'nameserver' => $nameservers,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime set nameserver failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Get auth code for domain transfer.
     */
    public function getAuthcode(string $domain): string
    {
        $response = $this->getWithBody('/domain/authcode', ['domain' => $domain]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime get authcode failed: '.$response->body());
        }

        return (string) $response->json('data.authcode', '');
    }

    /**
     * Get single product by productId or domain.
     *
     * @return array<string, mixed>
     */
    public function getProduct(?string $productId = null, ?string $domain = null): array
    {
        $body = array_filter([
            'productId' => $productId,
            'domain' => $domain,
        ]);
        if (empty($body)) {
            throw new \InvalidArgumentException('Either productId or domain must be provided.');
        }

        $response = $this->getWithBody('/product/single', $body);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime get product failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Get all products, optionally filtered by type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(?string $type = 'domain'): array
    {
        $url = $type ? '/product/all?type='.urlencode($type) : '/product/all';
        $response = $this->client()->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime get products failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Renew a product (by productId or domain).
     *
     * @return array{productId: string, price: string, expireAt: string, deletedAt: string}
     */
    public function renewProduct(?string $productId = null, ?string $domain = null): array
    {
        $body = array_filter([
            'productId' => $productId,
            'domain' => $domain,
        ]);
        if (empty($body)) {
            throw new \InvalidArgumentException('Either productId or domain must be provided.');
        }

        $response = $this->client()->post('/product/renew', $body);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime renew failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Enable or disable auto-renewal.
     *
     * @return array{productId: string, autoRenew: bool}
     */
    public function setAutoRenew(bool $autoRenew, ?string $productId = null, ?string $domain = null): array
    {
        $body = array_merge(
            ['autoRenew' => $autoRenew],
            array_filter([
                'productId' => $productId,
                'domain' => $domain,
            ])
        );
        if (! isset($body['productId']) && ! isset($body['domain'])) {
            throw new \InvalidArgumentException('Either productId or domain must be provided.');
        }

        $response = $this->client()->post('/product/autorenew', $body);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime set autorenew failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Get DNSSEC for a domain.
     *
     * @return array{dnssec?: array{flags: int, algorithm: int, publicKey: string}}
     */
    public function getDnssec(string $domain): array
    {
        $response = $this->getWithBody('/domain/dnssec', ['domain' => $domain]);

        if (! $response->successful()) {
            $body = $response->body();
            if (str_contains($body, 'No dnssec found') || str_contains($body, 'no dnssec found')) {
                return ['dnssec' => null];
            }
            throw new \RuntimeException('Skrime get DNSSEC failed: '.$body);
        }

        return $response->json('data', []);
    }

    /**
     * Set DNSSEC for a domain.
     *
     * @param  array{flags: int, algorithm: int, publicKey: string}  $dnssec
     */
    public function setDnssec(string $domain, array $dnssec): array
    {
        $response = $this->client()->post('/domain/dnssec', [
            'domain' => $domain,
            'dnssec' => $dnssec,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime set DNSSEC failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Delete DNSSEC for a domain.
     */
    public function deleteDnssec(string $domain): array
    {
        $response = $this->client()->withBody(json_encode(['domain' => $domain]), 'application/json')
            ->delete('/domain/dnssec');

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime delete DNSSEC failed: '.$response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Update whois privacy settings for a domain.
     *
     * @param  array{organization?: bool, name?: bool, email?: bool, voice?: bool, addressLine?: bool, city?: bool, postalCode?: bool}  $privacy
     * @return array{domain: string, privacy: array}
     */
    public function updateWhoisPrivacy(string $domain, array $privacy): array
    {
        $response = $this->client()->post('/domain/whois', [
            'domain' => $domain,
            'privacy' => $privacy,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Skrime update whois privacy failed: '.$response->body());
        }

        return $response->json('data', []);
    }
}
