<?php

return [
    'api_key' => env('REALTIMEREGISTER_API_KEY'),
    /** Reseller customer handle at Realtime Register (required for price list & many domain calls). */
    'customer_handle' => env('REALTIMEREGISTER_CUSTOMER_HANDLE', ''),
    'base_url' => env('REALTIMEREGISTER_BASE_URL', 'https://api.yoursrs.com/'),
    'sandbox_base_url' => env('REALTIMEREGISTER_SANDBOX_BASE_URL', 'https://api.yoursrs-ote.com/'),
    'timeout' => (int) env('REALTIMEREGISTER_TIMEOUT', 30),
    'default_nameservers' => array_values(array_filter(array_map('trim', explode(',', (string) env('REALTIMEREGISTER_DEFAULT_NS', ''))))),
    'margin_type' => env('REALTIMEREGISTER_MARGIN_TYPE', 'fixed'),
    'margin_value' => (float) env('REALTIMEREGISTER_MARGIN_VALUE', 0),
];
