<?php

use App\Models\Brand;
use App\Models\User;

test('brand getFeaturesArray returns gameserver_cloud false by default', function () {
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

test('brand getFeaturesArray returns gameserver_cloud true when set in features', function () {
    $brand = Brand::create([
        'key' => 'test-cloud',
        'name' => 'Test Cloud',
        'domains' => null,
        'is_default' => false,
        'features' => ['gameserver_cloud' => true],
    ]);

    $features = $brand->getFeaturesArray();
    expect($features['gameserver_cloud'])->toBeTrue();
});

test('admin can update brand with features.gameserver_cloud', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-edit',
        'name' => 'Test Edit',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true],
    ]);

    $admin = User::factory()->create(['is_admin' => true, 'brand_id' => $brand->id]);
    $this->actingAs($admin);

    $response = $this->put(route('admin.brands.update', $brand), [
        'name' => $brand->name,
        'domains' => [],
        'admin_domains' => [],
        'is_default' => true,
        'features' => [
            'gaming' => true,
            'gameserver_cloud' => true,
        ],
    ]);

    $response->assertRedirect();
    $brand->refresh();
    expect($brand->getFeaturesArray()['gameserver_cloud'])->toBeTrue();
});
