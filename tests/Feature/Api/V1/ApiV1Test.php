<?php

use App\Models\Brand;
use App\Models\HostingPlan;
use App\Models\TldPricelist;
use App\Models\User;

test('api v1 requires authentication', function () {
    $this->getJson('/api/v1/stats')->assertUnauthorized();
    $this->getJson('/api/v1/domains/tlds')->assertUnauthorized();
    $this->getJson('/api/v1/hosting-plans')->assertUnauthorized();
    $this->getJson('/api/v1/brand')->assertUnauthorized();
});

test('api v1 stats returns data with valid token', function () {
    $brand = Brand::create([
        'key' => 'test',
        'name' => 'Test',
        'is_default' => true,
    ]);
    $user = User::factory()->create(['brand_id' => $brand->id]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/stats');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'customers_count',
            'webspace_accounts_count',
            'game_server_accounts_count',
            'domains_count',
        ],
    ]);
});

test('api v1 domains tlds returns list with valid token', function () {
    TldPricelist::create([
        'tld' => 'de',
        'create_price' => 5.00,
        'renew_price' => 5.00,
        'transfer_price' => 5.00,
        'restore_price' => 10.00,
        'margin_type' => 'fixed',
        'margin_value' => 1.00,
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/domains/tlds');

    $response->assertOk();
    $response->assertJsonPath('data.0.tld', 'de');
    $response->assertJsonPath('data.0.create_price', 5);
});

test('api v1 hosting-plans returns plans with valid token', function () {
    $brand = Brand::create([
        'key' => 'test',
        'name' => 'Test',
        'is_default' => true,
    ]);
    HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => null,
        'panel_type' => 'plesk',
        'config' => [],
        'name' => 'Webspace Start',
        'plesk_package_name' => null,
        'disk_gb' => 10,
        'traffic_gb' => 100,
        'domains' => 1,
        'subdomains' => 5,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 4.99,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/hosting-plans');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Webspace Start');
    $response->assertJsonPath('data.0.panel_type', 'plesk');
});

test('api v1 brand returns brand with valid token', function () {
    $brand = Brand::create([
        'key' => 'test',
        'name' => 'Test Brand',
        'is_default' => true,
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/brand');

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Test Brand');
    $response->assertJsonStructure(['data' => ['id', 'name', 'features', 'seo']]);
});

test('api v1 returns 401 with invalid token', function () {
    $response = $this->withHeader('Authorization', 'Bearer invalid-token-here')
        ->getJson('/api/v1/stats');

    $response->assertUnauthorized();
});
