<?php

use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\GameServerAccount;
use App\Models\GameserverCloudPlan;
use App\Models\GameserverCloudSubscription;
use App\Models\HostingServer;
use App\Models\ProductShare;
use App\Models\User;

beforeEach(function () {
    Brand::query()->update(['is_default' => false]);
    $this->brand = Brand::create([
        'key' => 'cloud-sharing',
        'name' => 'Cloud Sharing Test',
        'domains' => null,
        'is_default' => true,
        'features' => ['gaming' => true, 'gameserver_cloud' => true],
    ]);

    BrandExtension::query()->create([
        'brand_id' => $this->brand->id,
        'extension' => BrandExtension::EXTENSION_PTERODACTYL,
        'installed_at' => now(),
    ]);

    $this->server = HostingServer::create([
        'brand_id' => $this->brand->id,
        'panel_type' => 'pterodactyl',
        'name' => 'Ptero',
        'hostname' => 'panel.test',
        'config' => [],
        'is_active' => true,
    ]);

    $this->plan = GameserverCloudPlan::create([
        'brand_id' => $this->brand->id,
        'hosting_server_id' => $this->server->id,
        'name' => 'Test Plan',
        'price' => 10,
        'config' => ['max_cpu' => 100, 'max_memory_mb' => 2048, 'max_disk_gb' => 10],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->owner = User::factory()->create(['brand_id' => $this->brand->id]);

    $this->subscription = GameserverCloudSubscription::create([
        'user_id' => $this->owner->id,
        'gameserver_cloud_plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->collaborator = User::factory()->create(['brand_id' => $this->brand->id]);

    $this->account = GameServerAccount::create([
        'user_id' => $this->owner->id,
        'gameserver_cloud_subscription_id' => $this->subscription->id,
        'hosting_plan_id' => null,
        'hosting_server_id' => $this->server->id,
        'name' => 'Cloud Server',
        'status' => 'active',
        'identifier' => 'abc123',
        'allocation' => ['cpu' => 50, 'memory_mb' => 1024, 'disk_mb' => 2048, 'nest_id' => 1, 'egg_id' => 1],
    ]);
});

test('user shared with view on subscription can view cloud game server account show page', function () {
    ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $this->collaborator->id,
        'permissions' => ['view'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->collaborator);

    $this->get(route('gaming-accounts.show', $this->account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('gaming-accounts/Show'));
});

test('user shared with view only cannot delete cloud server', function () {
    ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $this->collaborator->id,
        'permissions' => ['view'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->collaborator);

    $this->delete(route('gaming.cloud.subscriptions.servers.destroy', [$this->subscription, $this->account]))
        ->assertForbidden();
});

test('user shared with manage_servers can delete cloud server', function () {
    ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $this->collaborator->id,
        'permissions' => ['view', 'manage_servers'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->collaborator);

    $this->delete(route('gaming.cloud.subscriptions.servers.destroy', [$this->subscription, $this->account]))
        ->assertRedirect(route('gaming.cloud.subscriptions.show', $this->subscription));

    expect(GameServerAccount::query()->whereKey($this->account->id)->exists())->toBeFalse();
});

test('user shared with view only cannot update cloud server resources', function () {
    ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $this->collaborator->id,
        'permissions' => ['view'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->collaborator);

    $this->put(
        route('gaming.cloud.subscriptions.servers.resources.update', [$this->subscription, $this->account]),
        ['cpu' => 50, 'memory_mb' => 1024, 'disk_mb' => 2048]
    )->assertForbidden();
});

test('user shared with manage_servers can update cloud server resources', function () {
    ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $this->collaborator->id,
        'permissions' => ['view', 'manage_servers'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->collaborator);

    $this->put(
        route('gaming.cloud.subscriptions.servers.resources.update', [$this->subscription, $this->account]),
        ['cpu' => 50, 'memory_mb' => 1024, 'disk_mb' => 2048]
    )->assertRedirect();
});

test('cloud server creator who is not subscription owner cannot manage collaborators on game server account', function () {
    $creator = User::factory()->create(['brand_id' => $this->brand->id]);

    $this->account->update(['user_id' => $creator->id]);

    ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $creator->id,
        'permissions' => ['view', 'manage_servers'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($creator);

    $this->post(route('gaming-accounts.shares.invitations.store', $this->account), [
        'email' => 'invitee@example.com',
        'permissions' => ['view'],
    ])->assertForbidden();
});

test('cannot destroy product share row that targets subscription owner user id', function () {
    $bogusShare = ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $this->owner->id,
        'permissions' => ['view'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->owner);

    $this->delete(route('gaming.cloud.subscriptions.shares.destroy', [$this->subscription, $bogusShare]))
        ->assertForbidden();
});

test('user with create_server share passes store cloud server authorization', function () {
    $creator = User::factory()->create(['brand_id' => $this->brand->id]);

    ProductShare::create([
        'shareable_type' => GameserverCloudSubscription::class,
        'shareable_id' => $this->subscription->id,
        'user_id' => $creator->id,
        'permissions' => ['view', 'create_server'],
        'accepted_at' => now(),
    ]);

    $this->actingAs($creator);

    $this->post(route('gaming.cloud.subscriptions.servers.store', $this->subscription), [])
        ->assertSessionHasErrors();
});
