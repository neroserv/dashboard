<?php

use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\User;

test('authenticated user can access billing page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('billing.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index')
        ->has('invoices')
        ->has('aiTokensEnabled')
        ->has('aiTokenPackages')
    );
});

test('billing page includes prepaid balance and transactions when brand has prepaid_balance', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-prepaid',
        'name' => 'Test Prepaid',
        'domains' => null,
        'is_default' => true,
        'features' => ['prepaid_balance' => true],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    CustomerBalance::create(['user_id' => $user->id, 'balance' => 42.50]);
    $this->actingAs($user);

    $response = $this->get(route('billing.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index')
        ->where('prepaidEnabled', true)
        ->where('customerBalance', 42.50)
        ->has('balanceTransactions')
    );
});

test('billing page includes balance top-up props when brand has balance_topup', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-topup',
        'name' => 'Test Topup',
        'domains' => null,
        'is_default' => true,
        'features' => ['balance_topup' => true],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    $this->actingAs($user);

    $response = $this->get(route('billing.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index')
        ->where('balanceTopUpEnabled', true)
        ->has('balanceTopUpMinAmount')
        ->has('balanceCheckoutUrl')
    );
});

test('billing page hides AI tokens when brand has ai_tokens disabled', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-gaming',
        'name' => 'Test Gaming',
        'domains' => null,
        'is_default' => true,
        'features' => ['ai_tokens' => false],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    $this->actingAs($user);

    $response = $this->get(route('billing.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('billing/Index')
        ->where('aiTokensEnabled', false)
        ->where('aiTokenPackages', [])
        ->where('aiTokenBalance', 0)
    );
});
