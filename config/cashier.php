<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mollie Webhook URL
    |--------------------------------------------------------------------------
    */
    'webhook_url' => env('CASHIER_WEBHOOK_URL', 'webhooks/mollie'),

    /*
    |--------------------------------------------------------------------------
    | Mollie Webhook – vollständige URL (optional)
    |--------------------------------------------------------------------------
    | Für lokale Entwicklung: Mollie muss die Webhook-URL erreichen können.
    | Wenn gesetzt (z. B. ngrok-URL), wird diese statt route() verwendet.
    | Beispiel: MOLLIE_WEBHOOK_URL=https://xyz.ngrok.io/webhooks/mollie
    */
    'mollie_webhook_full_url' => env('MOLLIE_WEBHOOK_URL'),

    'aftercare_webhook_url' => env('CASHIER_AFTERCARE_WEBHOOK_URL', 'webhooks/mollie/aftercare'),

    'locale' => env('CASHIER_LOCALE', null),

    'order_number_generator' => [
        'model' => \Laravel\Cashier\Order\OrderNumberGenerator::class,
        'offset' => (int) env('CASHIER_ORDER_NUMBER_OFFSET', 0),
    ],

    'first_payment' => [
        'webhook_url' => env('CASHIER_FIRST_PAYMENT_WEBHOOK_URL', 'webhooks/mollie/first-payment'),
        'method' => [],
        'redirect_url' => env('APP_URL', config('app.url')),
        'amount' => [
            'value' => '1.00',
            'currency' => env('CASHIER_CURRENCY', 'EUR'),
        ],
        'description' => 'Welcome to '.config('app.name'),
    ],

    'update_payment_method' => [
        'redirect_url' => env('APP_URL', config('app.url')),
        'amount' => [
            'value' => '1.00',
            'currency' => env('CASHIER_CURRENCY', 'EUR'),
        ],
        'description' => 'Update payment method for '.config('app.name'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency (used by Cashier Mollie)
    |--------------------------------------------------------------------------
    */
    'currency' => env('CASHIER_CURRENCY', 'eur'),

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'de_DE'),

];
