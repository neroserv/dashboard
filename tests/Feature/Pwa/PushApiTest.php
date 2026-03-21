<?php

use App\Models\EmailTemplate;
use App\Models\User;

test('pwa settings page is reachable when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pwa.show'))->assertOk();
});

test('authenticated user can subscribe and unsubscribe push endpoint', function () {
    $user = User::factory()->create();

    $endpoint = 'https://push.example.test/'.uniqid('', true);
    $this->actingAs($user)->postJson('/api/push/subscribe', [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => 'test-p256dh-key',
            'auth' => 'test-auth-secret',
        ],
    ])->assertOk()->assertJsonPath('ok', true);

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => $endpoint,
        'subscribable_id' => $user->id,
        'subscribable_type' => $user->getMorphClass(),
    ]);

    $this->actingAs($user)->postJson('/api/push/unsubscribe', [
        'endpoint' => $endpoint,
    ])->assertOk()->assertJsonPath('ok', true);

    $this->assertDatabaseMissing('push_subscriptions', [
        'endpoint' => $endpoint,
    ]);
});

test('authenticated user can update push preferences', function () {
    $key = EmailTemplate::query()->orderBy('key')->value('key');
    expect($key)->not->toBeNull();

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/push/preferences', [
        'master_enabled' => true,
        'sync_with_email' => false,
        'types' => [
            $key => true,
        ],
    ])->assertOk()->assertJsonPath('ok', true);

    $user->refresh();
    expect($user->push_settings['master_enabled'])->toBeTrue()
        ->and($user->push_settings['sync_with_email'])->toBeFalse()
        ->and($user->push_settings['types'][$key] ?? null)->toBeTrue();
});

test('guest cannot access push api', function () {
    $this->postJson('/api/push/subscribe', [
        'endpoint' => 'https://x.test/e',
        'keys' => ['p256dh' => 'x', 'auth' => 'y'],
    ])->assertUnauthorized();

    $this->postJson('/api/push/preferences', [
        'master_enabled' => true,
        'sync_with_email' => true,
    ])->assertUnauthorized();
});
