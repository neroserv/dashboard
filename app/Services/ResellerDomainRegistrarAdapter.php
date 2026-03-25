<?php

namespace App\Services;

use App\Models\ResellerDomain;
use App\Support\DomainRegistrar;

/**
 * Dispatches domain operations to Skrime or Realtime Register based on {@see ResellerDomain::$registrar}.
 */
class ResellerDomainRegistrarAdapter
{
    public function __construct(
        protected ResellerDomain $domain,
        protected SkrimeApiService $skrime,
        protected RealtimeRegisterApiService $realtimeRegister,
    ) {}

    public static function forDomain(ResellerDomain $domain): self
    {
        $brand = $domain->brand ?? $domain->brand()->first();
        if ($brand === null) {
            throw new \RuntimeException('ResellerDomain has no brand.');
        }

        return new self(
            $domain,
            app(SkrimeApiService::class)->forBrand($brand),
            app(RealtimeRegisterApiService::class)->forBrand($brand),
        );
    }

    public function usesRealtimeRegister(): bool
    {
        return $this->domain->registrar === DomainRegistrar::REALTIME_REGISTER;
    }

    /**
     * @return array{domain: string, nameserver: array<int, string>}
     */
    public function getNameserver(): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->getNameserver($this->apiDomainName());
        }

        return $this->skrime->getNameserver($this->domain->domain);
    }

    /**
     * @param  array<int, string>  $nameservers
     */
    public function setNameserver(array $nameservers): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->setNameserver($this->apiDomainName(), $nameservers);
        }

        return $this->skrime->setNameserver($this->domain->domain, $nameservers);
    }

    /**
     * @return array<int, array{name: string, type: string, data: string}>
     */
    public function getDns(): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->getDns($this->apiDomainName());
        }

        return $this->skrime->getDns($this->domain->domain);
    }

    /**
     * @param  array<int, array{name: string, type: string, data: string}>  $records
     * @return array<int, array{name: string, type: string, data: string}>
     */
    public function setDns(array $records): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->setDns($this->apiDomainName(), $records);
        }

        return $this->skrime->setDns($this->domain->domain, $records);
    }

    public function getAuthcode(): string
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->getAuthcode($this->apiDomainName());
        }

        return $this->skrime->getAuthcode($this->domain->domain);
    }

    /**
     * @return array{productId: string, price: string, expireAt: string, deletedAt: string}
     */
    public function renewProduct(): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->renewProduct(domain: $this->apiDomainName());
        }

        return $this->skrime->renewProduct(domain: $this->domain->domain);
    }

    /**
     * @return array{productId: string, autoRenew: bool}
     */
    public function setAutoRenew(bool $enabled): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->setAutoRenew($enabled, domain: $this->apiDomainName());
        }

        return $this->skrime->setAutoRenew($enabled, domain: $this->domain->domain);
    }

    /**
     * @return array{domain: string, contact: array<string, mixed>, privacy?: array}
     */
    public function getContact(): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->getContact($this->apiDomainName());
        }

        return $this->skrime->getContact($this->domain->domain);
    }

    /**
     * @param  array{company?: string, firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string}  $contact
     */
    public function setContact(array $contact): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->setContact($this->apiDomainName(), $contact);
        }

        return $this->skrime->setContact($this->domain->domain, $contact);
    }

    /**
     * @return array{dnssec?: array{flags: int, algorithm: int, publicKey: string}|null}
     */
    public function getDnssec(): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->getDnssec($this->apiDomainName());
        }

        return $this->skrime->getDnssec($this->domain->domain);
    }

    /**
     * @param  array{flags: int, algorithm: int, publicKey: string}  $dnssec
     */
    public function setDnssec(array $dnssec): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->setDnssec($this->apiDomainName(), $dnssec);
        }

        return $this->skrime->setDnssec($this->domain->domain, $dnssec);
    }

    public function deleteDnssec(): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->deleteDnssec($this->apiDomainName());
        }

        return $this->skrime->deleteDnssec($this->domain->domain);
    }

    /**
     * @param  array<string, bool>  $privacy
     * @return array<string, mixed>
     */
    public function updateWhoisPrivacy(array $privacy): array
    {
        if ($this->usesRealtimeRegister()) {
            return $this->realtimeRegister->updateWhoisPrivacy($this->apiDomainName(), $privacy);
        }

        return $this->skrime->updateWhoisPrivacy($this->domain->domain, $privacy);
    }

    public function canRenewWithSkrimeIdCheck(): bool
    {
        if ($this->usesRealtimeRegister()) {
            return true;
        }

        return (bool) $this->domain->skrime_id;
    }

    protected function apiDomainName(): string
    {
        $n = $this->domain->realtimeregister_domain_name;

        return ($n !== null && $n !== '') ? $n : $this->domain->domain;
    }
}
