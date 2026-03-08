<?php

use App\Models\Brand;
use App\Models\GameServerAccount;
use App\Models\GameserverCloudPlan;
use App\Models\GameserverCloudSubscription;
use App\Models\HostingServer;
use App\Models\User;
use App\Services\ControlPanels\PterodactylClient;

beforeEach(function () {
    Brand::query()->update(['is_default' => false]);
    $this->brand = Brand::create([
        'key' => 'cloud-accounts-admin',
        'name' => 'Cloud Accounts Admin Test',
        'domains' => null,
        'is_default' => true,
        'features' => ['gameserver_cloud' => true],
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
        'name' => 'Test Plan',
        'price' => 10,
        'config' => ['max_cpu' => 100, 'max_memory_mb' => 2048, 'max_disk_gb' => 10],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->admin = User::factory()->create(['is_admin' => true, 'brand_id' => $this->brand->id]);
    $this->user = User::factory()->create(['brand_id' => $this->brand->id]);
});

test('admin can access gameserver cloud accounts index when brand has feature', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.gameserver-cloud-accounts.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/gameserver-cloud-accounts/Index')
        ->has('subscriptions')
        ->has('subscriptions.data')
        ->has('brandHasGameserverCloud')
        ->where('brandHasGameserverCloud', true)
    );
});

test('admin sees subscriptions list with server count', function () {
    $subscription = GameserverCloudSubscription::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    GameServerAccount::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_subscription_id' => $subscription->id,
        'hosting_plan_id' => null,
        'hosting_server_id' => $this->hostingServer->id,
        'name' => 'Cloud Server',
        'status' => 'active',
        'identifier' => 'xyz',
        'allocation' => ['cpu' => 50, 'memory_mb' => 1024, 'disk_mb' => 2048],
    ]);

    $this->actingAs($this->admin);

    $response = $this->get(route('admin.gameserver-cloud-accounts.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/gameserver-cloud-accounts/Index')
        ->has('subscriptions.data', 1)
        ->where('subscriptions.data.0.id', $subscription->id)
        ->where('subscriptions.data.0.servers_count', 1)
        ->where('subscriptions.data.0.gameserver_cloud_plan.name', 'Test Plan')
    );
});

test('admin can open cloud subscription show', function () {
    $subscription = GameserverCloudSubscription::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $account = GameServerAccount::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_subscription_id' => $subscription->id,
        'hosting_plan_id' => null,
        'hosting_server_id' => $this->hostingServer->id,
        'name' => 'My Server',
        'status' => 'active',
        'identifier' => 'abc',
        'allocation' => ['cpu' => 25, 'memory_mb' => 512, 'disk_mb' => 1024],
    ]);

    $this->actingAs($this->admin);

    $response = $this->get(route('admin.gameserver-cloud-accounts.show', $subscription));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/gameserver-cloud-accounts/Show')
        ->has('subscription')
        ->where('subscription.id', $subscription->id)
        ->has('gameServerAccounts', 1)
        ->where('gameServerAccounts.0.id', $account->id)
        ->where('gameServerAccounts.0.name', 'My Server')
        ->has('gameserverCloudPlans')
    );
});

