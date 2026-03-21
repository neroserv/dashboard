<?php

use App\Models\Brand;

test('manifest reflects brand resolved from host', function () {
    Brand::query()->update(['is_default' => false]);

    Brand::create([
        'key' => 'alpha-pwa',
        'name' => 'Alpha PWA Brand',
        'domains' => ['alpha-pwa.test'],
        'logo_url' => 'brands/alpha-logo.png',
        'is_default' => false,
    ]);

    Brand::create([
        'key' => 'beta-pwa',
        'name' => 'Beta PWA Brand',
        'domains' => ['beta-pwa.test'],
        'is_default' => true,
    ]);

    expect(Brand::resolveByHost('alpha-pwa.test')?->name)->toBe('Alpha PWA Brand')
        ->and(Brand::resolveByHost('beta-pwa.test')?->name)->toBe('Beta PWA Brand');

    $this->get('http://alpha-pwa.test/manifest.json')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'Alpha PWA Brand')
        ->assertJsonStructure(['id', 'name', 'short_name', 'start_url', 'icons']);

    $this->get('http://beta-pwa.test/manifest.json')
        ->assertOk()
        ->assertJsonPath('name', 'Beta PWA Brand');
});
