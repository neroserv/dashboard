<?php

use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

test('brand getFeaturesArray returns gameserver_cloud false without pterodactyl extension', function () {
    $brand = Brand::create([
        'key' => 'test-default',
        'name' => 'Test',
        'domains' => null,
        'is_default' => true,
        'features' => [],
    ]);

    $features = $brand->getFeaturesArray();
    expect($features['gameserver_cloud'])->toBeFalse();
});

test('brand getFeaturesArray returns gameserver_cloud true when pterodactyl installed and feature enabled', function () {
    $brand = Brand::create([
        'key' => 'test-cloud',
        'name' => 'Test Cloud',
        'domains' => null,
        'is_default' => false,
        'features' => ['gameserver_cloud' => true],
    ]);

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_PTERODACTYL,
        'installed_at' => now(),
    ]);

    $features = $brand->getFeaturesArray();
    expect($features['gameserver_cloud'])->toBeTrue();
});

test('admin can update pterodactyl product flags via brand extensions', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-edit',
        'name' => 'Test Edit',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true],
    ]);

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_PTERODACTYL,
        'installed_at' => now(),
    ]);

    $admin = User::factory()->create(['is_admin' => true, 'brand_id' => $brand->id]);
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->actingAs($admin);

    $this->put(route('admin.brand-extensions.pterodactyl-product-flags.update'), [
        'gaming' => true,
        'gameserver_cloud' => true,
    ])->assertRedirect(route('admin.brand-extensions.index'));

    $brand->refresh();
    expect($brand->getFeaturesArray()['gameserver_cloud'])->toBeTrue();
});
