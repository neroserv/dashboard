<?php

use App\Http\Requests\DomainCheckoutRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;

test('domain checkout allows new registration without auth_code', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'street' => 'Teststraße',
        'street_number' => '1',
        'postal_code' => '12345',
        'city' => 'Berlin',
        'state' => 'BE',
        'country' => 'DE',
        'phone' => '+491234567890',
    ]);

    $base = Request::create('/domains/checkout', 'POST', [
        'domain' => 'example-registration.de',
        'sale_price' => 4.42,
        'tld' => 'de',
        'transfer' => false,
        'use_profile_contact' => true,
        'accept_tos' => true,
        'accept_early_execution' => true,
        'payment_method' => 'mollie',
    ]);
    $base->setUserResolver(fn () => $user);

    $form = DomainCheckoutRequest::createFrom($base);
    $form->setContainer(app());
    $form->setRedirector(app(Redirector::class));

    $form->validateResolved();

    expect($form->validated())->toHaveKey('domain');
});

test('domain checkout allows new registration with empty auth_code string', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'street' => 'Teststraße',
        'street_number' => '1',
        'postal_code' => '12345',
        'city' => 'Berlin',
        'state' => 'BE',
        'country' => 'DE',
        'phone' => '+491234567890',
    ]);

    $base = Request::create('/domains/checkout', 'POST', [
        'domain' => 'example-registration-empty-auth.de',
        'sale_price' => 4.42,
        'tld' => 'de',
        'transfer' => false,
        'auth_code' => '',
        'use_profile_contact' => true,
        'accept_tos' => true,
        'accept_early_execution' => true,
        'payment_method' => 'mollie',
    ]);
    $base->setUserResolver(fn () => $user);

    $form = DomainCheckoutRequest::createFrom($base);
    $form->setContainer(app());
    $form->setRedirector(app(Redirector::class));

    $form->validateResolved();

    expect($form->validated())->toHaveKey('domain');
});

test('domain checkout requires auth_code for transfer', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'street' => 'Teststraße',
        'street_number' => '1',
        'postal_code' => '12345',
        'city' => 'Berlin',
        'state' => 'BE',
        'country' => 'DE',
        'phone' => '+491234567890',
    ]);

    $base = Request::create('/domains/checkout', 'POST', [
        'domain' => 'example-transfer.de',
        'sale_price' => 4.42,
        'tld' => 'de',
        'transfer' => true,
        'use_profile_contact' => true,
        'accept_tos' => true,
        'accept_early_execution' => true,
        'payment_method' => 'mollie',
    ]);
    $base->setUserResolver(fn () => $user);

    $form = DomainCheckoutRequest::createFrom($base);
    $form->setContainer(app());
    $form->setRedirector(app(Redirector::class));

    expect(fn () => $form->validateResolved())
        ->toThrow(ValidationException::class);
});
