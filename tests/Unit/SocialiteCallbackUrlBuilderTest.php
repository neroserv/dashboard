<?php

use App\Services\Auth\SocialiteCallbackUrlBuilder;
use Illuminate\Http\Request;

test('callback url uses request scheme host and social callback route', function () {
    $builder = new SocialiteCallbackUrlBuilder;
    $request = Request::create('https://dash.neroserv.de/settings/integration', 'GET');

    expect($builder->absoluteCallbackUrl($request, 'discord'))
        ->toBe('https://dash.neroserv.de/auth/discord/callback');
});

test('callback url uses another host when request host differs', function () {
    $builder = new SocialiteCallbackUrlBuilder;
    $request = Request::create('http://gaming.praxishosting.test/settings/integration', 'GET');

    expect($builder->absoluteCallbackUrl($request, 'discord'))
        ->toBe('http://gaming.praxishosting.test/auth/discord/callback');
});
