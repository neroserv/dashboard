<?php

use App\Models\Brand;
use App\Models\TldPricelist;
use App\Models\TldSaleRouting;
use App\Models\User;
use App\Services\SkrimeApiService;
use App\Support\DomainRegistrar;

beforeEach(function () {
    Brand::query()->create([
        'key' => 'tld-test-brand',
        'name' => 'TLD Test Brand',
        'is_default' => true,
    ]);
});

test('admin can access tld pricelist index', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.domains.tld-pricelist.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('admin/domains/TldPricelist')
        ->has('items')
        ->where('filters.list', DomainRegistrar::SKRIME));
});

test('non-admin cannot access tld pricelist index', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.domains.tld-pricelist.index'));

    $response->assertStatus(403);
});

test('admin can bulk update margin for tlds', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brandId = Brand::query()->where('is_default', true)->value('id');
    foreach (['de', 'com'] as $tld) {
        TldSaleRouting::query()->create([
            'brand_id' => $brandId,
            'tld' => $tld,
            'sale_registrar' => DomainRegistrar::SKRIME,
        ]);
    }
    TldPricelist::create([
        'brand_id' => $brandId,
        'tld' => 'de',
        'price_source' => DomainRegistrar::SKRIME,
        'create_price' => 10,
        'renew_price' => 10,
        'margin_type' => 'fixed',
        'margin_value' => 2,
    ]);
    TldPricelist::create([
        'brand_id' => $brandId,
        'tld' => 'com',
        'price_source' => DomainRegistrar::SKRIME,
        'create_price' => 12,
        'renew_price' => 12,
        'margin_type' => 'fixed',
        'margin_value' => 2,
    ]);

    $this->actingAs($admin);
    $response = $this->put(route('admin.domains.tld-pricelist.bulk'), [
        'price_source' => DomainRegistrar::SKRIME,
        'tlds' => ['de', 'com'],
        'margin_type' => 'percent',
        'margin_value' => 15,
    ]);

    $response->assertRedirect(route('admin.domains.tld-pricelist.index', ['list' => DomainRegistrar::SKRIME]));
    expect(TldPricelist::where('tld', 'de')->where('price_source', DomainRegistrar::SKRIME)->first()->margin_type)->toBe('percent');
    expect((float) TldPricelist::where('tld', 'de')->where('price_source', DomainRegistrar::SKRIME)->first()->margin_value)->toBe(15.0);
});

test('admin sync populates tld pricelist from skrime', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->mock(SkrimeApiService::class, function ($mock) {
        $mock->shouldReceive('forBrand')->andReturnSelf();
        $mock->shouldReceive('getPricelist')->once()->andReturn([
            ['tld' => 'de', 'create' => '8.50', 'renew' => '8.50', 'transfer' => '8.50', 'restore' => '30', 'offer' => false, 'offerTypes' => []],
        ]);
    });

    $this->actingAs($admin);
    $response = $this->post(route('admin.domains.tld-pricelist.sync'), [
        'price_source' => DomainRegistrar::SKRIME,
    ]);

    $response->assertRedirect(route('admin.domains.tld-pricelist.index', ['list' => DomainRegistrar::SKRIME]));
    $row = TldPricelist::query()->where('tld', 'de')->where('price_source', DomainRegistrar::SKRIME)->first();
    expect($row)->not->toBeNull();
    expect((float) $row->create_price)->toBe(8.5);
});

test('admin can set sale registrar for a tld', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brandId = Brand::query()->where('is_default', true)->value('id');
    TldSaleRouting::query()->create([
        'brand_id' => $brandId,
        'tld' => 'de',
        'sale_registrar' => DomainRegistrar::SKRIME,
    ]);

    $this->actingAs($admin);
    $this->put(route('admin.domains.tld-pricelist.sale-registrar'), [
        'tld' => 'de',
        'sale_registrar' => DomainRegistrar::REALTIME_REGISTER,
    ])->assertRedirect(route('admin.domains.tld-pricelist.index', ['list' => DomainRegistrar::SKRIME]));

    expect(TldSaleRouting::query()->where('brand_id', $brandId)->where('tld', 'de')->value('sale_registrar'))
        ->toBe(DomainRegistrar::REALTIME_REGISTER);
});

