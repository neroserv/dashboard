<?php

namespace App\Http\Controllers;

use App\Models\BalanceTransaction;
use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Services\AiTokenService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeSession;
use Stripe\StripeClient;

class BillingController extends Controller
{
    public function index(Request $request, AiTokenService $aiTokenService): Response
    {
        $user = $request->user();

        $packages = config('billing.ai_token_packages', []);
        $aiTokenPackages = [];
        foreach ($packages as $amount => $priceEur) {
            if ($priceEur !== null && $priceEur !== '') {
                $eur = (float) $priceEur;
                $aiTokenPackages[] = [
                    'amount' => $amount,
                    'label' => number_format($amount, 0, ',', '.').' Tokens ('.number_format($eur, 2, ',', '.').' €)',
                ];
            }
        }

        $invoices = $user->invoices()
            ->latest('invoice_date')
            ->limit(50)
            ->get(['id', 'number', 'amount', 'status', 'invoice_date', 'pdf_path', 'invoice_xml_path'])
            ->map(fn ($inv) => array_merge($inv->toArray(), [
                'invoice_date' => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d.m.Y') : null,
            ]));

        $paymentMethodSummary = null;
        if ($user->hasDefaultPaymentMethod()) {
            $paymentMethodSummary = [
                'brand' => $user->pm_type,
                'last4' => $user->pm_last_four,
            ];
        }

        $payload = [
            'invoices' => $invoices,
            'billingPortalUrl' => route('billing.portal'),
            'paymentMethodSummary' => $paymentMethodSummary,
        ];

        $currentBrand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];

        if ($brandFeatures['ai_tokens'] ?? true) {
            $payload['aiTokensEnabled'] = true;
            $payload['aiTokenBalance'] = $aiTokenService->getBalance($user);
            $payload['aiTokenPackages'] = $aiTokenPackages;
        } else {
            $payload['aiTokensEnabled'] = false;
            $payload['aiTokenBalance'] = 0;
            $payload['aiTokenPackages'] = [];
        }

        if ($brandFeatures['prepaid_balance'] ?? false) {
            $customerBalance = CustomerBalance::where('user_id', $user->id)->first();
            $payload['prepaidEnabled'] = true;
            $payload['customerBalance'] = $customerBalance ? (float) $customerBalance->balance : 0.0;
            $payload['balanceTransactions'] = BalanceTransaction::where('user_id', $user->id)
                ->latest()
                ->limit(20)
                ->get(['id', 'amount', 'type', 'description', 'created_at'])
                ->map(fn (BalanceTransaction $tx) => [
                    'id' => $tx->id,
                    'amount' => (float) $tx->amount,
                    'type' => $tx->type,
                    'description' => $tx->description,
                    'created_at' => $tx->created_at->format('d.m.Y H:i'),
                ])
                ->values()
                ->all();
        }

        if ($brandFeatures['balance_topup'] ?? false) {
            $payload['balanceTopUpEnabled'] = true;
            $payload['balanceTopUpMinAmount'] = (float) config('billing.balance_topup_min_amount', 5);
            $payload['balanceCheckoutUrl'] = route('billing.balance.checkout');
        }

        return Inertia::render('billing/Index', $payload);
    }

    public function balanceCheckout(\App\Http\Requests\BalanceCheckoutRequest $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
    {
        $currentBrand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];
        if (! ($brandFeatures['balance_topup'] ?? false)) {
            return redirect()->route('billing.index')->with('error', 'Guthaben-Aufladung ist für diese Marke nicht aktiviert.');
        }

        $user = $request->user();
        $amount = (float) $request->validated('amount');
        $amountCents = (int) round($amount * 100);

        try {
            $stripe = new StripeClient(config('cashier.secret'));
            $params = [
                'mode' => StripeSession::MODE_PAYMENT,
                'success_url' => route('billing.index').'?balance=success',
                'cancel_url' => route('billing.index'),
                'metadata' => [
                    'balance_topup' => '1',
                    'user_id' => (string) $user->id,
                    'amount_eur' => (string) $amount,
                ],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => config('cashier.currency', 'eur'),
                            'unit_amount' => $amountCents,
                            'product_data' => [
                                'name' => 'Guthaben aufladen',
                                'description' => 'Aufladung Ihres Kontoguthabens um '.number_format($amount, 2, ',', '.').' €',
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
            ];
            if ($user->stripe_id) {
                $params['customer'] = $user->stripe_id;
            } else {
                $params['customer_email'] = $user->email;
            }
            $session = $stripe->checkout->sessions->create($params);
            $stripeUrl = $session->url ?? null;
            if (! $stripeUrl || ! str_starts_with($stripeUrl, 'https://')) {
                return redirect()->route('billing.index')->with('error', 'Stripe Checkout lieferte keine gültige Weiterleitungs-URL.');
            }

            return Inertia::location($stripeUrl);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('billing.index')->with('error', 'Checkout konnte nicht gestartet werden. Bitte später erneut versuchen.');
        }
    }
}
