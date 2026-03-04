<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePaymentMethodsService
{
    private const CACHE_KEY = 'stripe_enabled_payment_methods';

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        protected StripeClient $stripe
    ) {}

    /**
     * Returns enabled payment methods with type and label for display (e.g. buttons with logos).
     * Uses cache; returns fallback if Stripe is not configured or API fails.
     *
     * @return array<int, array{type: string, label: string}>
     */
    public function getEnabledPaymentMethods(): array
    {
        $secret = config('cashier.secret');
        if (! $secret || $secret === '') {
            return $this->fallbackMethods();
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            try {
                $config = $this->resolveDefaultConfiguration();
                if (! $config) {
                    return $this->fallbackMethods();
                }

                return $this->extractEnabledMethods($config);
            } catch (ApiErrorException $e) {
                Log::warning('Stripe payment method configurations could not be fetched', [
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
        $labels = config('billing.stripe_payment_method_labels', []);
        $types = ['card', 'paypal', 'sepa_debit'];
        $result = [];
        foreach ($types as $type) {
            if (isset($labels[$type])) {
                $result[] = ['type' => $type, 'label' => $labels[$type]];
            }
        }

        return $result !== [] ? $result : [
            ['type' => 'card', 'label' => 'Karte'],
            ['type' => 'paypal', 'label' => 'PayPal'],
            ['type' => 'sepa_debit', 'label' => 'SEPA-Lastschrift'],
        ];
    }

    private function resolveDefaultConfiguration(): ?object
    {
        $list = $this->stripe->paymentMethodConfigurations->all(['limit' => 20]);
        if (! isset($list->data) || count($list->data) === 0) {
            return null;
        }
        foreach ($list->data as $config) {
            if (! empty($config->is_default) && ! empty($config->active)) {
                return $config;
            }
        }
        foreach ($list->data as $config) {
            if (! empty($config->active)) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Stripe payment method type keys that can appear in payment_method_configurations.
     * Only methods with available=true and display_preference.preference=on are listed.
     */
    private const PAYMENT_METHOD_CONFIG_KEYS = [
        'acss_debit', 'affirm', 'afterpay_clearpay', 'alipay', 'alma', 'amazon_pay', 'apple_pay', 'apple_pay_later',
        'au_becs_debit', 'bacs_debit', 'bancontact', 'billie', 'blik', 'boleto', 'card', 'cartes_bancaires',
        'cashapp', 'crypto', 'customer_balance', 'eps', 'fpx', 'giropay', 'grabpay', 'google_pay', 'ideal',
        'jcb', 'kakao_pay', 'klarna', 'konbini', 'kr_card', 'link', 'mb_way', 'mobilepay', 'multibanco',
        'naver_pay', 'nz_bank_account', 'oxxo', 'p24', 'pay_by_bank', 'payco', 'paynow', 'paypal', 'pix',
        'promptpay', 'revolut_pay', 'samsung_pay', 'satispay', 'sepa_debit', 'sofort', 'swish', 'twint',
        'us_bank_account', 'wechat_pay', 'zip',
    ];

    /**
     * @return array<int, array{type: string, label: string}>
     */
    private function extractEnabledMethods(object $config): array
    {
        $labelsConfig = config('billing.stripe_payment_method_labels', []);
        $result = [];
        $skip = ['customer_balance'];

        foreach (self::PAYMENT_METHOD_CONFIG_KEYS as $type) {
            if (in_array($type, $skip, true)) {
                continue;
            }
            if (! isset($config->{$type}) || ! is_object($config->{$type})) {
                continue;
            }
            $obj = $config->{$type};
            $available = $obj->available ?? false;
            $preference = $obj->display_preference->preference ?? $obj->display_preference->value ?? null;
            if ($available && $preference === 'on') {
                $label = $labelsConfig[$type] ?? $this->humanizeType($type);
                $result[] = ['type' => $type, 'label' => $label];
            }
        }

        return $result === [] ? $this->fallbackMethods() : $result;
    }

    private function humanizeType(string $type): string
    {
        return str_replace('_', ' ', ucfirst($type));
    }

    /**
     * Clear cached payment methods (e.g. after changing Stripe Dashboard settings).
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
