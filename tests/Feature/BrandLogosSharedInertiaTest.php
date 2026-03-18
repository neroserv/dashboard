<?php

use App\Models\Brand;

test('inertia shares brand logo urls on login for default brand', function () {
    Brand::create([
        'key' => 'logo-test',
        'name' => 'Logo Test Brand',
        'domains' => null,
        'is_default' => true,
        'logo_url' => 'brands/main.png',
        'logo_collapsed_url' => 'brands/collapsed.png',
        'auth_card_bg_url' => '/images/custom-auth-bg.svg',
    ]);

    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->where('brand.logoUrl', 'brands/main.png')
        ->where('brand.logoCollapsedUrl', 'brands/collapsed.png')
        ->where('brand.authCardBgUrl', '/images/custom-auth-bg.svg')
    );
});

test('brand persists auth_card_bg_url', function () {
    $brand = Brand::create([
        'key' => 'auth-bg-brand',
        'name' => 'Auth Bg',
        'domains' => null,
        'is_default' => false,
        'auth_card_bg_url' => 'brands/custom-bg.svg',
    ]);

    expect($brand->fresh()->auth_card_bg_url)->toBe('brands/custom-bg.svg');
});
