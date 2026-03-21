<?php

use App\Models\User;
use App\Notifications\Channels\AppWebPushChannel;

test('web push channel is included when master enabled sync with email and subscription exists', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['login' => 'email'],
        'push_settings' => [
            'master_enabled' => true,
            'sync_with_email' => true,
            'types' => [],
        ],
    ]);

    $user->updatePushSubscription('https://push.test/'.uniqid(), 'pk', 'at', null);

    $channels = $user->getPreferredNotificationChannels('login');

    expect($channels)->toContain(AppWebPushChannel::class);
});

test('web push is not included when email channel is none and sync with email', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['login' => 'none'],
        'push_settings' => [
            'master_enabled' => true,
            'sync_with_email' => true,
            'types' => [],
        ],
    ]);

    $user->updatePushSubscription('https://push.test/'.uniqid(), 'pk', 'at', null);

    $channels = $user->getPreferredNotificationChannels('login');

    expect($channels)->not->toContain(AppWebPushChannel::class);
});

test('web push follows per type toggle when not syncing with email', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['login' => 'none'],
        'push_settings' => [
            'master_enabled' => true,
            'sync_with_email' => false,
            'types' => [
                'login' => true,
            ],
        ],
    ]);

    $user->updatePushSubscription('https://push.test/'.uniqid(), 'pk', 'at', null);

    $channels = $user->getPreferredNotificationChannels('login');

    expect($channels)->toContain(AppWebPushChannel::class);
});

test('web push is not included without subscription', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['login' => 'email'],
        'push_settings' => [
            'master_enabled' => true,
            'sync_with_email' => true,
            'types' => [],
        ],
    ]);

    $channels = $user->getPreferredNotificationChannels('login');

    expect($channels)->not->toContain(AppWebPushChannel::class);
});
