<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class InvoiceNinjaSyncService
{
    public function __construct(
        protected BrandExtensionService $brandExtensionService,
        protected InvoiceNinjaApiService $invoiceNinjaApiService,
    ) {}

    public function sync(Invoice $invoice): void
    {
        $invoice->loadMissing(['user.brand', 'lineItems']);

        $user = $invoice->user;
        if ($user === null) {
            $this->logSkip('missing_invoice_user', ['invoice_id' => $invoice->id]);

            return;
        }

        $brand = $user->brand ?? Brand::getDefault();
        if ($brand === null) {
            $this->logSkip('no_brand_resolved', [
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'user_brand_id' => $user->brand_id,
            ]);

            return;
        }

        if (! $this->brandExtensionService->isInstalled($brand, BrandExtension::EXTENSION_INVOICE_NINJA)) {
            $this->logSkip('invoice_ninja_extension_not_installed', [
                'invoice_id' => $invoice->id,
                'brand_id' => $brand->id,
            ]);

            return;
        }

        if ($this->brandExtensionService->invoiceNinjaConfigForBrand($brand) === null) {
            $this->logSkip('invoice_ninja_not_configured', [
                'invoice_id' => $invoice->id,
                'brand_id' => $brand->id,
                'hint' => 'Set base_url and api_token in brand extension settings.',
            ]);

            return;
        }

        if ($invoice->lineItems->isEmpty()) {
            $this->logSkip('no_line_items', ['invoice_id' => $invoice->id]);

            return;
        }

        $api = $this->invoiceNinjaApiService->forBrand($brand);

        $clientId = $this->ensureClientId($user, $brand, $api);

        $body = $this->buildInvoicePayload($invoice, $clientId);
        $query = $this->invoiceActionQuery($invoice);

        $existingId = is_array($invoice->metadata)
            ? (string) ($invoice->metadata['invoice_ninja_invoice_id'] ?? '')
            : '';
        $existingId = trim($existingId);

        if ($existingId !== '') {
            $response = $api->updateInvoice($existingId, $body, $query);
        } else {
            $response = $api->createInvoice($body, $query);
        }

        if (! $response->successful()) {
            Log::warning('Invoice Ninja sync failed', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Invoice Ninja API error: HTTP '.$response->status());
        }

        $newId = $this->extractEntityId($response) ?? $existingId;
        if ($newId === '') {
            throw new \RuntimeException('Invoice Ninja response missing invoice id.');
        }

        $this->persistInvoiceNinjaMetadata($invoice, $newId);
    }

    protected function ensureClientId(User $user, Brand $brand, InvoiceNinjaApiService $api): string
    {
        $existing = trim((string) ($user->invoice_ninja_client_id ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $payload = $this->buildClientPayload($user);
        $response = $api->createClient($payload);

        if (! $response->successful()) {
            Log::warning('Invoice Ninja client create failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Invoice Ninja client create failed: HTTP '.$response->status());
        }

        $clientId = $this->extractEntityId($response);
        if ($clientId === null || $clientId === '') {
            throw new \RuntimeException('Invoice Ninja response missing client id.');
        }

        $user->forceFill(['invoice_ninja_client_id' => $clientId])->saveQuietly();

        return $clientId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClientPayload(User $user): array
    {
        $iso2 = $this->iso3166Alpha2ForUser($user);
        $name = trim((string) ($user->company ?? ''));
        if ($name === '') {
            $name = trim((string) ($user->name ?? '')) ?: 'Kunde';
        }

        [$firstName, $lastName] = $this->splitName((string) $user->name);
        $streetLine = trim(trim((string) ($user->street ?? '')).' '.trim((string) ($user->street_number ?? '')));

        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            $phone = '—';
        }

        return [
            'name' => $name,
            'contacts' => [
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => (string) $user->email,
                    'phone' => $phone,
                    'send_email' => false,
                ],
            ],
            'address1' => $streetLine !== '' ? $streetLine : '—',
            'city' => trim((string) ($user->city ?? '')) ?: '—',
            'state' => trim((string) ($user->state ?? '')) ?: '—',
            'postal_code' => trim((string) ($user->postal_code ?? '')) ?: '—',
            'country_id' => $this->countryNumericId($iso2),
            'country_code' => $iso2,
            'phone' => $phone,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['Kunde', 'Kunde'];
        }

        $parts = preg_split('/\s+/u', $fullName, 2, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return ['Kunde', 'Kunde'];
        }

        $first = $parts[0];
        $last = $parts[1] ?? $first;

        return [$first, $last];
    }

    protected function iso3166Alpha2ForUser(User $user): string
    {
        $c = strtoupper(trim((string) ($user->country ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $c) === 1) {
            return $c;
        }

        return 'DE';
    }

    protected function countryNumericId(string $iso2): int
    {
        return match (strtoupper($iso2)) {
            'AT' => 40,
            'CH' => 756,
            'US' => 840,
            'GB', 'UK' => 826,
            'NL' => 528,
            'FR' => 250,
            'IT' => 380,
            default => 276,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildInvoicePayload(Invoice $invoice, string $clientId): array
    {
        $lineItems = [];
        foreach ($invoice->lineItems as $line) {
            $lineItems[] = [
                'quantity' => (float) $line->quantity,
                'cost' => (float) $line->unit_price,
                'product_key' => 'line_'.(int) $line->position,
                'notes' => (string) $line->description,
                'discount' => 0,
                'is_amount_discount' => true,
            ];
        }

        $privateNotes = 'Portal: '.($invoice->number ?? '').' | id='.$invoice->id.' | uuid='.($invoice->uuid ?? '');

        $payload = [
            'client_id' => $clientId,
            'status_id' => (string) $this->mapLocalStatusToNinjaStatusId((string) $invoice->status),
            'date' => $invoice->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'private_notes' => $privateNotes,
            'line_items' => $lineItems,
        ];

        if ($invoice->due_date !== null) {
            $payload['due_date'] = $invoice->due_date->format('Y-m-d');
        }

        if ($invoice->number !== null && $invoice->number !== '') {
            $payload['number'] = (string) $invoice->number;
        }

        return $payload;
    }

    /**
     * @return array<string, scalar>
     */
    protected function invoiceActionQuery(Invoice $invoice): array
    {
        $query = [];
        if ($invoice->status === 'paid') {
            $query['paid'] = 'true';
        }
        if ($invoice->status === 'cancelled') {
            $query['cancel'] = 'true';
        }

        return $query;
    }

    protected function mapLocalStatusToNinjaStatusId(string $status): int
    {
        return match ($status) {
            'draft' => 1,
            'sent', 'pending' => 2,
            'paid' => 4,
            'cancelled' => 5,
            default => 1,
        };
    }

    protected function extractEntityId(Response $response): ?string
    {
        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            return null;
        }

        $id = $data['id'] ?? null;
        if (is_int($id) || is_float($id)) {
            $id = (string) $id;
        }

        return is_string($id) && $id !== '' ? $id : null;
    }

    protected function persistInvoiceNinjaMetadata(Invoice $invoice, string $ninjaInvoiceId): void
    {
        $meta = is_array($invoice->metadata) ? $invoice->metadata : [];
        $meta['invoice_ninja_invoice_id'] = $ninjaInvoiceId;
        $meta['invoice_ninja_synced_at'] = now()->toIso8601String();

        $invoice->forceFill(['metadata' => $meta])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logSkip(string $reason, array $context = []): void
    {
        Log::info('Invoice Ninja sync skipped', array_merge(['reason' => $reason], $context));
    }
}
