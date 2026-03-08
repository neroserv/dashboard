<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiTokenCheckoutRequest;
use App\Models\Brand;
use App\Services\MollieCustomerService;
use App\Support\MollieWebhookUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Mollie\Api\MollieApiClient;

class AiTokenController extends Controller
{
    public function checkout(AiTokenCheckoutRequest $request): Response|RedirectResponse
    {
        $currentBrand = $request->attributes->get('current_brand') ?? Brand::getDefault();
        $brandFeatures = $currentBrand?->getFeaturesArray() ?? [];
        if (! ($brandFeatures['ai_tokens'] ?? true)) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'AI-Tokens sind für diese Marke nicht aktiviert.');
        }

        $user = $request->user();
        $tokenAmount = (int) $request->validated('token_amount');

        $packages = config('billing.ai_token_packages', []);
        if (! is_array($packages) || ! array_key_exists($tokenAmount, $packages)) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'Dieses AI-Token-Paket ist derzeit nicht verfügbar.');
        }

        $priceEur = $packages[$tokenAmount];
        if ($priceEur === null || $priceEur === '') {
            return redirect()
                ->route('billing.index')
                ->with('error', 'Dieses AI-Token-Paket hat keinen Preis. Bitte AI_TOKENS_PRICE_* in .env prüfen.');
        }

        $amount = (float) $priceEur;
        if ($amount <= 0) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'Dieses AI-Token-Paket ist derzeit nicht verfügbar.');
        }

        try {
            $mollie = app(MollieApiClient::class);
            $currency = strtoupper(config('cashier.currency', 'eur'));
            $params = [
                'amount' => [
                    'currency' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'description' => 'AI-Token-Paket: '.number_format($tokenAmount, 0, ',', '.').' Tokens',
                'redirectUrl' => route('billing.index').'?ai_tokens=success',
                'metadata' => [
                    'type' => 'ai_tokens',
                    'user_id' => (string) $user->id,
                    'token_amount' => (string) $tokenAmount,
                ],
            ];
            $params['customerId'] = app(MollieCustomerService::class)->ensureCustomer($user);
            $webhookUrl = MollieWebhookUrl::get();
            if ($webhookUrl !== null) {
                $params['webhookUrl'] = $webhookUrl;
            }
            $payment = $mollie->payments->create($params);
            $checkoutUrl = $payment->getCheckoutUrl();
            if (! $checkoutUrl || ! str_starts_with($checkoutUrl, 'https://')) {
                return redirect()->route('billing.index')->with('error', 'Mollie Checkout lieferte keine gültige Weiterleitungs-URL.');
            }

            return Inertia::location($checkoutUrl);
        } catch (\Throwable $e) {
            Log::error('AI token checkout failed', [
                'user_id' => $user->id,
                'token_amount' => $tokenAmount,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return redirect()
                ->route('billing.index')
                ->with('error', 'Checkout konnte nicht gestartet werden. Bitte später erneut versuchen.');
        }
    }
}
