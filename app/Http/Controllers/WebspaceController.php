<?php

namespace App\Http\Controllers;

use App\Models\HostingPlan;
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

        return Inertia::render('webspace/Checkout', [
            'hostingPlans' => $plans,
            'selectedPlan' => $plan,
        ]);
    }

    /**
     * Store checkout session and redirect to Stripe (via checkout.redirect).
     */
    public function storeCheckout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hosting_plan_id' => ['required', 'exists:hosting_plans,id'],
            'domain' => ['required', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ], [
            'domain.regex' => 'Bitte eine gültige Domain angeben (z. B. example.com).',
        ]);

        $plan = HostingPlan::find($validated['hosting_plan_id']);
        if (! $plan || ! $plan->is_active) {
            return redirect()->route('webspace.checkout')->with('error', 'Paket nicht verfügbar.');
        }

        $request->session()->put('checkout_webspace', [
            'hosting_plan_id' => $plan->id,
            'domain' => $validated['domain'],
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('checkout.redirect');
    }
}