test('admin gets 404 when opening subscription of other brand', function () {
    $otherBrand = Brand::create([
        'key' => 'other',
        'name' => 'Other',
        'domains' => null,
        'is_default' => false,
        'features' => [],
    ]);

    $otherServer = HostingServer::create([
        'brand_id' => $otherBrand->id,
        'panel_type' => 'pterodactyl',
        'name' => 'Other Ptero',
        'hostname' => 'other.test',
        'config' => [],
        'is_active' => true,
    ]);

    $otherPlan = GameserverCloudPlan::create([
        'brand_id' => $otherBrand->id,
        'hosting_server_id' => $otherServer->id,
        'name' => 'Other Plan',
        'price' => 5,
        'config' => [],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $otherUser = User::factory()->create(['brand_id' => $otherBrand->id]);
    $subscription = GameserverCloudSubscription::create([
        'user_id' => $otherUser->id,
        'gameserver_cloud_plan_id' => $otherPlan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($this->admin);

    $response = $this->get(route('admin.gameserver-cloud-accounts.show', $subscription));

    $response->assertNotFound();
});

test('admin can update subscription plan', function () {
    $plan2 = GameserverCloudPlan::create([
        'brand_id' => $this->brand->id,
        'hosting_server_id' => $this->hostingServer->id,
        'name' => 'Bigger Plan',
        'price' => 20,
        'config' => ['max_cpu' => 200, 'max_memory_mb' => 4096, 'max_disk_gb' => 20],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $subscription = GameserverCloudSubscription::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($this->admin);

    $response = $this->put(route('admin.gameserver-cloud-accounts.update', $subscription), [
        'plan_display_user_defined' => false,
        'gameserver_cloud_plan_id' => $plan2->id,
    ]);

    $response->assertRedirect(route('admin.gameserver-cloud-accounts.show', $subscription));
    $subscription->refresh();
    expect($subscription->gameserver_cloud_plan_id)->toBe($plan2->id);
    expect($subscription->plan_display_user_defined)->toBeFalse();
    expect($subscription->custom_max_cpu)->toBeNull();
    expect($subscription->custom_price)->toBeNull();
});

test('admin can set plan display to user defined with custom resources and price', function () {
    $subscription = GameserverCloudSubscription::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
        'plan_display_user_defined' => false,
    ]);

    $this->actingAs($this->admin);

    $response = $this->put(route('admin.gameserver-cloud-accounts.update', $subscription), [
        'plan_display_user_defined' => true,
        'custom_max_cpu' => 300,
        'custom_max_memory_mb' => 8192,
        'custom_max_disk_gb' => 40,
        'custom_price' => 39.99,
    ]);

    $response->assertRedirect(route('admin.gameserver-cloud-accounts.show', $subscription));
    $subscription->refresh();
    expect($subscription->plan_display_user_defined)->toBeTrue();
    expect($subscription->gameserver_cloud_plan_id)->toBe($this->plan->id);
    expect($subscription->custom_max_cpu)->toBe(300);
    expect($subscription->custom_max_memory_mb)->toBe(8192);
    expect($subscription->custom_max_disk_gb)->toBe(40);
    expect((float) $subscription->custom_price)->toBe(39.99);
});

test('admin plan update rejects plan of other brand', function () {
    $otherBrand = Brand::create([
        'key' => 'other',
        'name' => 'Other',
        'domains' => null,
        'is_default' => false,
        'features' => [],
    ]);

    $otherServer = HostingServer::create([
        'brand_id' => $otherBrand->id,
        'panel_type' => 'pterodactyl',
        'name' => 'Other Ptero',
        'hostname' => 'other.test',
        'config' => [],
        'is_active' => true,
    ]);

    $otherPlan = GameserverCloudPlan::create([
        'brand_id' => $otherBrand->id,
        'hosting_server_id' => $otherServer->id,
        'name' => 'Other Plan',
        'price' => 5,
        'config' => [],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $subscription = GameserverCloudSubscription::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($this->admin);

    $response = $this->put(route('admin.gameserver-cloud-accounts.update', $subscription), [
        'gameserver_cloud_plan_id' => $otherPlan->id,
    ]);

    $response->assertSessionHasErrors('gameserver_cloud_plan_id');
    $subscription->refresh();
    expect($subscription->gameserver_cloud_plan_id)->toBe($this->plan->id);
});

test('admin can update cloud server resources and sets allocation_manually_set', function () {
    $subscription = GameserverCloudSubscription::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $account = GameServerAccount::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_subscription_id' => $subscription->id,
        'hosting_plan_id' => null,
        'hosting_server_id' => $this->hostingServer->id,
        'name' => 'My Server',
        'status' => 'active',
        'identifier' => 'abc',
        'pterodactyl_server_id' => 999,
        'allocation' => ['cpu' => 25, 'memory_mb' => 512, 'disk_mb' => 1024],
        'allocation_manually_set' => false,
    ]);

    $this->mock(PterodactylClient::class, function ($mock) {
        $mock->shouldReceive('setServer')->andReturnSelf();
        $mock->shouldReceive('updateServerBuild')->once()->andReturnNull();
    });

    $this->actingAs($this->admin);

    $response = $this->put(
        route('admin.gameserver-cloud-accounts.servers.resources.update', [$subscription, $account]),
        ['cpu' => 50, 'memory_mb' => 1024, 'disk_mb' => 2048]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $account->refresh();
    expect($account->allocation_manually_set)->toBeTrue();
    expect($account->allocation['cpu'])->toBe(50);
    expect($account->allocation['memory_mb'])->toBe(1024);
    expect($account->allocation['disk_mb'])->toBe(2048);
});

test('admin can update subscription period and status', function () {
    $newEndsAt = now()->addMonths(2)->format('Y-m-d');
    $subscription = GameserverCloudSubscription::create([
        'user_id' => $this->user->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
        'cancel_at_period_end' => false,
    ]);

    $this->actingAs($this->admin);

    $response = $this->put(route('admin.gameserver-cloud-accounts.period-and-status.update', $subscription), [
        'current_period_ends_at' => $newEndsAt,
        'status' => 'active',
        'cancel_at_period_end' => true,
    ]);

    $response->assertRedirect(route('admin.gameserver-cloud-accounts.show', $subscription));
    $response->assertSessionHas('success');

    $subscription->refresh();
    expect($subscription->current_period_ends_at->format('Y-m-d'))->toBe($newEndsAt);
    expect($subscription->status)->toBe('active');
    expect($subscription->cancel_at_period_end)->toBeTrue();
});
