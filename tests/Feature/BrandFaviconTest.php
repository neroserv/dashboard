<?php

use App\Models\Brand;

test('favicon.ico redirects to brand seo favicon for matching host', function () {
    Brand::create([
        'key' => 'favhost',
        'name' => 'Fav Host',
        'is_default' => false,
        'seo' => ['favicon_url' => 'https://cdn.example.com/fav.png'],
        'domains' => ['brand-fav.test'],
    ]);

    $this->get('http://brand-fav.test/favicon.ico')
        ->assertRedirect('https://cdn.example.com/fav.png');
});

test('favicon.ico serves svg when brand has no favicon_url', function () {
    Brand::create([
        'key' => 'nofav',
        'name' => 'No Fav',
        'is_default' => false,
        'seo' => [],
        'domains' => ['no-fav-brand.test'],
    ]);

    $response = $this->get('http://no-fav-brand.test/favicon.ico');

    $response->assertOk();
    $response->assertHeader('content-type', 'image/svg+xml');
});
