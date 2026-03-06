<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;

class MolliePaymentMethodsService
{
    private const CACHE_KEY = 'mollie_enabled_payment_methods';

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        protected MollieApiClient $mollie
    ) {}

    /**
     * Returns payment methods enabled for your Mollie account (Methods API).
     *
     * Uses Mollie API GET /v2/methods: lists only methods that are activated for your
     * account. Optional amount/currency filters so only methods valid for that amount
     * are returned. Labels use config overrides or the API response "description".
     *
     * @see https://docs.mollie.com/reference/methods-api (GET /v2/methods)
     * @return array<int, array{type: string, label: string}>
     */
    public function getEnabledPaymentMethods(): array
    {
        $key = config('mollie.key');
        if (! $key || $key === '') {
            return $this->fallbackMethods();
        }

        if (app()->environment('testing')) {
            return $this->fallbackMethods();
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            try {
                // Mollie API GET /v2/methods – methods enabled for your account (SDK: methods->all() / allActive())
                $methods = $this->mollie->methods->all([
                    'locale' => config('cashier.locale') ?? 'de_DE',
                    'amount' => [
                        'currency' => strtoupper(config('cashier.currency', 'eur')),
                        'value' => '10.00',
                    ],
                ]);
                $labels = config('billing.mollie_payment_method_labels', []);
                $result = [];
                foreach ($methods as $method) {
                    $type = $method->id;
                    $label = $labels[$type] ?? $method->description ?? $this->humanizeType($type);
                    $result[] = ['type' => $type, 'label' => $label];
                }

                return $result !== [] ? $result : $this->fallbackMethods();
            } catch (ApiException $e) {
                Log::warning('Mollie payment methods could not be fetched', [
                    'message' => $e->getMessage(),
                ]);

                return $this->fallbackMethods();
            }
        });
    }

    /**
     * @return array<int, array{type: string, label: string}>
     */
    private function fallbackMethods(): array
    {
        $labels = config('billing.mollie_payment_method_labels', []);

        $types = ['ideal', 'creditcard', 'paypal', 'bancontact', 'sofort', 'directdebit'];
        $result = [];
        foreach ($types as $type) {
            if (isset($labels[$type])) {
                $result[] = ['type' => $type, 'label' => $labels[$type]];
            }
        }

        return $result !== [] ? $result : [
            ['type' => 'ideal', 'label' => 'iDEAL'],
            ['type' => 'creditcard', 'label' => 'Karte'],
            ['type' => 'paypal', 'label' => 'PayPal'],
        ];
    }

    private function humanizeType(string $type): string
    {
        return str_replace('_', ' ', ucfirst($type));
    }

    /**
     * Clear cached payment methods (e.g. after changing Mollie Dashboard settings).
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
