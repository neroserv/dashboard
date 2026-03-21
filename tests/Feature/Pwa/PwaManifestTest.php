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

test('manifest icon src prefers app_icon_url over logo', function () {
    Brand::query()->update(['is_default' => false]);

    Brand::create([
        'key' => 'manifest-appicon',
        'name' => 'Manifest App Icon',
        'domains' => ['manifest-appicon.test'],
        'logo_url' => 'brands/wide-logo.png',
        'app_icon_url' => 'brands/pwa-square.png',
        'is_default' => false,
    ]);

    $response = $this->get('http://manifest-appicon.test/manifest.json');
    $response->assertOk();
    $src = $response->json('icons.0.src');
    expect($src)->toBeString()->toContain('pwa-square');
});
