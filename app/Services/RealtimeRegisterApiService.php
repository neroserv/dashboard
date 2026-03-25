<?php

namespace App\Services;

use App\Models\Brand;
use App\Support\RealtimeRegisterPhone;
use RealtimeRegister\Domain\DomainZoneRecordCollection;
use RealtimeRegister\Domain\KeyData;
use RealtimeRegister\Domain\KeyDataCollection;
use RealtimeRegister\RealtimeRegister;

class RealtimeRegisterApiService
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
     * @return list<string> German labels for missing settings (empty when {@see isConfigured()} is true).
     */
    public function configurationIssues(): array
    {
        $c = $this->brandExtensionService->realtimeregisterConfigForBrand($this->brand);
        $key = trim((string) ($c['api_key'] ?? ''));
        $customer = trim((string) ($c['customer_handle'] ?? ''));
        $url = trim((string) ($c['base_url'] ?? ''));

        $missing = [];
        if ($key === '') {
            $missing[] = 'API-Key (Secret): Marke oder REALTIMEREGISTER_API_KEY in .env';
        }
        if ($customer === '') {
            $missing[] = 'Kunden-Handle: Marke oder REALTIMEREGISTER_CUSTOMER_HANDLE in .env';
        }
        if ($url === '') {
            $missing[] = 'API-Basis-URL: Sandbox aktivieren, REALTIMEREGISTER_BASE_URL oder API-URL in der Marke';
        }

        return $missing;
    }

    public function isConfigured(): bool
    {
        return $this->configurationIssues() === [];
    }

    protected function sdk(): RealtimeRegister
    {
        $c = $this->brandExtensionService->realtimeregisterConfigForBrand($this->brand);
        $apiKey = (string) ($c['api_key'] ?? '');
        $baseUrl = rtrim((string) ($c['base_url'] ?? ''), '/').'/';

        return new RealtimeRegister($apiKey, $baseUrl);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exportDomainsList(): array
    {
        return $this->sdk()->domains->export();
    }

    /**
     * @return array<int, array{tld: string, create: string, renew: string, transfer: string, restore: string, offer: bool, offerTypes: array}>
     */
    public function getPricelist(?string $currency = 'EUR'): array
    {
        $c = $this->brandExtensionService->realtimeregisterConfigForBrand($this->brand);
        $customer = trim((string) ($c['customer_handle'] ?? ''));
        if ($customer === '') {
            throw new \RuntimeException('Realtime Register: customer_handle fehlt.');
        }

        $collection = $this->sdk()->customers->priceList($customer, $currency);
        $byTld = [];

        foreach ($collection->entities as $price) {
            if (! str_starts_with($price->product, 'domain_')) {
                continue;
            }
            $tld = $this->productToTld($price->product);
            if ($tld === '') {
                continue;
            }
            if (! isset($byTld[$tld])) {
                $byTld[$tld] = [
                    'tld' => $tld,
                    'create' => '0',
                    'renew' => '0',
                    'transfer' => '0',
                    'restore' => '0',
                    'offer' => false,
                    'offerTypes' => [],
                ];
            }
            $euro = $this->minorUnitToDecimal($price->price, $price->currency);
            $key = match (strtoupper($price->action)) {
                'CREATE' => 'create',
                'RENEW' => 'renew',
                'TRANSFER' => 'transfer',
                'RESTORE', 'REVIVE' => 'restore',
                default => null,
            };
            if ($key !== null) {
                $byTld[$tld][$key] = number_format($euro, 2, '.', '');
            }
        }

        return array_values($byTld);
    }

    /**
     * @return array{available: bool, premium: bool, domain: string}
     */
    public function checkAvailability(string $domain): array
    {
        $availability = $this->sdk()->domains->check($domain);

        return [
            'available' => $availability->available,
            'premium' => (bool) $availability->premium,
            'domain' => $domain,
        ];
    }

    /**
     * @param  array{company?: string, firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string}  $contact
     * @param  array<int, string>  $nameservers
     * @return array<string, mixed>
     */
    public function orderDomain(
        string $domain,
        array $contact,
        array $nameservers = [],
        ?string $authCode = null
    ): array {
        $c = $this->brandExtensionService->realtimeregisterConfigForBrand($this->brand);
        $customer = trim((string) ($c['customer_handle'] ?? ''));
        if ($customer === '') {
            throw new \RuntimeException('Realtime Register: customer_handle fehlt.');
        }

        $handle = 'c'.bin2hex(random_bytes(5));
        $name = trim($contact['firstname'].' '.$contact['lastname']);
        $organization = trim((string) ($contact['company'] ?? ''));
        $addressLine = [trim($contact['street'].' '.$contact['number'])];
        $voice = RealtimeRegisterPhone::toVoice($contact['phone'], $contact['country']);

        $this->sdk()->contacts->create(
            $customer,
            $handle,
            $name,
            $addressLine,
            $contact['postcode'],
            $contact['city'],
            strtoupper(substr($contact['country'], 0, 2)),
            $contact['email'],
            $voice,
            null,
            $organization !== '' ? $organization : null,
            $contact['state'] !== '' ? $contact['state'] : null,
        );

        $ns = $nameservers !== [] ? array_values($nameservers) : ($c['default_nameservers'] ?? []);
        $ns = array_values(array_filter($ns, fn ($h) => is_string($h) && trim($h) !== ''));

        if ($authCode !== null && trim($authCode) !== '') {
            $result = $this->sdk()->domains->transfer(
                $domain,
                $customer,
                $handle,
                null,
                null,
                trim($authCode),
                null,
                $ns !== [] ? $ns : null,
            );

            return [
                'id' => null,
                'processId' => (string) $result->processId,
                'status' => $result->status,
                'state' => $result->status,
                'expireAt' => $result->expiryDate?->format('Y-m-d\TH:i:s\Z'),
                'autoRenew' => false,
            ];
        }

        $registration = $this->sdk()->domains->register(
            $domain,
            $customer,
            $handle,
            false,
            1,
            null,
            null,
            true,
            $ns !== [] ? $ns : null,
        );

        return [
            'id' => $domain,
            'processId' => null,
            'status' => $registration->status[0] ?? 'ACTIVE',
            'state' => $registration->status[0] ?? 'ACTIVE',
            'expireAt' => $registration->expiryDate?->format('Y-m-d\TH:i:s\Z'),
            'autoRenew' => true,
        ];
    }

    /**
     * @return array<int, array{name: string, type: string, data: string}>
     */
    public function getDns(string $domain): array
    {
        $zone = $this->sdk()->domains->zone($domain);
        $records = $zone->records ?? $zone->defaultRecords;
        $out = [];
        foreach ($records->entities as $rec) {
            $out[] = [
                'name' => $rec->name,
                'type' => $rec->type,
                'data' => $rec->content,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{name: string, type: string, data: string}>  $records
     * @return array<int, array{name: string, type: string, data: string}>
     */
    public function setDns(string $domain, array $records): array
    {
        $zone = $this->sdk()->domains->zone($domain);
        $payload = [];
        foreach ($records as $r) {
            $payload[] = [
                'name' => $r['name'],
                'type' => $r['type'],
                'content' => $r['data'],
                'ttl' => $zone->ttl,
            ];
        }
        $collection = DomainZoneRecordCollection::fromArray($payload);
        $this->sdk()->domains->zoneUpdate(
            $domain,
            $zone->hostMaster,
            $zone->refresh,
            $zone->retry,
            $zone->expire,
            $zone->ttl,
            $collection
        );

        return $this->getDns($domain);
    }

    /**
     * @return array{domain: string, nameserver: array<int, string>}
     */
    public function getNameserver(string $domain): array
    {
        $details = $this->sdk()->domains->get($domain);

        return [
            'domain' => $domain,
            'nameserver' => $details->ns,
        ];
    }

    /**
     * @param  array<int, string>  $nameservers
     * @return array{domain: string, nameserver: array<int, string>}
     */
    public function setNameserver(string $domain, array $nameservers): array
    {
        $this->sdk()->domains->update($domain, ns: array_values($nameservers));

        return $this->getNameserver($domain);
    }

    public function getAuthcode(string $domain): string
    {
        $details = $this->sdk()->domains->get($domain);

        return (string) ($details->authcode ?? '');
    }

    /**
     * @return array{productId: string, price: string, expireAt: string, deletedAt: string}
     */
    public function renewProduct(?string $productId = null, ?string $domain = null): array
    {
        $domainName = $domain ?? $productId;
        if ($domainName === null || $domainName === '') {
            throw new \InvalidArgumentException('Domain required for renew.');
        }

        $expiry = $this->sdk()->domains->renew($domainName, 1);

        return [
            'productId' => '',
            'price' => '0',
            'expireAt' => $expiry->format('Y-m-d\TH:i:s\Z'),
            'deletedAt' => '',
        ];
    }

    /**
     * @return array{productId: string, autoRenew: bool}
     */
    public function setAutoRenew(bool $autoRenew, ?string $productId = null, ?string $domain = null): array
    {
        $domainName = $domain ?? $productId;
        if ($domainName === null || $domainName === '') {
            throw new \InvalidArgumentException('Domain required.');
        }

        $this->sdk()->domains->update($domainName, autoRenew: $autoRenew);

        return [
            'productId' => '',
            'autoRenew' => $autoRenew,
        ];
    }

    /**
     * @return array{domain: string, contact: array<string, mixed>, privacy?: array}
     */
    public function getContact(string $domain): array
    {
        $details = $this->sdk()->domains->get($domain);
        $customer = trim((string) $this->brandExtensionService->realtimeregisterConfigForBrand($this->brand)['customer_handle']);
        $contact = $this->sdk()->contacts->get($customer, $details->registrant);

        $lines = $contact->addressLine;

        return [
            'domain' => $domain,
            'contact' => [
                'firstname' => $this->splitNameFirst($contact->name),
                'lastname' => $this->splitNameLast($contact->name),
                'street' => $lines[0] ?? '',
                'number' => '',
                'postcode' => $contact->postalCode,
                'city' => $contact->city,
                'state' => $contact->state ?? '',
                'country' => $contact->country,
                'email' => $contact->email,
                'phone' => $contact->voice,
                'company' => $contact->organization ?? '',
            ],
        ];
    }

    /**
     * @param  array{company?: string, firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string}  $contact
     */
    public function setContact(string $domain, array $contact): array
    {
        $c = $this->brandExtensionService->realtimeregisterConfigForBrand($this->brand);
        $customer = trim((string) ($c['customer_handle'] ?? ''));
        $details = $this->sdk()->domains->get($domain);
        $handle = 'c'.bin2hex(random_bytes(5));
        $name = trim($contact['firstname'].' '.$contact['lastname']);
        $organization = trim((string) ($contact['company'] ?? ''));
        $addressLine = [trim($contact['street'].' '.$contact['number'])];

        $this->sdk()->contacts->create(
            $customer,
            $handle,
            $name,
            $addressLine,
            $contact['postcode'],
            $contact['city'],
            strtoupper(substr($contact['country'], 0, 2)),
            $contact['email'],
            RealtimeRegisterPhone::toVoice($contact['phone'], $contact['country']),
            null,
            $organization !== '' ? $organization : null,
            $contact['state'] !== '' ? $contact['state'] : null,
        );

        $this->sdk()->domains->update($domain, registrant: $handle);

        return $this->getContact($domain);
    }

    /**
     * @return array{dnssec?: array{flags: int, algorithm: int, publicKey: string}|null}
     */
    public function getDnssec(string $domain): array
    {
        $details = $this->sdk()->domains->get($domain);
        if ($details->keyData === null || $details->keyData->entities === []) {
            return ['dnssec' => null];
        }
        $k = $details->keyData->entities[0];

        return [
            'dnssec' => [
                'flags' => $k->flags,
                'algorithm' => $k->algorithm,
                'publicKey' => $k->publicKey,
            ],
        ];
    }

    /**
     * @param  array{flags: int, algorithm: int, publicKey: string}  $dnssec
     */
    public function setDnssec(string $domain, array $dnssec): array
    {
        $key = KeyData::fromArray([
            'protocol' => 3,
            'flags' => $dnssec['flags'],
            'algorithm' => $dnssec['algorithm'],
            'publicKey' => $dnssec['publicKey'],
        ]);
        $this->sdk()->domains->update($domain, keyData: new KeyDataCollection([$key]));

        return $this->getDnssec($domain);
    }

    public function deleteDnssec(string $domain): array
    {
        $this->sdk()->domains->update($domain, keyData: new KeyDataCollection([]));

        return ['dnssec' => null];
    }

    /**
     * Map Skrime-style privacy flags to RR privacyProtect boolean (best effort).
     *
     * @param  array<string, bool>  $privacy
     */
    public function updateWhoisPrivacy(string $domain, array $privacy): array
    {
        $anyHidden = in_array(false, $privacy, true);
        $this->sdk()->domains->update($domain, privacyProtect: $anyHidden);

        return ['domain' => $domain, 'privacy' => $privacy];
    }

    protected function productToTld(string $product): string
    {
        if (! str_starts_with($product, 'domain_')) {
            return '';
        }

        return str_replace('_', '.', substr($product, strlen('domain_')));
    }

    protected function minorUnitToDecimal(int $minor, string $currency): float
    {
        $currency = strtoupper($currency);
        $zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array($currency, $zeroDecimal, true)) {
            return (float) $minor;
        }

        return round($minor / 100, 2);
    }

    protected function splitNameFirst(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), 2, PREG_SPLIT_NO_EMPTY);

        return $parts[0] ?? $name;
    }

    protected function splitNameLast(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), 2, PREG_SPLIT_NO_EMPTY);
        if (count($parts) >= 2) {
            return $parts[1];
        }

        return $parts[0] ?? '';
    }
}
