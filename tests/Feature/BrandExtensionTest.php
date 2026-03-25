<?php

use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\ResellerDomain;
use App\Models\User;
use App\Services\RealtimeRegisterApiService;
use App\Services\SkrimeApiService;
use App\Services\TldPricelistSyncService;
use App\Support\DomainRegistrar;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Brand::query()->create([
        'key' => 'ext-test-brand',
        'name' => 'Extension Test',
        'is_default' => true,
    ]);
});

test('admin can view brand extensions index', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.brand-extensions.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/brand-extensions/Index')
        ->has('extension_brand')
        ->has('brand_extensions')
        ->has('pterodactyl_product_flags'));
});

test('non-admin cannot view brand extensions index', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.brand-extensions.index'));

    $response->assertStatus(403);
});

test('admin can install and uninstall an extension', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::getDefault();
    expect($brand)->not->toBeNull();

    $this->actingAs($admin);

    $this->post(route('admin.brand-extensions.install'), [
        'extension' => BrandExtension::EXTENSION_TEAMSPEAK,
    ])->assertRedirect(route('admin.brand-extensions.index'));

    expect(BrandExtension::query()
        ->where('brand_id', $brand->id)
        ->where('extension', BrandExtension::EXTENSION_TEAMSPEAK)
        ->whereNotNull('installed_at')
        ->exists())->toBeTrue();

    $this->post(route('admin.brand-extensions.uninstall'), [
        'extension' => BrandExtension::EXTENSION_TEAMSPEAK,
    ])->assertRedirect(route('admin.brand-extensions.index'));

    expect(BrandExtension::query()
        ->where('brand_id', $brand->id)
        ->where('extension', BrandExtension::EXTENSION_TEAMSPEAK)
        ->whereNull('installed_at')
        ->exists())->toBeTrue();
});

test('admin can update skrime extension settings', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::getDefault();
    expect($brand)->not->toBeNull();

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_SKRIME,
        'installed_at' => now(),
        'settings' => [],
    ]);

    $this->actingAs($admin);

    $this->put(route('admin.brand-extensions.skrime.update'), [
        'api_url' => 'https://api.example.test/skrime',
        'api_token' => 'secret-token',
        'timeout' => 45,
        'margin_type' => 'percent',
        'margin_value' => 12.5,
    ])->assertRedirect(route('admin.brand-extensions.index'));

    $row = BrandExtension::query()
        ->where('brand_id', $brand->id)
        ->where('extension', BrandExtension::EXTENSION_SKRIME)
        ->first();
    expect($row)->not->toBeNull();
    $settings = $row->settings;
    expect(is_array($settings))->toBeTrue();
    expect($settings['api_url'] ?? null)->toBe('https://api.example.test/skrime');
    expect($settings['api_token'] ?? null)->toBe('secret-token');
    expect((int) ($settings['timeout'] ?? 0))->toBe(45);
    expect($settings['margin_type'] ?? null)->toBe('percent');
    expect((float) ($settings['margin_value'] ?? 0))->toBe(12.5);
});

test('sync tld pricelist job uses skrime for brand', function () {
    $brand = Brand::getDefault();
    expect($brand)->not->toBeNull();

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_SKRIME,
        'installed_at' => now(),
        'settings' => [],
    ]);

    $this->mock(SkrimeApiService::class, function ($mock) {
        $mock->shouldReceive('forBrand')->andReturnSelf();
        $mock->shouldReceive('getPricelist')->once()->andReturn([
            ['tld' => 'de', 'create' => '1.00', 'renew' => '1.00', 'transfer' => '1.00', 'restore' => '10', 'offer' => false, 'offerTypes' => []],
        ]);
    });

    Cache::forget('skrime_pricelist:'.$brand->id);

    (new \App\Jobs\SyncTldPricelistJob($brand->id))->handle(
        app(SkrimeApiService::class),
        app(RealtimeRegisterApiService::class),
        app(TldPricelistSyncService::class),
    );

    $row = \App\Models\TldPricelist::query()
        ->where('brand_id', $brand->id)
        ->where('tld', 'de')
        ->where('price_source', DomainRegistrar::SKRIME)
        ->first();
    expect($row)->not->toBeNull();
    expect((float) $row->create_price)->toBe(1.0);
});

