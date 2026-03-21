<?php

use App\Models\Brand;
use App\Models\GameserverCloudPlan;
use App\Models\HostingServer;
use App\Models\User;

beforeEach(function () {
    Brand::query()->update(['is_default' => false]);
    $this->brand = Brand::create([
        'key' => 'cloud-test',
        'name' => 'Cloud Test',
        'domains' => null,
        'is_default' => true,
        'features' => ['gameserver_cloud' => true],
    ]);

    $this->hostingServer = HostingServer::create([
        'brand_id' => $this->brand->id,
        'panel_type' => 'pterodactyl',
        'name' => 'Ptero Test',
        'hostname' => 'panel.test',
        'config' => [],
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create(['is_admin' => true, 'brand_id' => $this->brand->id]);
});

test('admin can access gameserver cloud plans index when brand has feature', function () {
    $this->actingAs($this->admin);

    $response = $this->followingRedirects()
        ->get(route('admin.gameserver-cloud-plans.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/hosting-plans/Index')
        ->has('gameserverCloudPlans')
        ->where('brandHasGameserverCloud', true)
    );
});

test('admin can create gameserver cloud plan', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('admin.gameserver-cloud-plans.store'), [
        'name' => 'Small Cloud',
        'price' => 9.99,
        'hosting_server_id' => $this->hostingServer->id,
        'config' => ['max_cpu' => 200, 'max_memory_mb' => 4096, 'max_disk_gb' => 20],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertRedirect();
    expect(GameserverCloudPlan::query()->where('name', 'Small Cloud')->exists())->toBeTrue();
});

test('admin can update and delete gameserver cloud plan', function () {
    $plan = GameserverCloudPlan::create([
        'brand_id' => $this->brand->id,
        'hosting_server_id' => $this->hostingServer->id,
        'name' => 'To Update',
        'price' => 5.00,
        'config' => [],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($this->admin);

    $this->put(route('admin.gameserver-cloud-plans.update', $plan), [
        'name' => 'Updated Name',
        'price' => 7.50,
        'hosting_server_id' => $this->hostingServer->id,
        'config' => [],
        'is_active' => true,
        'sort_order' => 0,
    ])->assertRedirect();

    $plan->refresh();
    expect($plan->name)->toBe('Updated Name');

    $this->delete(route('admin.gameserver-cloud-plans.destroy', $plan))->assertRedirect();
    expect(GameserverCloudPlan::find($plan->id))->toBeNull();
});