test('tld pricelist rr list shows only realtime register rows', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $brandId = Brand::query()->where('is_default', true)->value('id');
    TldPricelist::create([
        'brand_id' => $brandId,
        'tld' => 'de',
        'price_source' => DomainRegistrar::SKRIME,
        'create_price' => 1,
        'renew_price' => 1,
        'margin_type' => 'fixed',
        'margin_value' => 0,
    ]);
    TldPricelist::create([
        'brand_id' => $brandId,
        'tld' => 'com',
        'price_source' => DomainRegistrar::REALTIME_REGISTER,
        'create_price' => 2,
        'renew_price' => 2,
        'margin_type' => 'fixed',
        'margin_value' => 0,
    ]);
    TldSaleRouting::query()->create([
        'brand_id' => $brandId,
        'tld' => 'com',
        'sale_registrar' => DomainRegistrar::SKRIME,
    ]);

    $this->actingAs($admin);
    $response = $this->get(route('admin.domains.tld-pricelist.index', ['list' => DomainRegistrar::REALTIME_REGISTER]));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('filters.list', DomainRegistrar::REALTIME_REGISTER)
        ->has('items', 1)
        ->where('items.0.tld', 'com')
        ->where('items.0.sale_registrar', DomainRegistrar::REALTIME_REGISTER)
        ->where('items.0.sale_price', 2));
});

test('admin can bulk set sale registrar for a price_source list', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $brandId = Brand::query()->where('is_default', true)->value('id');
    expect($brandId)->not->toBeNull();

    TldPricelist::query()->create([
        'brand_id' => $brandId,
        'tld' => 'de',
        'price_source' => DomainRegistrar::REALTIME_REGISTER,
        'create_price' => 1,
        'renew_price' => 1,
        'margin_type' => 'fixed',
        'margin_value' => 0,
    ]);
    TldPricelist::query()->create([
        'brand_id' => $brandId,
        'tld' => 'com',
        'price_source' => DomainRegistrar::REALTIME_REGISTER,
        'create_price' => 2,
        'renew_price' => 2,
        'margin_type' => 'fixed',
        'margin_value' => 0,
    ]);

    // Historical routing: skrime
    TldSaleRouting::query()->create([
        'brand_id' => $brandId,
        'tld' => 'de',
        'sale_registrar' => DomainRegistrar::SKRIME,
    ]);
    TldSaleRouting::query()->create([
        'brand_id' => $brandId,
        'tld' => 'com',
        'sale_registrar' => DomainRegistrar::SKRIME,
    ]);

    $this->actingAs($admin);
    $response = $this->put(route('admin.domains.tld-pricelist.bulk-sale-registrar'), [
        'sale_registrar' => DomainRegistrar::REALTIME_REGISTER,
        'tlds' => [],
        'search' => '.de',
    ]);

    $response->assertRedirect(route('admin.domains.tld-pricelist.index', [
        'list' => DomainRegistrar::REALTIME_REGISTER,
        'search' => '.de',
    ]));

    expect(TldSaleRouting::query()->where('brand_id', $brandId)->where('tld', 'de')->value('sale_registrar'))
        ->toBe(DomainRegistrar::REALTIME_REGISTER);
    // com should remain skrime because search filtered it out
    expect(TldSaleRouting::query()->where('brand_id', $brandId)->where('tld', 'com')->value('sale_registrar'))
        ->toBe(DomainRegistrar::SKRIME);
});

test('tld pricelist sync collapses duplicate tld rows by normalization per price_source', function () {
    $brandId = Brand::query()->where('is_default', true)->value('id');
    expect($brandId)->not->toBeNull();

    // Simulate historical duplicates: same logical TLD, different stored representation.
    TldPricelist::create([
        'brand_id' => $brandId,
        'tld' => 'de ',
        'price_source' => DomainRegistrar::SKRIME,
        'create_price' => 1,
        'renew_price' => 1,
        'transfer_price' => 1,
        'restore_price' => 1,
        'margin_type' => 'fixed',
        'margin_value' => 0,
    ]);

    TldPricelist::create([
        'brand_id' => $brandId,
        'tld' => '.de',
        'price_source' => DomainRegistrar::SKRIME,
        'create_price' => 2,
        'renew_price' => 2,
        'transfer_price' => 2,
        'restore_price' => 2,
        'margin_type' => 'fixed',
        'margin_value' => 0,
    ]);

    app(\App\Services\TldPricelistSyncService::class)->applyPricelist([
        ['tld' => 'de', 'create' => '3', 'renew' => '3', 'transfer' => '3', 'restore' => '3', 'offer' => false, 'offerTypes' => []],
    ], Brand::query()->findOrFail($brandId), DomainRegistrar::SKRIME);

    expect(TldPricelist::query()
        ->where('brand_id', $brandId)
        ->where('price_source', DomainRegistrar::SKRIME)
        ->where('tld', 'de')
        ->count())->toBe(1);

    expect(TldPricelist::query()
        ->where('brand_id', $brandId)
        ->where('price_source', DomainRegistrar::SKRIME)
        ->whereIn('tld', ['de ', '.de'])
        ->count())->toBe(0);
});
