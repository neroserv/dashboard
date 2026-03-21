<?php

use App\Models\Brand;

test('returns 400 when domain parameter is missing', function () {
    $response = $this->get('/api/verify-domain');

    $response->assertStatus(400);
});

test('returns 403 when domain does not exist', function () {
    $response = $this->get('/api/verify-domain?domain=nonexistent.com');

    $response->assertStatus(403);
});

test('returns 200 when domain is registered under a brand', function () {
    Brand::create([
        'key' => 'test-brand',
        'name' => 'Test Brand',
        'domains' => ['shop.example.com', 'main.example.com'],
        'is_default' => false,
    ]);

    $response = $this->get('/api/verify-domain?domain=shop.example.com');

    $response->assertStatus(200);
});

test('returns 200 for brand domain with normalized case', function () {
    Brand::create([
        'key' => 'test-brand',
        'name' => 'Test Brand',
        'domains' => ['brand-domain.com'],
        'is_default' => false,
    ]);

    $response = $this->get('/api/verify-domain?domain=BRAND-DOMAIN.COM');

    $response->assertStatus(200);
});

test('returns 200 when domain is an admin domain of a brand', function () {
    Brand::create([
        'key' => 'neroserv',
        'name' => 'Neroserv',
        'admin_domains' => ['admin.neroserv.de', 'admin.neroserv.test'],
        'is_default' => false,
    ]);

    $response = $this->get('/api/verify-domain?domain=admin.neroserv.de');

    $response->assertStatus(200);
});

test('returns 403 when domain is not in admin_domains', function () {
    Brand::create([
        'key' => 'neroserv',
        'name' => 'Neroserv',
        'admin_domains' => ['admin.neroserv.test'],
        'is_default' => false,
    ]);

    $response = $this->get('/api/verify-domain?domain=admin.neroserv.de');

    $response->assertStatus(403);
});
