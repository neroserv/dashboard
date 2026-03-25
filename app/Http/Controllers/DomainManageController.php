<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDomainWhoisRequest;
use App\Models\ResellerDomain;
use App\Services\DomainPricingService;
use App\Services\ResellerDomainRegistrarAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DomainManageController extends Controller
{
    protected function registrarAdapter(ResellerDomain $domain): ResellerDomainRegistrarAdapter
    {
        return ResellerDomainRegistrarAdapter::forDomain($domain);
    }

    protected function pricingFor(ResellerDomain $domain): DomainPricingService
    {
        return app(DomainPricingService::class)->forBrand($domain->brand);
    }

    public function show(Request $request, ResellerDomain $reseller_domain): InertiaResponse|RedirectResponse
    {
        $this->authorize('view', $reseller_domain);

        $nameservers = [];
        try {
            $result = $this->registrarAdapter($reseller_domain)->getNameserver();
            $nameservers = $result['nameserver'] ?? [];
        } catch (\Throwable) {
            // ignore
        }

        $renewPrice = null;
        if ($reseller_domain->tld) {
            $renewPrice = $this->pricingFor($reseller_domain)->getPricingForTld($reseller_domain->tld, 'renew')['sale_price'] ?? null;
        }

        $domain = [
            'uuid' => $reseller_domain->uuid,
            'domain' => $reseller_domain->domain,
            'status' => $reseller_domain->status,
            'expires_at' => $reseller_domain->expires_at?->format('d.m.Y'),
            'auto_renew' => $reseller_domain->auto_renew,
            'registrar' => $reseller_domain->registrar,
            'is_sandbox' => (bool) $reseller_domain->is_sandbox,
            'nameservers' => $nameservers,
            'renew_price' => $renewPrice,
            'show_rr_pending_validation_notice' => $reseller_domain->isRealtimeRegisterPendingValidation(),
        ];

        $easyDnsPresets = collect(config('domain-easy-dns.presets', []))
            ->map(fn (array $preset, string $id): array => array_merge($preset, ['id' => $id]))
            ->values()
            ->all();

        $canManageCollaborators = $request->user()->can('manageCollaborators', $reseller_domain);
        $productShares = [];
        $productInvitations = [];
        $allowedSharePermissions = [];
        $storeInvitationUrl = null;
        if ($canManageCollaborators) {
            $productShares = $reseller_domain->productShares()
                ->with('user:id,name,email')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'user' => $s->user ? ['id' => $s->user->id, 'name' => $s->user->name, 'email' => $s->user->email] : null,
                    'permissions' => $s->permissions ?? [],
                    'update_url' => route('domains.shares.update', [$reseller_domain, $s]),
                    'destroy_url' => route('domains.shares.destroy', [$reseller_domain, $s]),
                ])->all();
            $productInvitations = $reseller_domain->productInvitations()
                ->whereNull('accepted_at')->where('expires_at', '>', now())
                ->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'permissions' => $i->permissions ?? [],
                    'expires_at' => $i->expires_at?->toIso8601String(),
                    'destroy_url' => route('domains.invitations.destroy', [$reseller_domain, $i]),
                ])->all();
            $allowedSharePermissions = config('product-share-permissions.'.\App\Models\ResellerDomain::class, []);
            $storeInvitationUrl = route('domains.shares.invitations.store', $reseller_domain);
        }

        return Inertia::render('domains/Show', [
            'domain' => $domain,
            'domains_index_url' => route('domains.index'),
            'easy_dns_presets' => $easyDnsPresets,
            'canManageCollaborators' => $canManageCollaborators,
            'productShares' => $productShares,
            'productInvitations' => $productInvitations,
            'allowedSharePermissions' => $allowedSharePermissions,
            'storeInvitationUrl' => $storeInvitationUrl,
        ]);
    }

    public function authcode(ResellerDomain $reseller_domain): JsonResponse
    {
        $this->authorize('view', $reseller_domain);

        try {
            $code = $this->registrarAdapter($reseller_domain)->getAuthcode();

            return response()->json(['authcode' => $code]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateNameserver(
        \App\Http\Requests\UpdateDomainNameserverRequest $request,
        ResellerDomain $reseller_domain,
    ): RedirectResponse {
        try {
            $this->registrarAdapter($reseller_domain)->setNameserver($request->validated('nameservers'));
        } catch (\Throwable $e) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'Nameserver konnten nicht gesetzt werden: '.$e->getMessage());
        }

        return redirect()->route('domains.manage.show', $reseller_domain)
            ->with('success', 'Nameserver aktualisiert.');
    }

    public function dns(ResellerDomain $reseller_domain): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $reseller_domain);

        try {
            $records = $this->registrarAdapter($reseller_domain)->getDns();

            return response()->json(['records' => $records]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateDns(Request $request, ResellerDomain $reseller_domain): RedirectResponse
    {
        $this->authorize('update', $reseller_domain);

        $request->validate([
            'records' => ['required', 'array'],
            'records.*.name' => ['required', 'string', 'max:255'],
            'records.*.type' => ['required', 'string', 'in:A,AAAA,CNAME,ALIAS,MX,SRV,TXT,CAA,PTR,TLSA,DS,DNSKEY'],
            'records.*.data' => ['required', 'string', 'max:65535'],
        ]);

        $records = array_map(fn ($r) => [
            'name' => $r['name'],
            'type' => $r['type'],
            'data' => $r['data'],
        ], $request->input('records', []));

        try {
            $this->registrarAdapter($reseller_domain)->setDns($records);
        } catch (\Throwable $e) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'DNS-Zone konnte nicht gespeichert werden: '.$e->getMessage());
        }

        return redirect()->route('domains.manage.show', $reseller_domain)
            ->with('success', 'DNS-Zone aktualisiert.');
    }

    public function getDnssec(ResellerDomain $reseller_domain): JsonResponse
    {
        $this->authorize('view', $reseller_domain);

        try {
            $data = $this->registrarAdapter($reseller_domain)->getDnssec();

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function setDnssec(Request $request, ResellerDomain $reseller_domain): RedirectResponse
    {
        $this->authorize('update', $reseller_domain);

        $request->validate([
            'flags' => ['required', 'integer', 'in:256,257'],
            'algorithm' => ['required', 'integer', 'in:3,5,6,7,8,10,12,13,14,15,16,17,23'],
            'publicKey' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $this->registrarAdapter($reseller_domain)->setDnssec([
                'flags' => (int) $request->input('flags'),
                'algorithm' => (int) $request->input('algorithm'),
                'publicKey' => $request->input('publicKey'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'DNSSEC konnte nicht gesetzt werden: '.$e->getMessage());
        }

        return redirect()->route('domains.manage.show', $reseller_domain)
            ->with('success', 'DNSSEC aktiviert.');
    }

    public function deleteDnssec(ResellerDomain $reseller_domain): RedirectResponse
    {
        $this->authorize('update', $reseller_domain);

        try {
            $this->registrarAdapter($reseller_domain)->deleteDnssec();
        } catch (\Throwable $e) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'DNSSEC konnte nicht deaktiviert werden: '.$e->getMessage());
        }

        return redirect()->route('domains.manage.show', $reseller_domain)
            ->with('success', 'DNSSEC deaktiviert.');
    }

    public function renew(ResellerDomain $reseller_domain): RedirectResponse
    {
        $this->authorize('update', $reseller_domain);

        $adapter = $this->registrarAdapter($reseller_domain);
        if (! $adapter->canRenewWithSkrimeIdCheck()) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'Domain hat keine Skrime-ID.');
        }

        try {
            $data = $adapter->renewProduct();
            $reseller_domain->update([
                'expires_at' => isset($data['expireAt']) ? \Carbon\Carbon::parse($data['expireAt']) : null,
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'Verlängerung fehlgeschlagen: '.$e->getMessage());
        }

        return redirect()->route('domains.manage.show', $reseller_domain)
            ->with('success', 'Domain verlängert.');
    }

    public function setAutoRenew(Request $request, ResellerDomain $reseller_domain): RedirectResponse
    {
        $this->authorize('update', $reseller_domain);

        $request->validate(['auto_renew' => ['required', 'boolean']]);
        $enabled = (bool) $request->input('auto_renew');

        $adapter = $this->registrarAdapter($reseller_domain);
        if (! $adapter->canRenewWithSkrimeIdCheck()) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'Domain hat keine Skrime-ID.');
        }

        try {
            $adapter->setAutoRenew($enabled);
            $reseller_domain->update(['auto_renew' => $enabled]);
        } catch (\Throwable $e) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'Auto-Verlängerung konnte nicht gesetzt werden: '.$e->getMessage());
        }

        return redirect()->route('domains.manage.show', $reseller_domain)
            ->with('success', $enabled ? 'Auto-Verlängerung aktiviert.' : 'Auto-Verlängerung deaktiviert.');
    }

    public function getContact(ResellerDomain $reseller_domain): JsonResponse
    {
        $this->authorize('view', $reseller_domain);

        try {
            $data = $this->registrarAdapter($reseller_domain)->getContact();

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateContact(
        \App\Http\Requests\UpdateDomainContactRequest $request,
        ResellerDomain $reseller_domain,
    ): RedirectResponse {
        $contact = $request->validated('contact');
        $normalized = [
            'firstname' => trim((string) ($contact['firstname'] ?? '')),
            'lastname' => trim((string) ($contact['lastname'] ?? '')),
            'street' => trim((string) ($contact['street'] ?? '')),
            'number' => trim((string) ($contact['number'] ?? '')),
            'postcode' => trim((string) ($contact['postcode'] ?? '')),
            'city' => trim((string) ($contact['city'] ?? '')),
            'state' => trim((string) ($contact['state'] ?? '')),
            'country' => trim((string) ($contact['country'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
            'phone' => trim((string) ($contact['phone'] ?? '')),
        ];
        if (! empty(trim((string) ($contact['company'] ?? '')))) {
            $normalized['company'] = trim((string) $contact['company']);
        }

        try {
            $this->registrarAdapter($reseller_domain)->setContact($normalized);
        } catch (\Throwable $e) {
            return redirect()->route('domains.manage.show', $reseller_domain)
                ->with('error', 'Kontaktdaten konnten nicht gespeichert werden: '.$e->getMessage());
        }

        return redirect()->route('domains.manage.show', $reseller_domain)
            ->with('success', 'Kontaktdaten aktualisiert.');
    }

    public function updateWhoisPrivacy(
        UpdateDomainWhoisRequest $request,
        ResellerDomain $reseller_domain,
    ): JsonResponse {
        $privacy = $request->validated('privacy');

        try {
            $data = $this->registrarAdapter($reseller_domain)->updateWhoisPrivacy($privacy);

            return response()->json([
                'response' => 'Successfully updated whois settings',
                'state' => 'success',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get raw whois lookup result for the domain (public whois record).
     */
    public function getWhoisLookup(ResellerDomain $reseller_domain): JsonResponse
    {
        $this->authorize('view', $reseller_domain);

        $domain = $reseller_domain->domain;
        if (! preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $domain)) {
            return response()->json(['error' => 'Ungültiger Domainname'], 422);
        }

        $output = [];
        $returnCode = -1;
        @exec('whois '.escapeshellarg($domain).' 2>&1', $output, $returnCode);
        $text = implode("\n", $output);

        if ($text === '' && $returnCode !== 0) {
            return response()->json([
                'error' => 'Whois-Abfrage auf diesem Server nicht verfügbar (whois-Befehl fehlt oder fehlgeschlagen).',
                'whois' => '',
            ]);
        }

        return response()->json(['whois' => $text]);
    }
}
