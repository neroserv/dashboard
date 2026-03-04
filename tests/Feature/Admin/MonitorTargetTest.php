<?php

use App\Http\Middleware\EnsureAdminDomainForAdminRoutes;
use App\Models\MonitorTarget;
use App\Models\User;

beforeEach(function () {
    $this->withoutMiddleware(EnsureAdminDomainForAdminRoutes::class);
});

test('guests cannot access monitoring index', function () {
    $response = $this->get(route('admin.monitoring.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access monitoring index', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.monitoring.index'));
    $response->assertForbidden();
});

test('admin users can view monitoring index', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $response = $this->get(route('admin.monitoring.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/monitoring/Index')
        ->has('monitorTargets')
        ->has('monitorTargets.data')
    );
});

test('admin users can create http_url monitor target', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $response = $this->post(route('admin.monitoring.store'), [
        'type' => MonitorTarget::TYPE_HTTP_URL,
        'name' => 'Test URL',
        'config' => [
            'url' => 'https://example.com/health',
            'timeout' => 10,
            'expected_status' => 200,
        ],
        'is_enabled' => true,
    ]);

    $response->assertRedirect(route('admin.monitoring.index'));
    $response->assertSessionHas('success');

    $target = MonitorTarget::query()->where('type', MonitorTarget::TYPE_HTTP_URL)->first();
    expect($target)->not->toBeNull();
    expect($target->name)->toBe('Test URL');
    expect($target->config['url'])->toBe('https://example.com/health');
});

test('admin users can create tcp_port monitor target', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $response = $this->post(route('admin.monitoring.store'), [
        'type' => MonitorTarget::TYPE_TCP_PORT,
        'name' => 'SSH Server',
        'config' => [
            'host' => '192.168.1.1',
            'port' => 22,
            'timeout' => 5,
        ],
        'is_enabled' => true,
    ]);

    $response->assertRedirect(route('admin.monitoring.index'));
    $response->assertSessionHas('success');

    $target = MonitorTarget::query()->where('type', MonitorTarget::TYPE_TCP_PORT)->first();
    expect($target)->not->toBeNull();
    expect($target->name)->toBe('SSH Server');
    expect($target->config['host'])->toBe('192.168.1.1');
    expect($target->config['port'])->toBe(22);
});

test('admin users can update and delete monitor target', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $target = MonitorTarget::query()->create([
        'type' => MonitorTarget::TYPE_HTTP_URL,
        'name' => 'Old Name',
        'config' => ['url' => 'https://old.example.com', 'timeout' => 10, 'expected_status' => 200],
        'is_enabled' => true,
    ]);
    $this->actingAs($user);

    $response = $this->put(route('admin.monitoring.update', $target), [
        'type' => MonitorTarget::TYPE_HTTP_URL,
        'name' => 'Updated Name',
        'config' => [
            'url' => 'https://new.example.com',
            'timeout' => 15,
            'expected_status' => 200,
        ],
        'is_enabled' => false,
    ]);

    $response->assertRedirect(route('admin.monitoring.index'));
    $target->refresh();
    expect($target->name)->toBe('Updated Name');
    expect($target->config['url'])->toBe('https://new.example.com');
    expect($target->is_enabled)->toBeFalse();

    $response = $this->delete(route('admin.monitoring.destroy', $target));
    $response->assertRedirect(route('admin.monitoring.index'));
    expect(MonitorTarget::query()->find($target->id))->toBeNull();
});
