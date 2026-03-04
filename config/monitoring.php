<?php

return [
    'check_interval_minutes' => (int) env('MONITORING_CHECK_INTERVAL_MINUTES', 5),
    'enabled' => filter_var(env('MONITORING_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'notification_emails' => array_filter(array_map('trim', explode(',', env('MONITORING_NOTIFICATION_EMAILS', '')))),
];
