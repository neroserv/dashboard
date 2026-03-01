<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\HostingPlan;
use App\Services\BalancePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebspaceController extends Controller
{
    /**
     * List active webspace plans for customers.
     */
    public function index(): Response
    {
        $plans = HostingPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('webspace/Index', [
            'hostingPlans' => $plans,
        ]);
    }

    /**
     * Checkout form: domain + plan (plan pre-selected via query).
     */
    public function checkout(Request $request): Response|RedirectResponse
    {
        $planId = $request->query('plan');
        $plan = $planId ? HostingPlan::find($planId) : null;
        if ($planId && ! $plan) {
            return redirect()->route('webspace.index')->with('error', 'Paket nicht gefunden.');
        }

        $plans = HostingPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $payload = [
            'hostingPlans' => $plans,
            'selectedPlan' => $plan,
        ];

        $currentBrand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];
        if ($plan && ($brandFeatures['prepaid_balance'] ?? false)) {
            $customerBalance = CustomerBalance::where('user_id', $request->user()->id)->first();
            $payload['canPayWithBalance'] = true;
            $payload['customerBalance'] = $customerBalance ? (float) $customerBalance->balance : 0.0;
            $payload['amountRequired'] = (float) $plan->price;
        }

        return Inertia::render('webspace/Checkout', $payload);
    }

    /**
     * Store checkout session and redirect to Stripe or process balance payment.
     */
    public function storeCheckout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hosting_plan_id' => ['required', 'exists:hosting_plans,id'],
            'domain' => ['required', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
            'payment_method' => ['nullable', 'string', 'in:stripe,balance'],
        ], [
            'domain.regex' => 'Bitte eine gültige Domain angeben (z. B. example.com).',
        ]);

        $plan = HostingPlan::find($validated['hosting_plan_id']);
        if (! $plan || ! $plan->is_active) {
            return redirect()->route('webspace.checkout')->with('error', 'Paket nicht verfügbar.');
        }

        $currentBrand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];
        $paymentMethod = $validated['payment_method'] ?? 'stripe';

        if ($paymentMethod === 'balance' && ($brandFeatures['prepaid_balance'] ?? false)) {
            $user = $request->user();
            $amount = (float) $plan->price;
            try {
                app(BalancePaymentService::class)->pay($user, $amount, 'webspace', 'Webspace: '.$plan->name, [
                    'description' => 'Webspace '.$plan->name.' – '.$validated['domain'].' (1 Monat)',
                ]);
            } catch (InsufficientBalanceException $e) {
                return redirect()->route('webspace.checkout', ['plan' => $plan->id])
                    ->with('error', $e->getMessage());
            }

            $payload = [
                'hosting_plan_id' => $plan->id,
                'domain' => $validated['domain'],
                'user_id' => $user->id,
            ];
            $request->session()->forget('checkout_webspace');

            return app(CheckoutController::class)->processWebspaceBalanceCheckout($request, $user, $payload);
        }

        $payload = [
            'hosting_plan_id' => $plan->id,
            'domain' => $validated['domain'],
            'user_id' => $request->user()->id,
        ];
        $request->session()->put('checkout_webspace', $payload);

        return app(CheckoutController::class)->buildWebspaceCheckoutRedirect($request, $payload);
    }
}