test('admin can update realtimeregister extension settings', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::getDefault();
    expect($brand)->not->toBeNull();

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_REALTIMEREGISTER,
        'installed_at' => now(),
        'settings' => [],
    ]);

    $this->actingAs($admin);

    $this->put(route('admin.brand-extensions.realtimeregister.update'), [
        'api_url' => 'https://api.rr.example.test/',
        'api_key' => 'rr-secret',
        'customer_handle' => 'cust-1',
        'timeout' => 40,
        'margin_type' => 'percent',
        'margin_value' => 5,
        'sandbox' => true,
        'default_nameservers' => ['ns1.example.test', 'ns2.example.test'],
    ])->assertRedirect(route('admin.brand-extensions.index'));

    $row = BrandExtension::query()
        ->where('brand_id', $brand->id)
        ->where('extension', BrandExtension::EXTENSION_REALTIMEREGISTER)
        ->first();
    expect($row)->not->toBeNull();
    $settings = $row->settings;
    expect(is_array($settings))->toBeTrue();
    expect($settings['api_url'] ?? null)->toBe('https://api.rr.example.test/');
    expect($settings['api_key'] ?? null)->toBe('rr-secret');
    expect($settings['customer_handle'] ?? null)->toBe('cust-1');
    expect((bool) ($settings['sandbox'] ?? false))->toBeTrue();
    expect($settings['default_nameservers'] ?? [])->toBe(['ns1.example.test', 'ns2.example.test']);
});

test('skrime extension cannot be uninstalled while skrime domains exist', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::getDefault();
    expect($brand)->not->toBeNull();

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_SKRIME,
        'installed_at' => now(),
        'settings' => [],
    ]);

    ResellerDomain::factory()->create([
        'brand_id' => $brand->id,
        'registrar' => DomainRegistrar::SKRIME,
    ]);

    $this->actingAs($admin);
    $this->from(route('admin.brand-extensions.index'))->post(route('admin.brand-extensions.uninstall'), [
        'extension' => BrandExtension::EXTENSION_SKRIME,
    ])->assertRedirect(route('admin.brand-extensions.index'))
        ->assertSessionHas('error');

    expect(BrandExtension::query()
        ->where('brand_id', $brand->id)
        ->where('extension', BrandExtension::EXTENSION_SKRIME)
        ->whereNotNull('installed_at')
        ->exists())->toBeTrue();
});

test('realtimeregister extension cannot be uninstalled while rr domains exist', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::getDefault();
    expect($brand)->not->toBeNull();

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_REALTIMEREGISTER,
        'installed_at' => now(),
        'settings' => [],
    ]);

    ResellerDomain::factory()->create([
        'brand_id' => $brand->id,
        'registrar' => DomainRegistrar::REALTIME_REGISTER,
    ]);

    $this->actingAs($admin);
    $this->from(route('admin.brand-extensions.index'))->post(route('admin.brand-extensions.uninstall'), [
        'extension' => BrandExtension::EXTENSION_REALTIMEREGISTER,
    ])->assertRedirect(route('admin.brand-extensions.index'))
        ->assertSessionHas('error');

    expect(BrandExtension::query()
        ->where('brand_id', $brand->id)
        ->where('extension', BrandExtension::EXTENSION_REALTIMEREGISTER)
        ->whereNotNull('installed_at')
        ->exists())->toBeTrue();
});

test('realtimeregister isConfigured is true when api key and customer handle come from env only', function () {
    $brand = Brand::getDefault();
    expect($brand)->not->toBeNull();

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_REALTIMEREGISTER,
        'installed_at' => now(),
        'settings' => [],
    ]);

    config([
        'realtimeregister.api_key' => 'env-api-key',
        'realtimeregister.customer_handle' => 'env-customer',
        'realtimeregister.base_url' => 'https://api.yoursrs.com/',
    ]);

    $svc = app(RealtimeRegisterApiService::class)->forBrand($brand);
    expect($svc->isConfigured())->toBeTrue();
    expect($svc->configurationIssues())->toBe([]);
});
