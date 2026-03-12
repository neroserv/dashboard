<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Human-readable labels for permission keys (e.g. for emails and UI)
    |--------------------------------------------------------------------------
    */
    'permission_labels' => [
        'view' => 'Ansehen',
        'renew' => 'Verlängern',
        'cancel_subscription' => 'Abo kündigen',
        'plesk_login' => 'Plesk-Login',
        'manage_auto_renew' => 'Auto-Verlängerung',
        'manage_tokens' => 'Token verwalten',
        'panel_login' => 'Panel-Login',
        'backups' => 'Backups',
        'schedules' => 'Zeitpläne',
        'databases' => 'Datenbanken',
        'create_server' => 'Server anlegen',
        'manage_servers' => 'Server verwalten',
        'authcode' => 'Auth-Code',
        'contact' => 'Kontakt',
        'whois' => 'WHOIS',
        'nameserver' => 'Nameserver',
        'dns' => 'DNS',
        'dnssec' => 'DNSSEC',
        'autorenew' => 'Auto-Verlängerung',
    ],

    /*
    |--------------------------------------------------------------------------
    | Product share permission keys per shareable type
    |--------------------------------------------------------------------------
    |
    | Each key is a permission that can be granted to a shared user.
    | 'view' is required for the product to appear in lists.
    | 'manage_collaborators' is only for the owner (never granted via share).
    |
    */

    \App\Models\WebspaceAccount::class => [
        'view',
        'renew',
        'cancel_subscription',
        'plesk_login',
        'manage_auto_renew',
    ],

    \App\Models\TeamSpeakServerAccount::class => [
        'view',
        'renew',
        'cancel_subscription',
        'manage_tokens',
        'manage_auto_renew',
    ],

    \App\Models\GameServerAccount::class => [
        'view',
        'renew',
        'cancel_subscription',
        'panel_login',
        'backups',
        'schedules',
        'databases',
        'manage_auto_renew',
    ],

    \App\Models\GameserverCloudSubscription::class => [
        'view',
        'cancel_subscription',
        'create_server',
        'manage_servers',
        'manage_auto_renew',
    ],

    \App\Models\ResellerDomain::class => [
        'view',
        'authcode',
        'contact',
        'whois',
        'nameserver',
        'dns',
        'dnssec',
        'renew',
        'autorenew',
    ],
];
