<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\HostingPlan;
use App\Models\HostingServer;
use App\Services\BalancePaymentService;
use App\Services\ControlPanels\PterodactylClient;
use App\Services\HostingPlanOptionSurchargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GamingController extends Controller
{
    /**
     * Format a hosting plan for the gaming frontend (Index/Checkout) so config and plan_options are always present.
     *
     * @return array{id: int, name: string, price: string, config: array}
     */
    protected static function planForFrontend(HostingPlan $plan): array
    {
        $config = $plan->config ?? [];
        if (! is_array($config)) {
            $config = [];
        }
        if (! isset($config['plan_options']) || ! is_array($config['plan_options'])) {
            $config['plan_options'] = [];
        }

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'price' => (string) $plan->price,
            'config' => $config,
        ];
    }

    protected function ensureGamingFeature(Request $request): ?RedirectResponse
    {
        $brand = $request->attributes->get('current_brand');
        $features = $brand?->getFeaturesArray() ?? [];

        if (! ($features['gaming'] ?? false)) {
            return redirect()->route('dashboard')->with('error', 'Game-Server werden für diese Marke nicht angeboten.');
        }

        return null;
    }

    /**
     * List active Pterodactyl (gaming) plans for the current brand.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $redirect = $this->ensureGamingFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $brand = $request->attributes->get('current_brand');
        $brandId = $brand?->id;

        $plans = HostingPlan::query()
            ->where('is_active', true)
            ->where('panel_type', 'pterodactyl')
            ->when($brandId !== null, fn ($q) => $q->where('brand_id', $brandId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('gaming/Index', [
            'hostingPlans' => $plans->map(fn (HostingPlan $p) => self::planForFrontend($p)),
        ]);
    }

    /**
     * Checkout form: plan selection and optional server name.
     */
    public function checkout(Request $request): Response|RedirectResponse
    {
        $redirect = $this->ensureGamingFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $brand = $request->attributes->get('current_brand');
        $brandId = $brand?->id;

        $planId = $request->query('plan');
        $plan = $planId ? HostingPlan::query()
            ->where('panel_type', 'pterodactyl')
            ->when($brandId !== null, fn ($q) => $q->where('brand_id', $brandId))
            ->find($planId) : null;
        if ($planId && ! $plan) {
            return redirect()->route('gaming.index')->with('error', 'Paket nicht gefunden.');
        }

        $plans = HostingPlan::query()
            ->where('is_active', true)
            ->where('panel_type', 'pterodactyl')
            ->when($brandId !== null, fn ($q) => $q->where('brand_id', $brandId))
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

        return Inertia::render('gaming/Checkout', $payload);
    }

    /**
     * Return nests for the plan's panel (for dynamic option in checkout). Used when plan has option with source pterodactyl_nests.
     */
    public function pterodactylNests(Request $request): JsonResponse
    {
        $redirect = $this->ensureGamingFeature($request);
        if ($redirect !== null) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $planId = $request->integer('hosting_plan_id');
        if ($planId < 1) {
            return response()->json(['nests' => []]);
        }

        $plan = HostingPlan::query()
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->find($planId);
        if (! $plan || ! $plan->hosting_server_id) {
            return response()->json(['nests' => []]);
        }

        $server = HostingServer::query()
            ->where('id', $plan->hosting_server_id)
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->first();
        if (! $server) {
            return response()->json(['nests' => []]);
        }

        try {
            $client = app(PterodactylClient::class);
            $client->setServer($server);
            $nestsRaw = $client->getNests();
            $nests = [];
            foreach ($nestsRaw as $n) {
                $attrs = is_array($n) ? ($n['attributes'] ?? $n) : (array) $n;
                $id = (int) ($attrs['id'] ?? 0);
                $name = (string) ($attrs['name'] ?? 'Nest '.$id);
                if ($id > 0) {
                    $nests[] = ['id' => $id, 'name' => $name];
                }
            }

            return response()->json(['nests' => $nests]);
        } catch (\Throwable) {
            return response()->json(['nests' => []]);
        }
    }

    /**
     * Return eggs for a nest (for dynamic option in checkout). Used when plan has option with source pterodactyl_eggs.
     */
    public function pterodactylEggs(Request $request): JsonResponse
    {
        $redirect = $this->ensureGamingFeature($request);
        if ($redirect !== null) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $planId = $request->integer('hosting_plan_id');
        $nestId = $request->integer('nest_id');
        if ($planId < 1 || $nestId < 1) {
            return response()->json(['eggs' => []]);
        }

        $plan = HostingPlan::query()
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->find($planId);
        if (! $plan || ! $plan->hosting_server_id) {
            return response()->json(['eggs' => []]);
        }

        $server = HostingServer::query()
            ->where('id', $plan->hosting_server_id)
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->first();
        if (! $server) {
            return response()->json(['eggs' => []]);
        }

        try {
            $client = app(PterodactylClient::class);
            $client->setServer($server);
            $eggsRaw = $client->getEggs($nestId);
            $eggs = [];
            foreach ($eggsRaw as $e) {
                $attrs = is_array($e) ? ($e['attributes'] ?? $e) : (array) $e;
                $id = (int) ($attrs['id'] ?? 0);
                $name = (string) ($attrs['name'] ?? 'Egg '.$id);
                if ($id > 0) {
                    $eggs[] = ['id' => $id, 'name' => $name];
                }
            }

            return response()->json(['eggs' => $eggs]);
        } catch (\Throwable) {
            return response()->json(['eggs' => []]);
        }
    }

    /**
     * Store checkout session and redirect to Stripe or process balance payment.
     */
    public function storeCheckout(Request $request): RedirectResponse
    {
        $redirect = $this->ensureGamingFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $validated = $request->validate([
            'hosting_plan_id' => ['required', 'exists:hosting_plans,id'],
            'server_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'in:stripe,balance'],
            'option_choices' => ['nullable', 'array'],
            'option_choices.*' => ['nullable'],
        ]);

        $plan = HostingPlan::find($validated['hosting_plan_id']);
        if (! $plan || ! $plan->is_active || $plan->panel_type !== 'pterodactyl') {
            return redirect()->route('gaming.checkout')->with('error', 'Paket nicht verfügbar.');
        }

        $brand = $request->attributes->get('current_brand');
        if ($brand !== null && $plan->brand_id !== $brand->id) {
            return redirect()->route('gaming.checkout')->with('error', 'Paket nicht verfügbar.');
        }

        $optionSurchargeService = app(HostingPlanOptionSurchargeService::class);
        $optionChoices = $optionSurchargeService->validateOptionChoices($plan, $validated['option_choices'] ?? []);
        $surcharge = $optionSurchargeService->computeSurcharge($plan, $optionChoices);
        $basePrice = (float) $plan->price;
        $totalAmount = round($basePrice + $surcharge, 2);

        $currentBrand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];
        $paymentMethod = $validated['payment_method'] ?? 'stripe';

        if ($paymentMethod === 'balance' && ($brandFeatures['prepaid_balance'] ?? false)) {
            $user = $request->user();
            try {
                app(BalancePaymentService::class)->pay($user, $totalAmount, 'game_server', 'Game-Server: '.$plan->name, [
                    'description' => 'Game-Server '.$plan->name.' – '.($validated['server_name'] ?? $plan->name),
                ]);
            } catch (InsufficientBalanceException $e) {
                return redirect()->route('gaming.checkout', ['plan' => $plan->id])
                    ->with('error', $e->getMessage());
            }

            $payload = [
                'hosting_plan_id' => $plan->id,
                'user_id' => $user->id,
                'server_name' => $validated['server_name'] ?? null,
                'option_choices' => $optionChoices,
            ];
            $request->session()->forget('checkout_gaming');

            return app(CheckoutController::class)->processGamingBalanceCheckout($request, $user, $payload);
        }

        $payload = [
            'hosting_plan_id' => $plan->id,
            'user_id' => $request->user()->id,
            'server_name' => $validated['server_name'] ?? null,
            'option_choices' => $optionChoices,
            'option_surcharge' => $surcharge,
            'total_amount' => $totalAmount,
        ];
        $request->session()->put('checkout_gaming', $payload);

        return app(CheckoutController::class)->buildGamingCheckoutRedirect($request, $payload);
    }
}
