<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Brand;
use App\Models\Group;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Must match HandleInertiaRequests::version() so X-Inertia GET requests are not rewritten to 409 after a fallback route runs web middleware.
 */
function inertiaTestAssetVersion(): string
{
    return (string) (new HandleInertiaRequests)->version(Request::create('/'));
}

beforeEach(function () {
    Cache::flush();
});

test('authenticated customer receives maintenance page when global maintenance is enabled', function () {
    Setting::set('maintenance_global_enabled', '1');
    app(MaintenanceService::class)->forgetGlobalActiveCache();

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertStatus(503)
        ->assertInertia(fn ($page) => $page->component('errors/Maintenance'));
});

test('guest is redirected to login from dashboard during global maintenance', function () {
    Setting::set('maintenance_global_enabled', '1');
    app(MaintenanceService::class)->forgetGlobalActiveCache();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('guest can reach admin login redirect during global maintenance', function () {
    Setting::set('maintenance_global_enabled', '1');
    app(MaintenanceService::class)->forgetGlobalActiveCache();

    $this->get(route('admin.login'))->assertRedirect(route('login'));
});

test('json api request receives 503 during global maintenance', function () {
    Setting::set('maintenance_global_enabled', '1');
    app(MaintenanceService::class)->forgetGlobalActiveCache();

    $this->getJson('/api/v1/hosting-plans')
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});

test('user with admin.settings.update can enable global maintenance without admin.maintenance permission', function () {
    $access = Permission::query()->firstOrCreate(
        ['key' => 'admin.access'],
        ['name' => 'Admin-Zugang', 'label' => 'Admin', 'category' => 'Zugang'],
    );
    $settingsUpdate = Permission::query()->firstOrCreate(
        ['key' => 'admin.settings.update'],
        ['name' => 'Einstellungen bearbeiten', 'label' => 'Einstellungen bearbeiten', 'category' => 'System'],
    );
    $group = Group::query()->create([
        'key' => 'settings-maint-test-'.uniqid(),
        'name' => 'Settings Maint Test',
        'label' => 'Test',
        'color' => null,
    ]);
    $group->permissions()->sync([$access->id, $settingsUpdate->id]);

    $user = User::factory()->create(['is_admin' => false]);
    $user->groups()->attach($group->id);

    Setting::set('maintenance_toggle_cooldown_minutes', '0');
    Setting::set('maintenance_global_toggled_at', '');
    Setting::set('maintenance_global_enabled', '0');
    app(MaintenanceService::class)->forgetGlobalActiveCache();

    $this->actingAs($user)->from(route('admin.settings.index', ['tab' => 'wartung']))
        ->put(route('admin.settings.maintenance.update'), [
            'maintenance_global_enabled' => true,
            'maintenance_global_message' => '',
            'maintenance_global_until' => null,
            'maintenance_toggle_cooldown_minutes' => 0,
        ])
        ->assertRedirect(route('admin.settings.index', ['tab' => 'wartung']))
        ->assertSessionHas('success');

    expect(Setting::get('maintenance_global_enabled'))->toBe('1');
});

test('admin can save global maintenance when cooldown is zero', function () {
    Setting::set('maintenance_toggle_cooldown_minutes', '0');
    Setting::set('maintenance_global_toggled_at', '');
    Setting::set('maintenance_global_enabled', '0');

    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $this->from(route('admin.settings.index', ['tab' => 'wartung']))
        ->put(route('admin.settings.maintenance.update'), [
            'maintenance_global_enabled' => true,
            'maintenance_global_message' => 'Kurze Wartung',
            'maintenance_global_until' => null,
            'maintenance_toggle_cooldown_minutes' => 0,
        ])
        ->assertRedirect(route('admin.settings.index', ['tab' => 'wartung']))
        ->assertSessionHas('success');

    expect(Setting::get('maintenance_global_enabled'))->toBe('1');
    expect(Setting::get('maintenance_global_message'))->toBe('Kurze Wartung');
});

test('maintenance settings update respects cooldown', function () {
    Setting::set('maintenance_toggle_cooldown_minutes', '60');
    Setting::set('maintenance_global_toggled_at', now()->toIso8601String());
    Setting::set('maintenance_global_enabled', '0');

    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $response = $this->from(route('admin.settings.index', ['tab' => 'wartung']))
        ->put(route('admin.settings.maintenance.update'), [
            'maintenance_global_enabled' => true,
            'maintenance_global_message' => 'Wartung',
            'maintenance_global_until' => null,
            'maintenance_toggle_cooldown_minutes' => 60,
        ]);

    $response->assertSessionHasErrors('maintenance');
});

test('inertia renders custom http error page for admin not found', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $missingId = (int) (Brand::query()->max('id') ?? 0) + 9999;

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => inertiaTestAssetVersion(),
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get("/admin/brands/{$missingId}/edit");

    $response->assertStatus(404);
    expect($response->headers->get('content-type'))->toContain('application/json');
    $response->assertJsonPath('component', 'errors/HttpError');
    $response->assertJsonPath('props.status', 404);
    $response->assertJsonPath('props.shell', 'admin');
});

test('guest 404 inertia response uses guest shell', function () {
    $missing = 'zzz-guest-404-'.uniqid();
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => inertiaTestAssetVersion(),
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get("/{$missing}");

    $response->assertStatus(404);
    $response->assertJsonPath('component', 'errors/HttpError');
    $response->assertJsonPath('props.shell', 'guest');
});

test('guest 404 full document navigation returns inertia error page html', function () {
    $missing = 'zzz-full-doc-404-'.uniqid();
    $response = $this->get("/{$missing}");

    $response->assertStatus(404);
    $response->assertSee('errors/HttpError', false);
});

test('authenticated customer 404 inertia uses app shell', function () {
    $user = User::factory()->create();
    $missing = 'zzz-app-404-'.uniqid();
    $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => inertiaTestAssetVersion(),
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get("/{$missing}")
        ->assertStatus(404)
        ->assertJsonPath('props.shell', 'app');
});
