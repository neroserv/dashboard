<?php

use App\Models\Brand;
use App\Models\GameServerAccount;
use App\Models\HostingPlan;
use App\Models\User;

test('gaming account can enable auto renew with balance', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-auto-renew',
        'name' => 'Test Auto Renew',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'prepaid_balance' => true],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Plan',
        'plesk_package_name' => null,
        'panel_type' => 'pterodactyl',
        'price' => 10.00,
        'is_active' => true,
    ]);

    $account = GameServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'name' => 'Test Server',
        'status' => 'active',
        'renewal_type' => 'manual',
        'mollie_subscription_id' => null,
        'current_period_ends_at' => now()->addMonth(),
        'auto_renew_with_balance' => false,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('gaming-accounts.auto-renew-balance', $account), ['enabled' => true]);

    $response->assertRedirect(route('gaming-accounts.show', $account));
    $response->assertSessionHas('success');

    $account->refresh();
    expect($account->auto_renew_with_balance)->toBeTrue();
});

test('gaming account can disable auto renew with balance', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-auto-renew-off',
        'name' => 'Test Auto Renew Off',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'prepaid_balance' => true],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Plan',
        'plesk_package_name' => null,
        'panel_type' => 'pterodactyl',
        'price' => 10.00,
        'is_active' => true,
    ]);

    $account = GameServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'name' => 'Test Server',
        'status' => 'active',
        'renewal_type' => 'manual',
        'mollie_subscription_id' => null,
        'current_period_ends_at' => now()->addMonth(),
        'auto_renew_with_balance' => true,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('gaming-accounts.auto-renew-balance', $account), ['enabled' => false]);

    $response->assertRedirect(route('gaming-accounts.show', $account));
    $response->assertSessionHas('success');

    $account->refresh();
    expect($account->auto_renew_with_balance)->toBeFalse();
});

test('auto renew balance requires prepaid brand', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-no-prepaid',
        'name' => 'Test No Prepaid',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'prepaid_balance' => false],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Plan',
        'plesk_package_name' => null,
        'panel_type' => 'pterodactyl',
        'price' => 10.00,
        'is_active' => true,
    ]);

    $account = GameServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'name' => 'Test Server',
        'status' => 'active',
        'renewal_type' => 'manual',
        'mollie_subscription_id' => null,
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($user);

    $response = $this->post(route('gaming-accounts.auto-renew-balance', $account), ['enabled' => true]);

    $response->assertRedirect(route('gaming-accounts.show', $account));
    $response->assertSessionHas('error');
});
