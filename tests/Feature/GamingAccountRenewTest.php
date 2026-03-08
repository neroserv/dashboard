<?php

use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\GameServerAccount;
use App\Models\HostingPlan;
use App\Models\User;

$billingProfile = [
    'street' => 'Teststr. 1',
    'postal_code' => '12345',
    'city' => 'Berlin',
    'country' => 'DE',
];

test('prepaid gaming account can be renewed with balance', function () use ($billingProfile) {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-gaming-prepaid',
        'name' => 'Test Gaming Prepaid',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'prepaid_balance' => true, 'balance_period_months' => 1],
    ]);

    $user = User::factory()->create(array_merge(['brand_id' => $brand->id], $billingProfile));
    CustomerBalance::create(['user_id' => $user->id, 'balance' => 100]);

    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Game Plan',
        'plesk_package_name' => null,
        'panel_type' => 'pterodactyl',
        'price' => 10.00,
        'is_active' => true,
    ]);

    $periodEndsAt = now()->addDays(5);
    $account = GameServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'name' => 'Test Server',
        'status' => 'active',
        'stripe_subscription_id' => null,
        'renewal_type' => 'manual',
        'current_period_ends_at' => $periodEndsAt,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('gaming-accounts.renew', $account), [
        'payment_method' => 'balance',
        'period_months' => 1,
    ]);

    $response->assertRedirect(route('gaming-accounts.show', $account));
    $response->assertSessionHas('success');

    $account->refresh();
    expect($account->current_period_ends_at->format('Y-m-d'))->toBe($periodEndsAt->copy()->addMonth()->format('Y-m-d'));

    $balance = CustomerBalance::where('user_id', $user->id)->first();
    expect((float) $balance->balance)->toBe(90.0);
});

test('prepaid gaming account can be renewed for 3 months with balance', function () use ($billingProfile) {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-gaming-3m',
        'name' => 'Test Gaming 3M',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'prepaid_balance' => true],
    ]);

    $user = User::factory()->create(array_merge(['brand_id' => $brand->id], $billingProfile));
    CustomerBalance::create(['user_id' => $user->id, 'balance' => 100]);

    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Game Plan',
        'plesk_package_name' => null,
        'panel_type' => 'pterodactyl',
        'price' => 5.00,
        'is_active' => true,
    ]);

    $periodEndsAt = now()->addDays(2);
    $account = GameServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'name' => 'Test Server',
        'status' => 'active',
        'stripe_subscription_id' => null,
        'renewal_type' => 'manual',
        'current_period_ends_at' => $periodEndsAt,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('gaming-accounts.renew', $account), [
        'payment_method' => 'balance',
        'period_months' => 3,
    ]);

    $response->assertRedirect(route('gaming-accounts.show', $account));
    $response->assertSessionHas('success');

    $account->refresh();
    expect($account->current_period_ends_at->format('Y-m-d'))->toBe($periodEndsAt->copy()->addMonths(3)->format('Y-m-d'));

    $balance = CustomerBalance::where('user_id', $user->id)->first();
    expect((float) $balance->balance)->toBe(85.0);
});

test('renew with balance returns error when insufficient balance', function () use ($billingProfile) {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-gaming-low',
        'name' => 'Test Gaming Low Balance',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'prepaid_balance' => true],
    ]);

    $user = User::factory()->create(array_merge(['brand_id' => $brand->id], $billingProfile));
    CustomerBalance::create(['user_id' => $user->id, 'balance' => 2]);

    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Game Plan',
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
        'stripe_subscription_id' => null,
        'renewal_type' => 'manual',
        'current_period_ends_at' => now()->addDays(5),
    ]);

    $this->actingAs($user);

    $response = $this->post(route('gaming-accounts.renew', $account), [
        'payment_method' => 'balance',
        'period_months' => 1,
    ]);

    $response->assertRedirect(route('gaming-accounts.show', $account));
    $response->assertSessionHas('error');
});

test('renew with mollie redirects to checkout flow', function () use ($billingProfile) {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-gaming-mollie',
        'name' => 'Test Gaming Mollie',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true],
    ]);

    $user = User::factory()->create(array_merge(['brand_id' => $brand->id], $billingProfile));

    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Game Plan',
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
        'stripe_subscription_id' => null,
        'renewal_type' => 'manual',
        'current_period_ends_at' => now()->addDays(5),
    ]);

    $this->actingAs($user);

    $response = $this->post(route('gaming-accounts.renew', $account), [
        'payment_method' => 'mollie',
        'period_months' => 1,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('renew requires account to belong to user', function () use ($billingProfile) {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-gaming-auth',
        'name' => 'Test Gaming Auth',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'prepaid_balance' => true],
    ]);
    $owner = User::factory()->create(array_merge(['brand_id' => $brand->id], $billingProfile));
    $otherUser = User::factory()->create(array_merge(['brand_id' => $brand->id], $billingProfile));

    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'name' => 'Test Plan',
        'plesk_package_name' => null,
        'panel_type' => 'pterodactyl',
        'price' => 5,
        'is_active' => true,
    ]);

    $account = GameServerAccount::create([
        'user_id' => $owner->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'name' => 'Owner Server',
        'status' => 'active',
        'stripe_subscription_id' => null,
        'renewal_type' => 'manual',
        'current_period_ends_at' => now()->addDay(),
    ]);

    $this->actingAs($otherUser);

    $response = $this->post(route('gaming-accounts.renew', $account), [
        'payment_method' => 'balance',
        'period_months' => 1,
    ]);

    $response->assertForbidden();
});
