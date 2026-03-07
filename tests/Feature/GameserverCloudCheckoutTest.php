<?php

use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\GameserverCloudPlan;
use App\Models\GameserverCloudSubscription;
use App\Models\HostingServer;
use App\Models\User;

beforeEach(function () {
    Brand::query()->update(['is_default' => false]);
    $this->brand = Brand::create([
        'key' => 'cloud-checkout',
        'name' => 'Cloud Checkout Test',
        'domains' => ['cloud.praxishosting.test'],
        'is_default' => true,
        'features' => ['gameserver_cloud' => true, 'prepaid_balance' => true],
    ]);

    $this->hostingServer = HostingServer::create([
        'brand_id' => $this->brand->id,
        'panel_type' => 'pterodactyl',
        'name' => 'Ptero',
        'hostname' => 'panel.test',
        'config' => [],
        'is_active' => true,
    ]);

    $this->plan = GameserverCloudPlan::create([
        'brand_id' => $this->brand->id,
        'hosting_server_id' => $this->hostingServer->id,
        'name' => 'Test Cloud Plan',
        'price' => 10.00,
        'config' => ['max_cpu' => 200, 'max_memory_mb' => 4096, 'max_disk_gb' => 20],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $billingProfile = [
        'street' => 'Teststr. 1',
        'postal_code' => '12345',
        'city' => 'Berlin',
        'country' => 'DE',
    ];
    $this->user = User::factory()->create(array_merge(['brand_id' => $this->brand->id], $billingProfile));
});

test('user can complete cloud plan checkout with balance', function () {
    CustomerBalance::create(['user_id' => $this->user->id, 'balance' => 50]);
    $this->actingAs($this->user);

    $response = $this->post(route('gaming.cloud.checkout.store'), [
        'gameserver_cloud_plan_id' => $this->plan->id,
        'period_months' => 1,
        'payment_method' => 'balance',
        'accept_tos' => '1',
        'accept_early_execution' => '1',
    ]);

    $response->assertRedirect();
    expect(GameserverCloudSubscription::query()->where('user_id', $this->user->id)->where('gameserver_cloud_plan_id', $this->plan->id)->exists())->toBeTrue();
});

test('cloud subscriptions index is accessible when brand has feature', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('gaming.cloud.subscriptions.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('gaming/cloud/SubscriptionsIndex'));
});
