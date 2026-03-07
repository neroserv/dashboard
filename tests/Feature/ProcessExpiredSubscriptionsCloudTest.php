<?php

use App\Jobs\ProcessExpiredSubscriptions;
use App\Models\Brand;
use App\Models\GameServerAccount;
use App\Models\GameserverCloudPlan;
use App\Models\GameserverCloudSubscription;
use App\Models\HostingServer;
use App\Models\User;

test('expired gameserver cloud subscription is suspended by job', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'cloud-expire',
        'name' => 'Cloud Expire',
        'domains' => null,
        'is_default' => true,
        'features' => ['gameserver_cloud' => true],
    ]);

    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'pterodactyl',
        'name' => 'Ptero',
        'hostname' => 'panel.test',
        'config' => [],
        'is_active' => true,
    ]);

    $plan = GameserverCloudPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
        'name' => 'Plan',
        'price' => 10,
        'config' => [],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);

    $subscription = GameserverCloudSubscription::create([
        'user_id' => $user->id,
        'gameserver_cloud_plan_id' => $plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->subDay(),
    ]);

    $account = GameServerAccount::create([
        'user_id' => $user->id,
        'gameserver_cloud_subscription_id' => $subscription->id,
        'hosting_plan_id' => null,
        'hosting_server_id' => $server->id,
        'name' => 'Cloud Server',
        'status' => 'active',
        'identifier' => null,
    ]);

    (new ProcessExpiredSubscriptions)->handle();

    $subscription->refresh();
    $account->refresh();
    expect($subscription->status)->toBe('suspended');
    expect($account->status)->toBe('suspended');
});
