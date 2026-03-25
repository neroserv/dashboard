<?php

use App\Models\Brand;
use App\Models\TldSaleRouting;
use App\Models\User;
use App\Services\TldSaleRoutingService;
use App\Support\DomainRegistrar;

beforeEach(function () {
    Brand::query()->create([
        'key' => 'brand-domain-sales-registrar-test',
        'name' => 'Brand Domain Sales Registrar Test',
        'is_default' => true,
    ]);
});

test('admin can update domain_sales_registrar feature', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::query()->where('key', 'brand-domain-sales-registrar-test')->first();
    expect($brand)->not->toBeNull();

    $this->actingAs($admin);

    $this->put(route('admin.brands.update', $brand), [
        'name' => 'Brand Domain Sales Registrar Test',
        'is_default' => false,
        'features' => [
            'domain_sales_registrar' => DomainRegistrar::REALTIME_REGISTER,
        ],
    ])->assertRedirect(route('admin.settings.index', ['tab' => 'marken']));

    expect($brand->fresh()->features['domain_sales_registrar'] ?? null)->toBe(DomainRegistrar::REALTIME_REGISTER);
});

test('changing brand domain_sales_registrar updates tld_sale_routing rows that matched the old default', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::query()->where('key', 'brand-domain-sales-registrar-test')->first();
    expect($brand)->not->toBeNull();

    TldSaleRouting::query()->create([
        'brand_id' => $brand->id,
        'tld' => 'de',
        'sale_registrar' => DomainRegistrar::SKRIME,
    ]);

    TldSaleRouting::query()->create([
        'brand_id' => $brand->id,
        'tld' => 'com',
        'sale_registrar' => DomainRegistrar::REALTIME_REGISTER,
    ]);

    $this->actingAs($admin);

    $this->put(route('admin.brands.update', $brand), [
        'name' => 'Brand Domain Sales Registrar Test',
        'is_default' => false,
        'features' => [
            'domain_sales_registrar' => DomainRegistrar::REALTIME_REGISTER,
        ],
    ])->assertRedirect(route('admin.settings.index', ['tab' => 'marken']));

    expect(TldSaleRouting::query()->where('brand_id', $brand->id)->where('tld', 'de')->value('sale_registrar'))
        ->toBe(DomainRegistrar::REALTIME_REGISTER);
    expect(TldSaleRouting::query()->where('brand_id', $brand->id)->where('tld', 'com')->value('sale_registrar'))
        ->toBe(DomainRegistrar::REALTIME_REGISTER);
});

test('admin can sync all tld sale routing rows to brand domain_sales_registrar default', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $admin = User::factory()->create(['is_admin' => true]);
    $brand = Brand::query()->where('key', 'brand-domain-sales-registrar-test')->first();
    expect($brand)->not->toBeNull();

    $brand->update([
        'features' => array_merge($brand->features ?? [], [
            'domain_sales_registrar' => DomainRegistrar::REALTIME_REGISTER,
        ]),
    ]);

    TldSaleRouting::query()->create([
        'brand_id' => $brand->id,
        'tld' => 'de',
        'sale_registrar' => DomainRegistrar::SKRIME,
    ]);

    $this->actingAs($admin);

    $this->post(route('admin.brands.sync-domain-sale-routing', $brand))
        ->assertRedirect(route('admin.brands.edit', $brand));

    expect(TldSaleRouting::query()->where('brand_id', $brand->id)->where('tld', 'de')->value('sale_registrar'))
        ->toBe(DomainRegistrar::REALTIME_REGISTER);
});

test('sale registrar for empty tld uses brand domain_sales_registrar default', function () {
    $brand = Brand::query()->where('key', 'brand-domain-sales-registrar-test')->first();
    expect($brand)->not->toBeNull();

    $brand->update([
        'features' => array_merge($brand->features ?? [], [
            'domain_sales_registrar' => DomainRegistrar::REALTIME_REGISTER,
        ]),
    ]);

    $service = app(TldSaleRoutingService::class);

    expect($service->saleRegistrarFor($brand->fresh(), ''))->toBe(DomainRegistrar::REALTIME_REGISTER);
});
