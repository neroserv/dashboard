<?php

use App\Models\Brand;
use App\Models\User;

test('admin can view hosting plans index with webspace, pterodactyl, teamspeak and cloud props', function () {
    Brand::query()->update(['is_default' => false]);
    Brand::create([
        'key' => 'test',
        'name' => 'Test Brand',
        'domains' => null,
        'is_default' => true,
        'features' => ['webspace' => true, 'gaming' => true, 'teamspeak' => true, 'gameserver_cloud' => false],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.hosting-plans.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/hosting-plans/Index')
        ->has('hostingPlans')
        ->has('hostingPlans.data')
        ->has('hostingPlansPterodactyl')
        ->has('hostingPlansPterodactyl.data')
        ->has('hostingPlansTeamSpeak')
        ->has('hostingPlansTeamSpeak.data')
        ->where('brandHasGaming', true)
        ->where('brandHasTeamSpeak', true)
        ->where('brandHasGameserverCloud', false)
    );
});
