<?php

namespace App\Support;

class MollieWebhookUrl
{
    /**
     * Liefert die für Mollie zu verwendende Webhook-URL, oder null wenn weggelassen werden soll.
     * Lokale Umgebung (localhost, .test, .local) ohne MOLLIE_WEBHOOK_URL: null, damit Mollie die URL nicht prüft.
     */
    public static function get(): ?string
    {
        $override = config('cashier.mollie_webhook_full_url');
        if ($override && $override !== '') {
            return $override;
        }

        $appUrl = config('app.url');
        $isLocal = $appUrl && (
            str_contains($appUrl, 'localhost')
            || str_contains($appUrl, '.test')
            || str_contains($appUrl, '.local')
        );

        if ($isLocal) {
            return null;
        }

        return route('webhooks.mollie.default');
    }
}
