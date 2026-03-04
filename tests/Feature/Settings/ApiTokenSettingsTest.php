<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('user can create api token', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = $this->post('/settings/api-tokens', [
        'name' => 'Landing Page',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.newToken');
    $response->assertSessionHas('flash.newToken.plainTextToken');
    $response->assertSessionHas('flash.newToken.name', 'Landing Page');

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('Landing Page');
});

test('user cannot create token with duplicate name', function () {
    $user = User::factory()->create();
    $user->createToken('My Token');
    actingAs($user);

    $response = $this->post('/settings/api-tokens', [
        'name' => 'My Token',
    ]);

    $response->assertSessionHasErrors('name');
    expect($user->tokens()->count())->toBe(1);
});

test('user can revoke own token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('To Revoke');
    $tokenId = $token->accessToken->id;
    actingAs($user);

    $response = $this->delete("/settings/api-tokens/{$tokenId}");

    $response->assertRedirect();
    expect($user->tokens()->count())->toBe(0);
});

test('guest cannot access api tokens page', function () {
    $response = $this->get('/settings/api-tokens');

    $response->assertRedirect(route('login'));
});
