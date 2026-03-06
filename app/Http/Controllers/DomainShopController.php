<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\CheckDomainAvailabilityRequest;
use App\Http\Requests\DomainCheckoutRequest;
use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\ResellerDomain;
use App\Services\BalancePaymentService;
use App\Services\DomainPricingService;
use App\Services\InvoicePdfService;
use App\Services\SkrimeApiService;
use App\Services\SkrimeContactService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Stripe\Checkout\Session as StripeSession;
use Stripe\StripeClient;

class DomainShopController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $domains = $request->user()
            ->resellerDomains()
            ->latest('created_at')
            ->get()
            ->map(fn (ResellerDomain $d) => array_merge($d->only(['id', 'domain', 'status', 'expires_at', 'auto_renew']), [
                'expires_at' => $d->expires_at?->format('d.m.Y'),
            ]));

        return Inertia::render('domains/Index', [
            'domains' => $domains,
        ]);
    }

    public function search(Request $request): InertiaResponse
    {
        return Inertia::render('domains/Search', [
            'csrf_token' => $request->session()->token(),
        ]);
    }

    public function checkAvailability(CheckDomainAvailabilityRequest $request, SkrimeApiService $skrime, DomainPricingService $pricing): JsonResponse
    {
        $baseName = strtolower(trim($request->input('domain')));
        $tlds = $request->input('tlds', ['de', 'com', 'net', 'io']);
        $results = [];

        foreach ($tlds as $tld) {
            $tld = strtolower(ltrim($tld, '.'));
            $domain = $baseName.'.'.$tld;
            try {
                $check = $skrime->checkAvailability($domain);
                $createInfo = $pricing->getPricingForTld($tld, 'create');
                $transferInfo = $pricing->getPricingForTld($tld, 'transfer');
                $results[] = [
                    'domain' => $domain,
                    'available' => $check['available'],
                    'premium' => $check['premium'],
                    'sale_price' => $createInfo['sale_price'],
                    'purchase_price' => $createInfo['purchase_price'],
                    'transfer_sale_price' => $transferInfo['sale_price'],
                ];
            } catch (\Throwable) {
                $results[] = [
                    'domain' => $domain,
                    'available' => false,
                    'premium' => false,
                    'sale_price' => 0,
                    'purchase_price' => 0,
                    'transfer_sale_price' => 0,
                    'error' => true,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function checkout(Request $request): InertiaResponse|RedirectResponse
    {
        $domain = $request->query('domain');
        $salePrice = $request->query('sale_price');
        $tld = $request->query('tld');
        $isTransfer = $request->boolean('transfer');
        if (! $domain || $salePrice === null || $salePrice === '') {
            return redirect()->route('domains.search')->with('error', 'Bitte Domain und Preis aus der Suche wählen.');
        }

        $user = $request->user();
        $profileContact = app(SkrimeContactService::class)->fromUserForDisplay($user);

        $payload = [
            'domain' => $domain,
            'sale_price' => (float) $salePrice,
            'tld' => $tld ?? '',
            'transfer' => $isTransfer,
            'profile_contact' => $profileContact,
            'tosUrl' => config('billing.tos_url', '#'),
            'privacyUrl' => config('billing.privacy_url', '#'),
        ];

        $currentBrand = $request->attributes->get('current_brand') ?? \App\Models\Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];
        if ($brandFeatures['prepaid_balance'] ?? false) {
            $customerBalance = CustomerBalance::where('user_id', $user->id)->first();
            $payload['canPayWithBalance'] = true;
            $payload['customerBalance'] = $customerBalance ? (float) $customerBalance->balance : 0.0;
            $payload['amountRequired'] = (float) $salePrice;
        }

        return Inertia::render('domains/Checkout', $payload);
    }

    public function storeCheckout(DomainCheckoutRequest $request, SkrimeApiService $skrime, DomainPricingService $pricing): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (ResellerDomain::where('domain', $data['domain'])->exists()) {
            return redirect()->route('domains.search')->with('error', 'Diese Domain ist bereits registriert.');
        }

        if ($data['use_profile_contact'] && ! $user->hasCompleteDomainContact()) {
            return redirect()->route('profile.edit')->with('error', 'Bitte vervollständigen Sie unter Einstellungen alle Kontaktdaten für die Domain-Registrierung: Name, Straße, Hausnummer, PLZ, Ort, Bundesland (State), Land, E-Mail, Telefon.');
        }

        $contact = $data['use_profile_contact']
            ? app(SkrimeContactService::class)->fromUser($user)
            : $this->normalizeContact($data['contact'] ?? []);

        $salePrice = (float) $data['sale_price'];
        $currentBrand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];
        $paymentMethod = $data['payment_method'] ?? 'stripe';

        if ($paymentMethod === 'balance' && ($brandFeatures['prepaid_balance'] ?? false)) {
            try {
                app(BalancePaymentService::class)->pay($user, $salePrice, 'domain_purchase', 'Domain-Registrierung: '.$data['domain'], [
                    'description' => 'Domain-Registrierung '.$data['domain'],
                ]);
            } catch (InsufficientBalanceException $e) {
                return redirect()->route('domains.checkout', ['domain' => $data['domain'], 'sale_price' => $salePrice, 'tld' => $data['tld'] ?? ''])
                    ->with('error', $e->getMessage());
            }

            if (! config('skrime.api_key') || ! config('skrime.base_url')) {
                Log::warning('Domain checkout balance: Skrime API nicht konfiguriert.');

                return redirect()->route('domains.index')->with('error', 'Skrime API ist nicht konfiguriert. Bitte kontaktieren Sie uns.');
            }

            $domain = $data['domain'];
            $nameservers = config('skrime.default_nameservers', []);
            $isTransfer = ! empty($data['transfer']);
            $authCode = $isTransfer ? trim((string) ($data['auth_code'] ?? '')) : null;

            try {
                $orderData = $skrime->orderDomain($domain, $contact, $nameservers, $authCode);
            } catch (\Throwable $e) {
                Log::error('Domain checkout balance: Skrime orderDomain fehlgeschlagen.', [
                    'domain' => $domain,
                    'message' => $e->getMessage(),
                ]);

                return redirect()->route('domains.index')->with('error', 'Domain-Bestellung bei Skrime fehlgeschlagen: '.$e->getMessage());
            }

            $expiresAt = isset($orderData['expireAt']) ? Carbon::parse($orderData['expireAt']) : null;
            ResellerDomain::create([
                'domain' => $data['domain'],
                'user_id' => $user->id,
                'skrime_id' => $orderData['id'] ?? null,
                'status' => $orderData['state'] ?? 'active',
                'registered_at' => now(),
                'expires_at' => $expiresAt,
                'auto_renew' => (bool) ($orderData['autoRenew'] ?? false),
                'purchase_price' => (float) ($data['purchase_price'] ?? 0),
                'sale_price' => $salePrice,
                'tld' => $data['tld'] ?? null,
            ]);

            return redirect()->route('domains.index')->with('success', 'Domain '.$data['domain'].' wurde registriert.');
        }

        $token = Str::random(32);
        $request->session()->put('domain_checkout_'.$token, [
            'domain' => $data['domain'],
            'tld' => $data['tld'] ?? null,
            'sale_price' => $salePrice,
            'purchase_price' => (float) ($data['purchase_price'] ?? 0),
            'contact' => $contact,
            'user_id' => $user->id,
            'transfer' => ! empty($data['transfer']),
            'auth_code' => ! empty($data['transfer']) ? trim((string) ($data['auth_code'] ?? '')) : null,
        ]);

        try {
            $stripe = new StripeClient(config('cashier.secret'));
            $params = [
                'mode' => StripeSession::MODE_PAYMENT,
                'success_url' => route('domains.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('domains.checkout', array_filter(['domain' => $data['domain'], 'sale_price' => $salePrice, 'tld' => $data['tld'] ?? '', 'transfer' => ! empty($data['transfer']) ? 1 : null])),
                'metadata' => [
                    'domain_checkout_token' => $token,
                    'user_id' => (string) $user->id,
                ],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => config('cashier.currency', 'eur'),
                            'unit_amount' => (int) round($salePrice * 100),
                            'product_data' => [
                                'name' => 'Domain-Registrierung: '.$data['domain'],
                                'description' => 'Registrierung der Domain '.$data['domain'],
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
            ];
            if ($user->mollie_customer_id) {
                $params['customer'] = $user->mollie_customer_id;
            } else {
                $params['customer_email'] = $user->email;
            }
            $session = $stripe->checkout->sessions->create($params);
            $stripeUrl = $session->url ?? null;
            if (! $stripeUrl) {
                $request->session()->forget('domain_checkout_'.$token);

                return redirect()->route('domains.checkout', request()->only('domain', 'sale_price', 'tld'))
                    ->with('error', 'Stripe Checkout lieferte keine Weiterleitungs-URL.');
            }

            $request->session()->put('domain_checkout_'.$token, array_merge(
                $request->session()->get('domain_checkout_'.$token, []),
                ['stripe_url' => $stripeUrl]
            ));

            return redirect()->route('domains.checkout.redirect', ['token' => $token]);
        } catch (\Throwable $e) {
            $request->session()->forget('domain_checkout_'.$token);

            return redirect()->route('domains.checkout', request()->only('domain', 'sale_price', 'tld'))
                ->with('error', 'Stripe Checkout konnte nicht gestartet werden. '.$e->getMessage());
        }
    }

    /**
     * Redirect to Stripe Checkout URL (GET). Used after storeCheckout so the browser
     * receives 409 + X-Inertia-Location and navigates to Stripe reliably.
     * In local env, shows a choice page: go to Stripe or simulate payment (no webhook).
     */
    public function redirectToStripe(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response|InertiaResponse
    {
        $token = $request->query('token');
        if (! $token || ! is_string($token)) {
            return redirect()->route('domains.checkout')->with('error', 'Checkout-Link ungültig oder abgelaufen.');
        }

        $payload = $request->session()->get('domain_checkout_'.$token);
        if (! $payload || ! isset($payload['stripe_url']) || ($payload['user_id'] ?? null) != $request->user()?->id) {
            return redirect()->route('domains.checkout')->with('error', 'Checkout-Daten ungültig oder abgelaufen.');
        }

        $stripeUrl = $payload['stripe_url'];
        unset($payload['stripe_url']);
        $request->session()->put('domain_checkout_'.$token, $payload);

        if (app()->environment('local')) {
            return Inertia::render('domains/CheckoutRedirect', [
                'token' => $token,
                'stripeUrl' => $stripeUrl,
                'domain' => $payload['domain'] ?? '',
            ]);
        }

        return Inertia::location($stripeUrl);
    }

    /**
     * Dev only: complete domain order without Stripe (simulate payment). Use in local when no webhook.
     */
    public function devCompleteCheckout(Request $request, InvoicePdfService $pdfService): RedirectResponse
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $token = $request->query('token');
        if (! $token || ! is_string($token)) {
            return redirect()->route('domains.index')->with('error', 'Token fehlt.');
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Bitte melden Sie sich an.');
        }

        $payload = $request->session()->get('domain_checkout_'.$token);
        $request->session()->forget('domain_checkout_'.$token);
        if (! $payload || ($payload['user_id'] ?? null) != $user->id) {
            return redirect()->route('domains.index')->with('error', 'Checkout-Daten abgelaufen oder ungültig.');
        }

        if (! config('skrime.api_key') || ! config('skrime.base_url')) {
            Log::warning('Domain checkout: Skrime API nicht konfiguriert (SKRIME_API_TOKEN / SKRIME_API_URL in .env).');

            return redirect()->route('domains.index')->with('error', 'Skrime API ist nicht konfiguriert. Bitte SKRIME_API_TOKEN und SKRIME_API_URL in .env setzen.');
        }

        $skrime = app(SkrimeApiService::class);
        $domain = $payload['domain'];
        $nameservers = config('skrime.default_nameservers', []);
        $authCode = ! empty($payload['transfer']) ? ($payload['auth_code'] ?? null) : null;
        Log::info('Domain checkout: rufe Skrime orderDomain auf.', ['domain' => $domain, 'nameservers_count' => count($nameservers), 'transfer' => ! empty($payload['transfer'])]);

        try {
            $orderData = $skrime->orderDomain(
                $domain,
                $payload['contact'],
                $nameservers,
                $authCode
            );
            Log::info('Domain checkout: Skrime orderDomain erfolgreich.', ['domain' => $domain, 'response_keys' => array_keys($orderData)]);
        } catch (\Throwable $e) {
            Log::error('Domain checkout: Skrime orderDomain fehlgeschlagen.', [
                'domain' => $domain,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('domains.index')->with('error', 'Domain-Bestellung bei Skrime fehlgeschlagen: '.$e->getMessage());
        }

        $expiresAt = isset($orderData['expireAt']) ? Carbon::parse($orderData['expireAt']) : null;
        ResellerDomain::create([
            'domain' => $payload['domain'],
            'user_id' => $user->id,
            'skrime_id' => $orderData['id'] ?? null,
            'status' => $orderData['state'] ?? 'active',
            'registered_at' => now(),
            'expires_at' => $expiresAt,
            'auto_renew' => (bool) ($orderData['autoRenew'] ?? false),
            'purchase_price' => $payload['purchase_price'] ?? null,
            'sale_price' => $payload['sale_price'] ?? null,
            'tld' => $payload['tld'] ?? null,
        ]);

        $invoice = $this->createDomainInvoice($user->id, $payload['domain'], $payload['sale_price'], $pdfService);
        if ($invoice) {
            $invoice->update(['status' => 'paid']);
        }

        return redirect()->route('domains.index')->with('success', 'Domain '.$payload['domain'].' wurde registriert (Dev: Zahlung simuliert).');
    }

    public function checkoutSuccess(Request $request, InvoicePdfService $pdfService): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        if (! $sessionId) {
            return redirect()->route('domains.index')->with('error', 'Checkout-Session nicht gefunden.');
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Bitte melden Sie sich an.');
        }

        try {
            $stripe = new StripeClient(config('cashier.secret'));
            $session = $stripe->checkout->sessions->retrieve($sessionId);
        } catch (\Throwable $e) {
            return redirect()->route('domains.index')->with('error', 'Stripe-Session konnte nicht geladen werden.');
        }

        if ($session->payment_status !== 'paid') {
            return redirect()->route('domains.index')->with('error', 'Zahlung wurde nicht abgeschlossen.');
        }

        $token = $session->metadata->domain_checkout_token ?? null;
        if (! $token) {
            return redirect()->route('domains.index')->with('error', 'Ungültige Checkout-Session.');
        }

        $payload = $request->session()->get('domain_checkout_'.$token);
        $request->session()->forget('domain_checkout_'.$token);
        if (! $payload || ($payload['user_id'] ?? null) != $user->id) {
            return redirect()->route('domains.index')->with('error', 'Checkout-Daten abgelaufen oder ungültig.');
        }

        if (! config('skrime.api_key') || ! config('skrime.base_url')) {
            Log::warning('Domain checkout: Skrime API nicht konfiguriert (SKRIME_API_TOKEN / SKRIME_API_URL in .env).');

            return redirect()->route('domains.index')->with('error', 'Skrime API ist nicht konfiguriert. Bitte SKRIME_API_TOKEN und SKRIME_API_URL in .env setzen.');
        }

        $domain = $payload['domain'];
        $nameservers = config('skrime.default_nameservers', []);
        $authCode = ! empty($payload['transfer']) ? ($payload['auth_code'] ?? null) : null;
        Log::info('Domain checkout: rufe Skrime orderDomain auf.', ['domain' => $domain, 'nameservers_count' => count($nameservers), 'transfer' => ! empty($payload['transfer'])]);

        try {
            $skrime = app(SkrimeApiService::class);
            $orderData = $skrime->orderDomain(
                $domain,
                $payload['contact'],
                $nameservers,
                $authCode
            );
            Log::info('Domain checkout: Skrime orderDomain erfolgreich.', ['domain' => $domain, 'response_keys' => array_keys($orderData)]);
        } catch (\Throwable $e) {
            Log::error('Domain checkout: Skrime orderDomain fehlgeschlagen.', [
                'domain' => $domain,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('domains.index')->with('error', 'Domain-Bestellung bei Skrime fehlgeschlagen: '.$e->getMessage());
        }

        $expiresAt = isset($orderData['expireAt']) ? Carbon::parse($orderData['expireAt']) : null;
        ResellerDomain::create([
            'domain' => $payload['domain'],
            'user_id' => $user->id,
            'skrime_id' => $orderData['id'] ?? null,
            'status' => $orderData['state'] ?? 'active',
            'registered_at' => now(),
            'expires_at' => $expiresAt,
            'auto_renew' => (bool) ($orderData['autoRenew'] ?? false),
            'purchase_price' => $payload['purchase_price'] ?? null,
            'sale_price' => $payload['sale_price'] ?? null,
            'tld' => $payload['tld'] ?? null,
        ]);

        $invoice = $this->createDomainInvoice($user->id, $payload['domain'], $payload['sale_price'], $pdfService);
        if ($invoice) {
            $invoice->update(['status' => 'paid']);
        }

        return redirect()->route('domains.index')->with('success', 'Domain '.$payload['domain'].' wurde registriert.');
    }

    /**
     * Normalize contact for Skrime. All required fields must be non-empty.
     *
     * @param  array<string, mixed>  $contact
     * @return array{firstname: string, lastname: string, street: string, number: string, postcode: string, city: string, state: string, country: string, email: string, phone: string, company?: string}
     */
    private function normalizeContact(array $contact): array
    {
        $required = ['firstname', 'lastname', 'street', 'number', 'postcode', 'city', 'state', 'country', 'email', 'phone'];
        $out = [];
        foreach ($required as $key) {
            $value = trim((string) ($contact[$key] ?? ''));
            if ($value === '') {
                throw new \RuntimeException('Kontakt: Feld "'.$key.'" darf nicht leer sein.');
            }
            $out[$key] = $value;
        }
        if (trim((string) ($contact['company'] ?? '')) !== '') {
            $out['company'] = trim((string) $contact['company']);
        }

        return $out;
    }

    private function createDomainInvoice(int $userId, string $domain, float $amount, InvoicePdfService $pdfService): ?Invoice
    {
        $year = date('Y');
        $nextSeq = (int) Invoice::whereYear('invoice_date', $year)->max('id') + 1;
        $number = 'INV-'.$year.'-'.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'user_id' => $userId,
            'number' => $number,
            'type' => 'domain_purchase',
            'amount' => $amount,
            'tax' => 0,
            'status' => 'draft',
            'invoice_date' => now(),
        ]);

        InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'position' => 1,
            'description' => 'Domain-Registrierung '.$domain,
            'quantity' => 1,
            'unit' => 'Stück',
            'unit_price' => $amount,
            'amount' => $amount,
        ]);

        $pdfPath = $pdfService->generate($invoice->fresh(['user', 'lineItems']));
        if ($pdfPath) {
            $invoice->update(['pdf_path' => $pdfPath]);
        }

        return $invoice;
    }
}
