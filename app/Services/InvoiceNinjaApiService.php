<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class InvoiceNinjaApiService
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

    public function isConfigured(): bool
    {
        if ($this->brand === null) {
            return false;
        }

        return $this->brandExtensionService->invoiceNinjaConfigForBrand($this->brand) !== null;
    }

    protected function client(): PendingRequest
    {
        $c = $this->brandExtensionService->invoiceNinjaConfigForBrand($this->brand);
        $token = (string) ($c['api_token'] ?? '');

        return Http::withHeaders([
            'X-API-TOKEN' => $token,
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($c['timeout'] ?? 30))
            ->baseUrl(rtrim((string) ($c['base_url'] ?? ''), '/'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createClient(array $payload): Response
    {
        return $this->client()->post('/api/v1/clients', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateClient(string $id, array $payload): Response
    {
        return $this->client()->put('/api/v1/clients/'.$id, $payload);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar>  $query
     */
    public function createInvoice(array $body, array $query = []): Response
    {
        $client = $this->client();
        if ($query !== []) {
            $client = $client->withQueryParameters($query);
        }

        return $client->post('/api/v1/invoices', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar>  $query
     */
    public function updateInvoice(string $id, array $body, array $query = []): Response
    {
        $client = $this->client();
        if ($query !== []) {
            $client = $client->withQueryParameters($query);
        }

        return $client->put('/api/v1/invoices/'.$id, $body);
    }
}
